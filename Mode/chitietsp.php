<?php
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy ID sản phẩm từ URL
$sp_ma = isset($_GET['sp']) ? intval($_GET['sp']) : 0;


// --- Lưu sản phẩm đã xem gần đây vào session ---
session_start();
if (!isset($_SESSION['recent_viewed'])) {
    $_SESSION['recent_viewed'] = [];
}

// Nếu sản phẩm chưa có trong danh sách thì thêm vào đầu mảng
if (!in_array($sp_ma, $_SESSION['recent_viewed'])) {
    array_unshift($_SESSION['recent_viewed'], $sp_ma);
}

// Giới hạn tối đa 4 sản phẩm đã xem gần đây
if (count($_SESSION['recent_viewed']) > 4) {
    $_SESSION['recent_viewed'] = array_slice($_SESSION['recent_viewed'], 0, 4);
}


// Lấy thông tin sản phẩm + giá mới nhất
$sql_sp = "SELECT 
    sp.SP_MA,
    sp.SP_TEN,
    sp.SP_CHATLIEU,
    sp.SP_MOTA,
    sp.SP_CONSUDUNG,
    sp.DM_MA,
    dg.DONGIA AS GIA_GOC,
    COALESCE(
        CASE 
            WHEN km.KM_CONSUDUNG = 1 
              AND CURDATE() BETWEEN km.KM_NGAYBATDAU AND km.KM_NGAYKETTHUC 
            THEN ctkm.CTKM_PHANTRAM_GIAM 
            ELSE 0 
        END, 
    0) AS PHAN_TRAM_GIAM,
    ROUND(dg.DONGIA * 
        (100 - COALESCE(
            CASE 
                WHEN km.KM_CONSUDUNG = 1 
                  AND CURDATE() BETWEEN km.KM_NGAYBATDAU AND km.KM_NGAYKETTHUC 
                THEN ctkm.CTKM_PHANTRAM_GIAM 
                ELSE 0 
            END, 
        0)) / 100, 0) AS GIA_HIEN_THI
FROM san_pham sp
-- Lấy giá mới nhất
JOIN (
    SELECT d1.SP_MA, d1.DONGIA
    FROM don_gia_ban d1
    JOIN (
        SELECT SP_MA, MAX(TD_THOIDIEM) AS MAX_TIME
        FROM don_gia_ban
        GROUP BY SP_MA
    ) d2 ON d1.SP_MA = d2.SP_MA AND d1.TD_THOIDIEM = d2.MAX_TIME
) dg ON sp.SP_MA = dg.SP_MA
-- Lấy thông tin khuyến mãi (nếu có)
LEFT JOIN chi_tiet_khuyen_mai ctkm ON sp.SP_MA = ctkm.SP_MA
LEFT JOIN khuyen_mai km ON ctkm.KM_MA = km.KM_MA
WHERE sp.SP_CONSUDUNG = 1
  AND sp.SP_MA = $sp_ma;
";

$result_sp = $conn->query($sql_sp);

if(!$result_sp || $result_sp->num_rows == 0){
    echo "<h2>Sản phẩm không tồn tại hoặc đã ngưng kinh doanh.</h2>";
    exit;
}
$product = $result_sp->fetch_assoc();
$dm_ma = $product['DM_MA'];

// Lấy ảnh sản phẩm
$sql_img = "SELECT ANH_DUONGDAN FROM ANH_SAN_PHAM WHERE SP_MA = $sp_ma";
$result_img = $conn->query($sql_img);
$images = [];
while ($row = $result_img->fetch_assoc()) {
    $images[] = $row['ANH_DUONGDAN'];
}

// Lấy kích thước sản phẩm
$sql_size = "SELECT kt.KT_TEN, kt.KT_MA, ct.CTSP_SOLUONGTON 
    FROM CHI_TIET_SAN_PHAM ct
    JOIN KICH_THUOC kt ON ct.KT_MA = kt.KT_MA
    WHERE ct.SP_MA = $sp_ma
";
$result_size = $conn->query($sql_size);
$sizes = [];
while ($row = $result_size->fetch_assoc()) {
    $sizes[] = $row;
}

// Lấy tên danh mục hiện tại và danh mục cha (nếu có)
$sql_breadcrumb = "WITH RECURSIVE dm_path AS (
        SELECT DM_MA, DM_TEN, DM_CHA
        FROM DANH_MUC
        WHERE DM_MA = $dm_ma
        UNION ALL
        SELECT d.DM_MA, d.DM_TEN, d.DM_CHA
        FROM DANH_MUC d
        INNER JOIN dm_path dp ON d.DM_MA = dp.DM_CHA
    )
    SELECT * FROM dm_path;
";

$result_breadcrumb = $conn->query($sql_breadcrumb);
$breadcrumb = [];
if($result_breadcrumb){
    while ($row = $result_breadcrumb->fetch_assoc()) {
        $breadcrumb[] = $row;
    }
}
$breadcrumb = array_reverse($breadcrumb);
$ten_dm = !empty($breadcrumb) ? htmlspecialchars($breadcrumb[count($breadcrumb)-1]['DM_TEN']) : '';


// Đánh giá sản phẩm
$sql_danh_gia = "SELECT ph.PH_SOSAO, ph.PH_NOIDUNG, ph.PH_NGAYGIO, nd.ND_HOTEN
    FROM PHAN_HOI ph
    JOIN KHACH_HANG kh ON ph.ND_MA = kh.ND_MA
    JOIN NGUOI_DUNG nd ON kh.ND_MA = nd.ND_MA
    WHERE ph.SP_MA = ?
    ORDER BY ph.PH_NGAYGIO DESC
    LIMIT 2
";
// Thực thi
$stmt_danh_gia = $conn->prepare($sql_danh_gia);
$stmt_danh_gia->bind_param("i", $sp_ma);
$stmt_danh_gia->execute();
$result_danh_gia = $stmt_danh_gia->get_result();

// sản phẩm tương tự
$sql_tuong_tu = "WITH anh_cte AS (
  SELECT 
      x.SP_MA,
      MAX(CASE WHEN rn_anh = 1 THEN x.ANH_DUONGDAN END) AS Anh1,
      MAX(CASE WHEN rn_anh = 2 THEN x.ANH_DUONGDAN END) AS Anh2
  FROM (
      SELECT 
          a.ANH_DUONGDAN,
          a.SP_MA,
          ROW_NUMBER() OVER (PARTITION BY a.SP_MA ORDER BY a.ANH_MA ASC) AS rn_anh
      FROM anh_san_pham a
  ) x
  GROUP BY x.SP_MA
),
gia_cte AS (
  SELECT 
      sp.SP_MA,
      sp.SP_TEN,
      -- Giá gốc mới nhất
      (
          SELECT g.DONGIA
          FROM don_gia_ban g
          JOIN (
              SELECT SP_MA, MAX(TD_THOIDIEM) AS MAX_TIME
              FROM don_gia_ban
              GROUP BY SP_MA
          ) gg ON g.SP_MA = gg.SP_MA AND g.TD_THOIDIEM = gg.MAX_TIME
          WHERE g.SP_MA = sp.SP_MA
      ) AS GIA_GOC,
      -- Tính giá hiển thị theo khuyến mãi còn hiệu lực
      ROUND(
          (
              (
                  SELECT g.DONGIA
                  FROM don_gia_ban g
                  JOIN (
                      SELECT SP_MA, MAX(TD_THOIDIEM) AS MAX_TIME
                      FROM don_gia_ban
                      GROUP BY SP_MA
                  ) gg ON g.SP_MA = gg.SP_MA AND g.TD_THOIDIEM = gg.MAX_TIME
                  WHERE g.SP_MA = sp.SP_MA
              ) 
              * 
              (100 - COALESCE(
                  (
                      SELECT ctkm.CTKM_PHANTRAM_GIAM
                      FROM chi_tiet_khuyen_mai ctkm
                      JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
                      WHERE ctkm.SP_MA = sp.SP_MA 
                        AND k.KM_CONSUDUNG = 1
                        AND CURDATE() BETWEEN k.KM_NGAYBATDAU AND k.KM_NGAYKETTHUC
                      ORDER BY k.KM_NGAYBATDAU DESC
                      LIMIT 1
                  ), 0)
              ) / 100
          ), 0
      ) AS GIA_HIEN_THI,
      COALESCE(
          (
              SELECT ctkm.CTKM_PHANTRAM_GIAM
              FROM chi_tiet_khuyen_mai ctkm
              JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
              WHERE ctkm.SP_MA = sp.SP_MA 
                AND k.KM_CONSUDUNG = 1
                AND CURDATE() BETWEEN k.KM_NGAYBATDAU AND k.KM_NGAYKETTHUC
              ORDER BY k.KM_NGAYBATDAU DESC
              LIMIT 1
          ), 0
      ) AS PHAN_TRAM_GIAM
  FROM san_pham sp
  WHERE sp.SP_CONSUDUNG = 1
    AND sp.DM_MA = (SELECT DM_MA FROM SAN_PHAM WHERE SP_MA = $sp_ma)
    AND sp.SP_MA <> $sp_ma
)
SELECT g.*, a.Anh1, a.Anh2
FROM gia_cte g
LEFT JOIN anh_cte a ON g.SP_MA = a.SP_MA
ORDER BY g.SP_MA DESC
LIMIT 4;
";

$result_tuong_tu = $conn->query($sql_tuong_tu);


// --- Lấy thông tin 4 sản phẩm đã xem gần đây ---
$recent_viewed_products = [];
if (!empty($_SESSION['recent_viewed'])) {
    $ids = implode(',', array_map('intval', $_SESSION['recent_viewed']));

    $sql_recent = "SELECT 
        sp.SP_MA,
        sp.SP_TEN,
        g.DONGIA AS GIA_MOI,
        COALESCE(ctkm.CTKM_PHANTRAM_GIAM, 0) AS CTKM_PHANTRAM_GIAM,
        MAX(CASE WHEN a.rn = 1 THEN a.ANH_DUONGDAN END) AS Anh1,
        MAX(CASE WHEN a.rn = 2 THEN a.ANH_DUONGDAN END) AS Anh2
    FROM SAN_PHAM sp
    LEFT JOIN (
        SELECT SP_MA, DONGIA
        FROM (
            SELECT SP_MA, DONGIA,
                  ROW_NUMBER() OVER (PARTITION BY SP_MA ORDER BY TD_THOIDIEM DESC) AS rn
            FROM DON_GIA_BAN
        ) t
        WHERE rn = 1
    ) g ON sp.SP_MA = g.SP_MA
    LEFT JOIN (
        SELECT ctkm.SP_MA, ctkm.CTKM_PHANTRAM_GIAM
        FROM CHI_TIET_KHUYEN_MAI ctkm
        JOIN KHUYEN_MAI km 
          ON ctkm.KM_MA = km.KM_MA 
          AND km.KM_CONSUDUNG = 1
          AND km.KM_NGAYBATDAU <= CURDATE()
          AND km.KM_NGAYKETTHUC >= CURDATE()
    ) ctkm ON sp.SP_MA = ctkm.SP_MA
    LEFT JOIN (
        SELECT SP_MA, ANH_DUONGDAN, 
               ROW_NUMBER() OVER (PARTITION BY SP_MA ORDER BY ANH_MA ASC) AS rn
        FROM ANH_SAN_PHAM
    ) a ON sp.SP_MA = a.SP_MA AND a.rn <= 2
    WHERE sp.SP_MA IN ($ids)
      AND sp.SP_CONSUDUNG = 1
    GROUP BY sp.SP_MA, sp.SP_TEN, g.DONGIA, ctkm.CTKM_PHANTRAM_GIAM
    ORDER BY FIELD(sp.SP_MA, $ids)
    LIMIT 4
    ";

    $recent_viewed_products = $conn->query($sql_recent);
}


?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($product['SP_TEN']) ?></title>
  <!-- Icon -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <!-- Bootstrap CSS -->
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Link Css -->
  <link href="../assets/css/home.css" rel="stylesheet">
  <link href="../assets/css/detail_product.css" rel="stylesheet">
</head>
<body <?= isset($_SESSION['nd_ma']) ? 'data-nd-ma="'.intval($_SESSION['nd_ma']).'"' : '' ?>>

<!-- Header row -->
<div class="container header-row">
  <div class="row align-items-center">
    <div class="col-md-3 col-8">
      <a href="#" class="brand-wrap text-decoration-none">
        <img src="../assets/images/logo.png" alt="Logo" class="logo">
        <div>
          <div style="font-family:'Playfair Display', serif; font-weight:700; font-size:25px; color:#4682B4; letter-spacing:3px;">MODÉ</div>
          <div style="font-size:15px; color:#777">Thời trang nam nữ</div>
        </div>
      </a>
    </div>

    <div class="col-md-6 d-none d-md-flex">
      <form class="search-bar" action="timkiem.php" method="get">
        <input name="q" type="search" placeholder="Tìm kiếm sản phẩm...">
        <!-- <button type="submit" class="btn-search"><i class="fa fa-search"></i></button> -->
        <!-- Nút tìm kiếm bằng hình ảnh -->
        <button type="button" class="btn-search-image">
          <i class="fa fa-camera"></i>
        </button>
      </form>
    </div>

    <div class="col-md-3 col-4 d-flex justify-content-end align-items-center gap-4">
      <a class="text-decoration-none text-dark" href="#" id="btn-danhmuc">
        <i class="fa-solid fa-list icon-category"></i>
      </a>
      <!-- Người dùng -->
      <div class="dropdown user-dropdown">
        <?php if (isset($_SESSION['nd_hoten'])): ?>
          <a class="nav-link text-dark d-flex align-items-center gap-1" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-user icon-user"></i>
            <span>Xin chào, <?= htmlspecialchars($_SESSION['nd_hoten']) ?> ▼</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
            <li><a class="dropdown-item" href="../KhachHang/khachhang.php">Trang cá nhân</a></li>
            <li><a class="dropdown-item" href="../KhachHang/lichsu_donhang.php">Đơn hàng của tôi</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="dangxuat.php">Đăng xuất</a></li>
          </ul>
        <?php else: ?>
          <a class="nav-link text-dark" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-user icon-user"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
            <li><a class="dropdown-item" href="dangnhap.php">Đăng nhập</a></li>
            <li><a class="dropdown-item" href="dangky.php">Đăng ký</a></li>
          </ul>
        <?php endif; ?>
      </div>

      <div class="position-relative">
        <a href="#" class="text-dark fs-5 cart-icon">
          <i class="fa-solid fa-cart-shopping icon-cart"></i>
        </a>
        <span id="cart-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">0</span>
      </div>
    </div>
  </div>
</div>

<!-- Modal giỏ tạm -->
<div class="modal fade" id="cartTempModal" data-bs-backdrop="false" tabindex="-1" aria-labelledby="cartTempModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cartTempModalLabel">Giỏ hàng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="cartTempContent"></div>
      <div class="modal-footer">
        <span id="cartTempTotal" class="me-auto fw-bold"></span>
        <a href="cart.php" class="btn btn-primary">Đi đến giỏ hàng</a>
      </div>
    </div>
  </div>
</div>

<!-- Modal Giỏ Hàng Thật -->
<div class="modal fade" id="cartRealModal"  data-bs-backdrop="false" tabindex="-1" aria-labelledby="cartRealModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cartRealModalLabel">Giỏ hàng của bạn</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body" id="cartRealContent">
        <!-- Dữ liệu sản phẩm sẽ được load ở đây -->
        <p class="text-center text-muted">Đang tải...</p>
      </div>
      <div class="modal-footer">
        <h6 class="me-auto fw-bold" id="cartRealTotal">Tổng: 0 đ</h6>
        <a href="cart.php" class="btn btn-dark">Đi đến giỏ hàng</a>
      </div>
    </div>
  </div>
</div>

<!-- danh mục -->
<?php include("menu_danhmuc.php"); ?>

<!-- Đường chỉ dẫn danh mục -->
<div class="container mt-3 mb-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="trangchu.php"><i class="fa-solid fa-house"></i> Trang chủ</a></li>
      <?php foreach ($breadcrumb as $index => $item): ?>
        <?php if ($index < count($breadcrumb) - 1): ?>
          <li class="breadcrumb-item">
            <a href="sanpham.php?dm=<?= $item['DM_MA'] ?>">
              <?= htmlspecialchars($item['DM_TEN']) ?>
            </a>
          </li>
        <?php else: ?>
          <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($item['DM_TEN']) ?></li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ol>
  </nav>
</div>


<div class="product-detail">
  <!-- Bọc slider + thumbnails vào wrapper -->
  <div class="slider-wrapper">

      <!-- Slider -->
      <div class="slider">
        <div class="slides" id="slides">
            <?php foreach ($images as $img) { ?>
              <div class="slide">
                <img class="zoomable" src="<?= $img ?>" alt="Ảnh sản phẩm">
                <div class="zoomLens"></div>
              </div>
            <?php } ?>
        </div>
      </div>

      <!-- Thumbnails bên phải -->
      <div class="thumbnails">
        <?php foreach ($images as $index => $img) { ?>
          <img src="<?= $img ?>" onclick="goToSlide(<?= $index ?>)">
        <?php } ?>
      </div>
  </div>


  <!-- Thông tin sản phẩm -->
  <div class="info">
    <input type="hidden" id="product-id" value="<?= $product['SP_MA'] ?>">
    <h2><?= htmlspecialchars($product['SP_TEN']) ?></h2>
    <?php if (!empty($product['PHAN_TRAM_GIAM']) && $product['PHAN_TRAM_GIAM'] > 0) : 
      $gia_goc = $product['GIA_GOC'];  
      $gia_moi = $product['GIA_HIEN_THI']; 
    ?>
    <p class="price" data-price="<?= $gia_moi ?>">
      <span class="text-decoration-line-through text-muted"><?= number_format($gia_goc,0,',','.') ?> đ</span>
      <span class="text-danger fw-bold ms-2"><?= number_format($gia_moi,0,',','.') ?> đ</span>
    </p>
    <?php else: ?>
    <p class="price" data-price="<?= $product['GIA_GOC'] ?>"><?= number_format($product['GIA_GOC'],0,',','.') ?> đ</p>
    <?php endif; ?>

    <?php
      $tong_ton = 0;
      foreach ($sizes as $s) {
        $tong_ton += $s['CTSP_SOLUONGTON'];
      }
    ?>
    <p class="status">Tình trạng: 
      <span id="stock-status">
        <?= $tong_ton > 0 ? "Còn $tong_ton sản phẩm" : "Hết hàng" ?>
      </span>
    </p>

    <p class="material">Chất Liệu:  <?= htmlspecialchars($product['SP_CHATLIEU']) ?></p>

    <!-- chọn kích thước -->
    <div class="size-selector">
      <label>Chọn kích thước: <span id="selected-size"></span></label>
      <div class="size-buttons">
        <?php foreach ($sizes as $s) { ?>
          <button type="button" 
                  class="size-btn <?= $s['CTSP_SOLUONGTON'] <= 0 ? 'disabled' : '' ?>" 
                  data-size="<?= $s['KT_TEN'] ?>" 
                  data-kt-ma="<?= $s['KT_MA'] ?>"
                  data-stock="<?= $s['CTSP_SOLUONGTON'] ?>"
                  <?= $s['CTSP_SOLUONGTON'] <= 0 ? 'disabled' : '' ?>>
            <?= $s['KT_TEN'] ?>
          </button>
        <?php } ?>
      </div>
    </div>

    <!-- chọn số lượng -->
    <div class="quantity-selector">
      <label for="qty">Số lượng:</label>
      <div class="quantity-box">
        <button type="button" class="qty-btn">−</button>
        <input type="number" id="qty" value="1" min="1" oninput="validateQty()">
        <button type="button" class="qty-btn">+</button>
      </div>
    </div>

    <!-- khối thông tin thêm -->
    <div class="extra-info">
      <p><i class="fa-solid fa-angle-right"></i><a href="thongso.php"> Hướng dẫn chọn size</a></p>
      <p><i class="fa-solid fa-angle-right"></i> Liên hệ tư vấn: <a href="https://www.facebook.com/profile.php?id=61556131574569">m.me/MODÉ.PierreCardin.Official</a></p>
      <p><i class="fa-solid fa-angle-right"></i> Miễn phí vận chuyển cho đơn hàng từ 3.000.000đ</p>
      <p><i class="fa-solid fa-angle-right"></i> Chính sách đổi hàng: <a href="javascript:void(0);" id="btnQuyDinhLink">Quy định đổi hàng</a></p>

      <!-- Modal -->
      <div id="modalQuyDinh" class="modal">
        <div class="modal-content">
          <span class="close">&times;</span>
          <h2>Quy định đổi hàng</h2>
          <p><span class="highlight">Quý Khách có thể đổi hàng trực tiếp tại hệ thống cửa hàng MODÉ trên toàn quốc.</span></p>
          <p><span class="highlight">Áp dụng đối với sản phẩm NGUYÊN GIÁ.</span></p>
          <ol>
            <li>Chỉ chấp nhận đổi các sản phẩm <span class="highlight">chưa sử dụng</span> và còn <span class="highlight">hóa đơn mua</span> tại hệ thống cửa hàng.</li>
            <li>Sản phẩm <span class="highlight">đã sửa chữa, giảm giá, hoặc đã qua sử dụng</span> không được đổi.</li>
            <li>Khi đổi sản phẩm mới, <span class="highlight">không hoàn tiền dư</span> nếu Quý Khách chọn sản phẩm có giá thấp hơn.</li>
            <li>Khi đổi hàng, vui lòng đính kèm <span class="highlight">“phiếu đổi hàng” còn hiệu lực</span>.</li>
            <li>Thời gian đổi sản phẩm nguyên giá chưa sử dụng tại cửa hàng:
              <ul>
                <li><span class="highlight">30 ngày</span>: Áo, Quần</li>
                <li><span class="highlight">07 ngày</span>: Giày, Cao gót, Áo khoác</li>
                <li><span class="highlight">14 ngày</span>: Đầm, Váy, Vecton, Đồ thể thao</li>
              </ul>
            </li>
            <li>Chính sách <span class="highlight">trả hàng, hoàn tiền</span> không áp dụng.</li>
            <li><span class="highlight">Voucher hết hạn</span> không áp dụng đổi sản phẩm.</li>
            <li>Đối với <span class="highlight">mua hàng trực tuyến</span>, nếu không thể mang đến cửa hàng gần nhất, Quý Khách có thể gửi trực tiếp cho công ty theo địa chỉ ghi trên đơn hàng. <span class="highlight">Chi phí vận chuyển (đi và về) do Quý Khách thanh toán.</span></li>
          </ol>
        </div>
      </div>
    </div>

    <!-- nút hành động -->
    <div class="buttons">
      <button class="add-to-cart"><i class="fa-solid fa-cart-shopping icon-cart"></i> Thêm vào giỏ</button>
      <button class="btn btn-primary buy-now"><i class="fas fa-bolt"></i> Mua ngay</button>
    </div>
  </div>
</div>

<!-- Mô tả -->
<div class="commitment-wrapper">
  <h4>MODÉ CAM KẾT</h4>
  <div class="commitment">
    <div class="commit-item">
      <div class="icon">✔️</div>
      <p>Cam kết sản phẩm đúng mô tả, chất liệu cao cấp.</p>
    </div>
    <div class="commit-item">
      <div class="icon">🚚</div>
      <p>Giao trong 3-5 ngày và freeship đơn từ 3.000.000k</p>
    </div>
    <div class="commit-item">
      <div class="icon">↩️</div>
      <p>Hỗ trợ đổi trả trong 7 ngày nếu sản phẩm lỗi.</p>
    </div>
    <div class="commit-item">
      <div class="icon">❓</div>
      <p>Đội ngũ tư vấn tận tâm, giải đáp nhanh chóng</p>
    </div>
  </div>
</div>

<div class="product-tabs">
  <ul class="tab-header">
    <li class="active" data-tab="tab1">THÔNG TIN SẢN PHẨM</li>
    <li data-tab="tab2">BẢO QUẢN</li>
    <li data-tab="tab3">ĐÁNH GIÁ</li>
  </ul>

  <div class="tab-content active" id="tab1">
    <p><strong>Chất liệu:</strong> <?= htmlspecialchars($product['SP_CHATLIEU']) ?></p>
    <p><strong>Màu sắc:</strong> <?= htmlspecialchars($product['SP_TEN']) ?> (*Hình ảnh chỉ mang tính chất minh họa, màu sắc sản phẩm thực tế có thể thay đổi tùy thuộc vào điều kiện sáng và thiết bị hiển thị)</p>
    <p>
      <strong>Mô tả:</strong>
      <?php 
        $parts = explode('|', $product['SP_MOTA']);
        echo htmlspecialchars(implode(' ', $parts)); 
      ?>
    </p>
    <p><strong>Lưu ý:</strong> Bảng thông số chọn size mang tính chất tham khảo. Có thể sai số do kích thước cơ thể khác nhau.</p>
  </div>

  <div class="tab-content" id="tab2">
    <p><strong><i class="fa-solid fa-shirt"></i> Hướng dẫn bảo quản:</strong></p>
    <ul class="care-list">
      <li><i class="fa-solid fa-temperature-low"></i> Giặt bằng nước ở nhiệt độ dưới 30℃ để giúp sợi vải giữ được độ bền và màu sắc tươi mới lâu hơn. Nên sử dụng chế độ giặt nhẹ hoặc giặt tay để đảm bảo độ mềm mại tự nhiên của sản phẩm.</li>
      
      <li><i class="fa-solid fa-sun"></i> Phơi sản phẩm ở nơi có ánh sáng tự nhiên nhẹ, thoáng gió. Tránh đặt dưới ánh nắng trực tiếp trong thời gian dài để hạn chế tình trạng bạc màu hoặc co rút sợi vải.</li>
      
      <li><i class="fa-solid fa-shirt"></i> Không giặt chung với các sản phẩm khác màu, đặc biệt là đồ trắng và đồ đậm màu, để tránh hiện tượng lem màu không mong muốn.</li>
      
      <li><i class="fa-solid fa-soap"></i> Không nên sử dụng các loại chất giặt tẩy mạnh hoặc có chứa thuốc tẩy. Hãy ưu tiên sử dụng bột giặt dịu nhẹ hoặc dung dịch chuyên dụng cho vải cao cấp.</li>
      
      <li><i class="fa-solid fa-ban"></i> Không phơi trực tiếp dưới ánh nắng mặt trời gắt. Tốt nhất nên phơi ở nơi râm mát hoặc trong bóng râm để giữ độ bền của màu vải và hạn chế co rút.</li>
      
      <li><i class="fa-solid fa-tag"></i> Luôn đọc kỹ hướng dẫn trên nhãn (tag) sản phẩm trước khi giặt. Mỗi chất liệu sẽ có yêu cầu bảo quản riêng như nhiệt độ, cách sấy, hoặc ủi phù hợp để giữ dáng và màu lâu bền nhất.</li>
    </ul>
  </div>

  <div class="tab-content" id="tab3">
    <p><strong>Đánh giá của khách hàng:</strong></p>
    <div class="reviews-list">
      <?php
      if($result_danh_gia->num_rows > 0){
          while($row_dg = $result_danh_gia->fetch_assoc()){
              $so_sao = (int)$row_dg['PH_SOSAO'];
              if($so_sao >= 4) {
                  $emoji = "😄";
                  $emojiClass = "happy";
              } elseif($so_sao == 3) {
                  $emoji = "😐";
                  $emojiClass = "neutral";
              } else {
                  $emoji = "😢";
                  $emojiClass = "sad";
              }

              echo '<div class="review-card">';
              echo '<div class="review-avatar '.$emojiClass.'">'.$emoji.'</div>';
              echo '<div class="review-content">';
              
              // Cột trái: tên + sao
              echo '<div class="review-left">';
              echo '<p class="review-name">' . htmlspecialchars($row_dg['ND_HOTEN']) . '</p>';
              echo '<p class="review-star">' . str_repeat('⭐', $so_sao) . '</p>';
              echo '</div>';
              
              // Cột phải: nội dung + ngày
              echo '<div class="review-right">';
              echo '<p class="review-text">' . htmlspecialchars($row_dg['PH_NOIDUNG']) . '</p>';
              echo '<p class="review-date">' . date('d/m/Y H:i', strtotime($row_dg['PH_NGAYGIO'])) . '</p>';
              echo '</div>';

              echo '</div></div>';
          }
      } else {
          echo '<p>Chưa có đánh giá. Bạn có thể mua sản phẩm để tiến hành đánh giá.</p>';
      }
      ?>
    </div>
  </div>
</div>


<!-- Sản phẩm tương tự -->
<div class="products-same">
  <h4>SẢN PHẨM CÙNG NHÓM</h4>
  <div class="featured-products">
    <?php while($row = $result_tuong_tu->fetch_assoc()) { ?>
      <div class="product-card">
        <div class="product-img">
          <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
            <img src="<?= htmlspecialchars($row['Anh1']) ?>" class="img-main">
            <img src="<?= htmlspecialchars($row['Anh2']) ?>" class="img-hover">
          </a>
          <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
            <i class="fas fa-shopping-cart"></i>
          </a>
        </div>
        <div class="product-info">
          <h5>
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
              <?= htmlspecialchars($row['SP_TEN']) ?>
            </a>
          </h5>
          <p>
            <?php if (!empty($row['PHAN_TRAM_GIAM']) && $row['PHAN_TRAM_GIAM'] > 0): ?>
                <span class="price-old"><?= number_format($row['GIA_GOC'], 0, ',', '.') ?> đ</span>
                <span class="price-sale"><?= number_format($row['GIA_HIEN_THI'], 0, ',', '.') ?> đ</span>
            <?php else: ?>
                <span><?= number_format($row['GIA_HIEN_THI'], 0, ',', '.') ?> đ</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
    <?php } ?>
  </div>
</div>


  <!-- Nút xem thêm sản phẩm -->
  <?php
    // Lấy mã danh mục cha của danh mục hiện tại
    $sql_parent = "SELECT DM_CHA FROM DANH_MUC WHERE DM_MA = $dm_ma LIMIT 1";
    $result_parent = $conn->query($sql_parent);
    $dm_xem_them = $dm_ma; // mặc định là DM hiện tại

    if($result_parent && $row = $result_parent->fetch_assoc()){
        if(!empty($row['DM_CHA'])){
            $dm_xem_them = $row['DM_CHA']; // nếu có cha thì lấy cha
        }
    }
  ?>
  <div class="see-more-container">
    <a href="../Mode/sanpham.php?dm=<?= $dm_xem_them ?>" class="see-more-btn">
       Xem thêm sản phẩm
    </a>
  </div>

  <!-- Xem nhanh chi tiết sản phẩm -->
  <div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Thêm nhanh vào giỏ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
        <div id="quickViewContent">
          <!-- Cột ảnh -->
          <div class="qv-left">
            <img id="qv-image" src="" class="img-fluid rounded" alt="Ảnh sản phẩm">
          </div>
          <!-- Cột thông tin -->
          <div class="qv-right">
            <h4 id="qv-name"></h4>
            <p id="qv-price" class="fw-bold text-danger fs-5"></p>
            <p id="qv-material"></p>

            <div id="qv-sizes" class="mb-3">
              <label class="fw-semibold">Kích thước:</label>
              <div id="qv-size-buttons" class="d-flex flex-wrap gap-2 mt-1"></div>
            </div>

            <div class="mb-3">
              <label class="fw-semibold">Số lượng:</label>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="qv-minus">−</button>
                <input type="number" id="qv-qty" value="1" min="1" class="form-control form-control-sm text-center" readonly>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="qv-plus">+</button>
              </div>
            </div>

            <button id="qv-add-cart" class="btn btn-primary w-100">
              <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

  <!-- Sản phẩm đã xem gần đây -->
  <?php if (!empty($recent_viewed_products) && $recent_viewed_products->num_rows > 0): ?>
    <div class="recent-views">
      <h4>SẢN PHẨM BẠN ĐÃ XEM</h4>
      <div class="featured-products">
        <?php while ($row = $recent_viewed_products->fetch_assoc()) { 
          $gia_hien_thi = !empty($row['CTKM_PHANTRAM_GIAM']) && $row['CTKM_PHANTRAM_GIAM'] > 0 
                          ? round($row['GIA_MOI'] * (100 - $row['CTKM_PHANTRAM_GIAM']) / 100, 0) 
                          : $row['GIA_MOI'];
        ?>
        <div class="product-card">
          <div class="product-img">
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
              <img src="<?= htmlspecialchars($row['Anh1']) ?>" class="img-main">
              <img src="<?= htmlspecialchars($row['Anh2']) ?>" class="img-hover">
            </a>
            <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
              <i class="fas fa-shopping-cart"></i>
            </a>
          </div>
          <div class="product-info">
            <h5><a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>"><?= htmlspecialchars($row['SP_TEN']) ?></a></h5>
            <p>
              <?php if (!empty($row['CTKM_PHANTRAM_GIAM']) && $row['CTKM_PHANTRAM_GIAM'] > 0): ?>
                  <span class="price-old"><?= number_format($row['GIA_MOI'], 0, ',', '.') ?> đ</span>
                  <span class="price-sale"><?= number_format($gia_hien_thi, 0, ',', '.') ?> đ</span>
              <?php else: ?>
                  <span><?= number_format($row['GIA_MOI'], 0, ',', '.') ?> đ</span>
              <?php endif; ?>
            </p>
          </div>
        </div>
        <?php } ?>
      </div>
    </div>
  <?php endif; ?>


  <!-- Chân trang -->
<footer id="footer">
    <div class="container footer-container">
      <div class="footer-left">
        <h3 class="footer-brand">MODÉ</h3>
        <p>Thời trang tinh tế - Tự tin khẳng định phong cách của bạn.  
          MODÉ luôn hướng đến sự hoàn hảo trong từng chi tiết.</p>
        <p><i class="fa-solid fa-location-dot"></i> 12 Đ. Nguyễn Đình Chiểu, Tân An, Ninh Kiều, Cần Thơ, Việt Nam</p>
        <p><i class="fa-solid fa-phone"></i> 0765 958 481</p>
        <p><i class="fa-solid fa-envelope"></i> iuidolofyou@gmail.com</p>
      </div>

      <div class="footer-center">
        <h4>Liên kết nhanh</h4>
        <ul>
          <li><a href="trangchu.php">Trang chủ</a></li>
          <li><a href="gioithieu.php">Giới thiệu</a></li>
          <li><a href="sanpham.php?dm=1">Thời trang nam</a></li>
          <li><a href="sanpham.php?dm=2">Thời trang nữ</a></li>
          <li><a href="voucher.php">Ưu Đãi</a></li>
          <li><a href="lienhe.php">Liên hệ</a></li>
        </ul>
      </div>

      <div class="footer-right">
        <h4>Kết nối với MODÉ</h4>
        <div class="socials-list">
          <a href="https://www.facebook.com/profile.php?id=61556131574569"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://www.instagram.com/"><i class="fa-brands fa-instagram"></i></a>
          <a href="https://www.youtube.com/"><i class="fa-brands fa-youtube"></i></a>
          <a href="https://www.pinterest.com/"><i class="fa-brands fa-pinterest-p"></i></a>
          <a href="https://x.com/"><i class="fa-brands fa-x-twitter"></i></a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© 2025 <strong>MODÉ</strong>. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let slideIndex = 0;
  const slides = document.getElementById("slides");
  const totalSlides = slides.children.length;
  let maxQty = 1;
  let autoSlide = null;

  function startAutoSlide() {
    stopAutoSlide(); // luôn clear trước để tránh nhiều interval cùng chạy
    autoSlide = setInterval(() => {
      slideIndex = (slideIndex + 1) % totalSlides;
      goToSlide(slideIndex);
    }, 5000);
  }


  function stopAutoSlide() {
    if (autoSlide) {
      clearInterval(autoSlide);
      autoSlide = null;
    }
  }

  document.addEventListener("DOMContentLoaded", function() {
    goToSlide(0); // đánh dấu thumbnail đầu tiên active
    startAutoSlide();
  });


  const thumbnails = document.querySelectorAll(".thumbnails img"); 

  function goToSlide(index) {
    slideIndex = index;
    slides.style.transform = `translateX(${-slideIndex * 100}%)`;

    // Cập nhật thumbnail active
    thumbnails.forEach((thumb, i) => {
      thumb.classList.toggle("active", i === index);
    });

    // Nếu muốn click thumbnail vẫn tự chạy tiếp
    stopAutoSlide(); 
    startAutoSlide(); 
  }


 // Hiệu ứng zoom trực tiếp trên ảnh sản phẩm (inline zoom)
  const zoomImages = document.querySelectorAll('.slider img');

  zoomImages.forEach(img => {
      img.style.transition = 'transform 0.4s ease-out';
      img.style.cursor = 'zoom-in';

      img.addEventListener('mouseenter', function() {
          stopAutoSlide(); // Dừng chuyển slide khi hover
      });

      img.addEventListener('mousemove', function (e) {
          const rect = img.getBoundingClientRect();
          const x = ((e.clientX - rect.left) / rect.width) * 100;
          const y = ((e.clientY - rect.top) / rect.height) * 100;
          img.style.transformOrigin = `${x}% ${y}%`;
          img.style.transform = 'scale(2.0)';
          img.style.cursor = 'zoom-out';
      });

      img.addEventListener('mouseleave', function () {
          img.style.transformOrigin = 'center center';
          img.style.transform = 'scale(1)';
          img.style.cursor = 'zoom-in';
          startAutoSlide(); // chạy lại slide khi rời chuột
      });
  });


  // Khi chọn size
  const sizeBtns = document.querySelectorAll(".size-btn");
  const qtyInput = document.getElementById("qty");
  const minusBtn = document.querySelectorAll(".qty-btn")[0];
  const plusBtn = document.querySelectorAll(".qty-btn")[1];
  const stockStatus = document.getElementById("stock-status");

  sizeBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      if (btn.classList.contains("disabled")) return;

      // Bỏ active ở size khác
      sizeBtns.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");

      // Lấy tồn kho của size được chọn
      maxQty = parseInt(btn.dataset.stock);

      // Chỉ đặt lại về 1 nếu số lượng hiện tại vượt quá tồn kho
      if (parseInt(qtyInput.value) > maxQty) {
        qtyInput.value = maxQty;
      } else if (!qtyInput.value || parseInt(qtyInput.value) < 1) {
        qtyInput.value = 1;
      }

      // Cập nhật tình trạng tồn kho
      if (maxQty > 0) {
        stockStatus.textContent = `Còn ${maxQty} sản phẩm`;
      } else {
        stockStatus.textContent = "Hết hàng";
      }

      // Kích hoạt nút cộng/trừ
      minusBtn.disabled = false;
      plusBtn.disabled = false;
    });
  });


  // Sự kiện cộng số lượng
  plusBtn.addEventListener("click", () => {
    let val = parseInt(qtyInput.value);
    if (val < maxQty) qtyInput.value = val + 1;
  });

  // Sự kiện trừ số lượng
  minusBtn.addEventListener("click", () => {
    let val = parseInt(qtyInput.value);
    if (val > 1) qtyInput.value = val - 1;
  });

  // Chuyển đổi giữa các tab
  const tabs = document.querySelectorAll(".tab-header li");
  const contents = document.querySelectorAll(".tab-content");

  tabs.forEach(tab => {
    tab.addEventListener("click", () => {
      tabs.forEach(t => t.classList.remove("active"));
      contents.forEach(c => c.classList.remove("active"));

      tab.classList.add("active");
      document.getElementById(tab.dataset.tab).classList.add("active");
    });
  });
 
  // mở danh mục
  document.addEventListener("DOMContentLoaded", function() {
  const btnDanhMuc = document.getElementById("btn-danhmuc");
  const menuDanhMuc = document.getElementById("menu-danhmuc");
  const overlay = document.getElementById("menu-overlay");

  btnDanhMuc.addEventListener("click", function(e) {
    e.preventDefault();
    menuDanhMuc.classList.toggle("active");
    overlay.classList.toggle("active");
  });

  // Bấm overlay để đóng
  overlay.addEventListener("click", function() {
    menuDanhMuc.classList.remove("active");
    overlay.classList.remove("active");
  });
});

  // Quy định đổi hàng
  const modal = document.getElementById("modalQuyDinh");
  const btnLink = document.getElementById("btnQuyDinhLink");
  const span = document.getElementsByClassName("close")[0];

  btnLink.onclick = () => modal.style.display = "block";
  span.onclick = () => modal.style.display = "none";
  window.onclick = (event) => {
    if (event.target == modal) modal.style.display = "none";
  };
  

  // Mở đóng xem nhanh giỏ hàng và xử lý dữ liệu
// Tạo 1 modal instance duy nhất
const quickViewModal = new bootstrap.Modal(document.getElementById('quickViewModal'), {
  backdrop: true,
  keyboard: true
});

// Mở modal và load dữ liệu
function openQuickView(spMa) {
  fetch(`xemnhanh.php?sp=${spMa}`)
    .then(res => res.json())
    .then(data => {
      const imgEl = document.getElementById('qv-image');
      imgEl.src = data.images[0] || '../assets/images/logo.png';
      imgEl.dataset.spMa = spMa; // Gán spMa để dùng khi add to cart

      const priceEl = document.getElementById('qv-price');
      priceEl.dataset.price = parseFloat(data.gia);  // giá số nguyên
      priceEl.innerHTML = data.gia_text;             // hiển thị HTML

      document.getElementById('qv-name').textContent = data.ten;
      document.getElementById('qv-material').textContent = "Chất liệu: " + data.chatlieu;

      // Xử lý size như bình thường
      const sizeContainer = document.getElementById('qv-size-buttons');
      sizeContainer.innerHTML = '';
      data.sizes.forEach(s => {
        const btn = document.createElement('button');
        btn.textContent = s.ten;
        btn.className = 'btn btn-outline-dark btn-sm';
        btn.disabled = s.ton <= 0;
        // Gán id KT_MA vào data của button
        btn.dataset.ktMa = s.KT_MA; 
        btn.onclick = () => {
          document.querySelectorAll('#qv-size-buttons button').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          document.getElementById('qv-qty').max = s.ton;
          document.getElementById('qv-qty').value = 1;
        };
        sizeContainer.appendChild(btn);
      });

      const qtyInput = document.getElementById('qv-qty');
      qtyInput.value = 1;
      qtyInput.max = Math.max(...data.sizes.map(s => s.ton), 1);

      quickViewModal.show();
    })
    .catch(err => console.error("Lỗi khi load sản phẩm:", err));
}

// Gắn sự kiện cho icon giỏ hàng
document.querySelectorAll('.cart-overlay').forEach(icon => {
  icon.addEventListener('click', function(e) {
    e.preventDefault();
    const spMa = this.getAttribute('href').split('=')[1];
    openQuickView(spMa);
  });
});

// Tăng giảm số lượng
document.getElementById('qv-minus').onclick = () => {
  const qty = document.getElementById('qv-qty');
  if (qty.value > 1) qty.value--;
};
document.getElementById('qv-plus').onclick = () => {
  const qty = document.getElementById('qv-qty');
  const max = parseInt(qty.max) || 1000;
  if (parseInt(qty.value) < max) qty.value = parseInt(qty.value) + 1;
};

// Thêm vào giỏ
document.getElementById('qv-add-cart').onclick = () => {
  const selectedSizeBtn = document.querySelector('#qv-size-buttons button.active');
  if (!selectedSizeBtn) {
    alert("Vui lòng chọn kích thước!");
    return;
  }

  const spMa = document.getElementById('qv-image').dataset.spMa;
  const spTen = document.getElementById('qv-name').textContent;
  const img = document.getElementById('qv-image').src;
  const ktMa = selectedSizeBtn.dataset.ktMa;
  const qty = parseInt(document.getElementById('qv-qty').value);
  const price = parseFloat(document.getElementById('qv-price').dataset.price);

  const ndMa = document.body.dataset.ndMa || null; // ND_MA nếu đã login

  if (ndMa) {
    fetch('add_to_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ND_MA: ndMa, SP_MA: spMa, KT_MA: ktMa, qty, price })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Đã thêm vào giỏ hàng.');
        updateCartCount(); 
      }
    })
    .catch(err => console.error('Lỗi thêm giỏ hàng:', err));
  } else {
    // lưu tạm vào localStorage
    const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    const existIndex = cartTemp.findIndex(item => item.SP_MA == spMa && item.KT_MA == ktMa);
    if (existIndex > -1) {
      cartTemp[existIndex].qty += qty;
    } else {
      const ktTen = selectedSizeBtn.textContent; // lấy tên size từ nút đang chọn
      cartTemp.push({ 
        SP_MA: spMa, 
        SP_TEN: spTen, 
        KT_MA: ktMa, 
        KT_TEN: ktTen, // thêm tên size
        qty, 
        price, 
        img,
        maxQty
      });
    }
    localStorage.setItem('cartTemp', JSON.stringify(cartTemp));
    updateCartCount();
    alert('Đã thêm vào giỏ tạm.');
  }

  quickViewModal.hide();
};


// Merge giỏ tạm khi login xong
function mergeCartTemp(ndMa) {
  const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
  if (cartTemp.length === 0) return;

  fetch('merge_cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ND_MA: ndMa, cart: cartTemp })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) localStorage.removeItem('cartTemp');
  });
}

// Khởi tạo modal
const cartTempModal = new bootstrap.Modal(document.getElementById('cartTempModal'));

// Hiển thị giỏ tạm
function showCartTemp() {
  const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
  const container = document.getElementById('cartTempContent');
  const totalEl = document.getElementById('cartTempTotal');

  if (cartTemp.length === 0) {
    container.innerHTML = '<p>Giỏ tạm trống.</p>';
    totalEl.textContent = '';
    cartTempModal.show();
    return;
  }

  let total = 0;
  let html = '<table class="table align-middle"><thead><tr><th>Ảnh</th><th>Sản phẩm</th><th>Size</th><th>Số lượng</th><th>Giá</th><th>Hành động</th></tr></thead><tbody>';

  cartTemp.forEach((item, index) => {
    const priceDisplay = item.price ? item.price.toLocaleString() + ' đ' : 'Chưa có giá';
    const subtotal = item.price ? item.price * item.qty : 0;
    total += subtotal;

    html += `<tr>
      <td><img src="${item.img || '../assets/images/logo.png'}" width="50"></td>
      <td>${item.SP_TEN || 'Sản phẩm #' + item.SP_MA}</td>
      <td>${item.KT_TEN}</td>
      <td>${item.qty}</td>
      <td>${priceDisplay}</td>
      <td><button class="btn btn-sm btn-danger" onclick="removeCartTemp(${index})">Xóa</button></td>
    </tr>`;
  });

  html += '</tbody></table>';
  container.innerHTML = html;
  totalEl.textContent = 'Tổng: ' + total.toLocaleString() + ' đ';

  cartTempModal.show();
}

// Xóa 1 sản phẩm khỏi giỏ tạm
function removeCartTemp(index) {
  const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
  cartTemp.splice(index, 1);
  localStorage.setItem('cartTemp', JSON.stringify(cartTemp));
  showCartTemp(); // cập nhật lại hiển thị
  updateCartCount();
}

// Gắn sự kiện cho icon giỏ hàng
document.querySelectorAll('.cart-icon').forEach(icon => {
  icon.addEventListener('click', function(e) {
    e.preventDefault();
    const ndMa = document.body.dataset.ndMa || null;
    if (!ndMa) {
      showCartTemp(); // chưa login thì mở giỏ tạm
    } else {
      openCart(); // login rồi thì mở giỏ chính
    }
  });
});

// --- Hàm cập nhật số lượng hiển thị trên icon giỏ hàng ---
function updateCartCount() {
  const countEl = document.getElementById('cart-count');
  const ndMa = document.body.dataset.ndMa || null;

  // Nếu chưa đăng nhập → đếm giỏ tạm trong localStorage
  if (!ndMa) {
    const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    // Dùng đúng key 'qty'
    const totalQty = cartTemp.reduce((sum, item) => sum + (item.qty || 0), 0);
    if (countEl) countEl.textContent = totalQty;
    return;
  }

  // Nếu đã đăng nhập → lấy tổng số lượng từ CSDL
  fetch('get_cart.php')
    .then(res => res.json())
    .then(data => {
      if (countEl) {
        if (data && Array.isArray(data.items)) {
          // Dùng đúng key 'qty'
          const totalQty = data.items.reduce((sum, item) => sum + (item.qty || 0), 0);
          countEl.textContent = totalQty;
        } else {
          countEl.textContent = '0';
        }
      }
    })
    .catch(() => {
      if (countEl) countEl.textContent = '0';
    });
}

// Gọi lại khi load trang
document.addEventListener('DOMContentLoaded', updateCartCount);

// Giỏ hàng khi người dùng đã đăng nhập
function openCart() {
  const modal = new bootstrap.Modal(document.getElementById('cartRealModal'));
  const content = document.getElementById('cartRealContent');
  const totalEl = document.getElementById('cartRealTotal');

  content.innerHTML = `<p class="text-center text-muted">Đang tải...</p>`;
  totalEl.textContent = "Tổng: 0 đ";

  fetch("get_cart.php")
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        content.innerHTML = `<p class="text-danger text-center">${data.error}</p>`;
        return;
      }

      if (data.items.length === 0) {
        content.innerHTML = `<p class="text-center text-muted">Giỏ hàng của bạn đang trống.</p>`;
        totalEl.textContent = '';
        return;
      }

      // Giống giao diện giỏ tạm
      let html = `
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Ảnh</th>
              <th>Sản phẩm</th>
              <th>Size</th>
              <th>Số lượng</th>
              <th>Giá</th>
              <th>Thành tiền</th>
            </tr>
          </thead>
          <tbody>
      `;

      let total = 0;

      data.items.forEach((item) => {
        const subtotal = item.qty * item.price;
        total += subtotal;

        html += `
          <tr>
            <td><img src="${item.SP_ANH || '../assets/images/logo.png'}" width="50" class="rounded"></td>
            <td>${item.SP_TEN}</td>
            <td>${item.KT_TEN}</td>
            <td>${item.qty}</td>
            <td>${item.price.toLocaleString()} đ</td>
            <td>${subtotal.toLocaleString()} đ</td>
          </tr>
        `;
      });

      html += `</tbody></table>`;

      content.innerHTML = html;
      totalEl.textContent = 'Tổng: ' + total.toLocaleString() + ' đ';
    })
    .catch(() => {
      content.innerHTML = `<p class="text-danger text-center">Lỗi tải giỏ hàng.</p>`;
    });

  modal.show();
}

// Nút thêm vào giỏ
const detailAddToCartBtn = document.querySelector(".add-to-cart");

detailAddToCartBtn.addEventListener("click", () => {
  const productId = document.getElementById("product-id").value;
  const selectedSizeBtn = document.querySelector(".size-btn.active");
  if (!selectedSizeBtn) {
    alert("Vui lòng chọn kích thước!");
    return;
  }

  const qty = parseInt(document.getElementById("qty").value) || 1;
  const stock = parseInt(selectedSizeBtn.dataset.stock);

  if (qty > stock) {
    alert(`Chỉ còn ${stock} sản phẩm cho size ${selectedSizeBtn.dataset.size}`);
    return;
  }

  const sizeName = selectedSizeBtn.dataset.size;
  const ndMa = document.body.dataset.ndMa || null; // lấy ND_MA nếu login

  if (ndMa) {
    // Thêm vào giỏ thật
    fetch('add_to_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ND_MA: ndMa,
        SP_MA: productId,
        KT_MA: selectedSizeBtn.dataset.ktMa || sizeName,
        qty,
        price: parseFloat(document.querySelector(".price").dataset.price)
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Đã thêm vào giỏ hàng.");
        updateCartCount();
        mergeCartTemp(ndMa); // Gộp giỏ tạm sau khi thêm vào giỏ thật
      } else {
        alert(data.message || "Thêm giỏ hàng thất bại");
      }
    })
    .catch(err => console.error(err));
  } else {
    // Giỏ tạm localStorage
    const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    const existIndex = cartTemp.findIndex(item => item.SP_MA == productId && item.KT_TEN == sizeName);

    const img = document.querySelector(".slider .slide img")?.src || "../assets/images/logo.png";
    const spTen = document.querySelector(".info h2").textContent;
    const price = parseFloat(document.querySelector(".price").dataset.price);

    if (existIndex > -1) {
      cartTemp[existIndex].qty += qty;
    } else {
      cartTemp.push({
        SP_MA: productId,
        SP_TEN: spTen,
        KT_TEN: sizeName,
        qty,
        price,
        img,
        maxQty: stock
      });
    }

    localStorage.setItem('cartTemp', JSON.stringify(cartTemp));
    updateCartCount();
    alert("Đã thêm vào giỏ tạm.");
  }
});

// Nút mua ngay
const buyNowBtn = document.querySelector(".buy-now");

buyNowBtn.addEventListener("click", () => {
  const ndMa = document.body.dataset.ndMa || null; // ND_MA nếu login
  if (!ndMa) {
    alert("Vui lòng đăng nhập để mua sản phẩm!");
    return;
  }

  const productId = document.getElementById("product-id").value;
  const selectedSizeBtn = document.querySelector(".size-btn.active");
  if (!selectedSizeBtn) {
    alert("Vui lòng chọn kích thước!");
    return;
  }

  const qty = parseInt(document.getElementById("qty").value) || 1;
  const stock = parseInt(selectedSizeBtn.dataset.stock);

  if (qty > stock) {
    alert(`Chỉ còn ${stock} sản phẩm cho size ${selectedSizeBtn.dataset.size}`);
    return;
  }

  const sizeName = selectedSizeBtn.dataset.size;

  // Gửi AJAX để thêm vào giỏ hàng
  fetch('add_to_cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      ND_MA: ndMa,
      SP_MA: productId,
      KT_MA: selectedSizeBtn.dataset.ktMa || sizeName,
      qty,
      price: parseFloat(document.querySelector(".price").dataset.price)
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Chuyển thẳng sang trang thanh toán
      window.location.href = "thanhtoan.php";
    } else {
      alert(data.message || "Thêm giỏ hàng thất bại");
    }
  })
  .catch(err => console.error(err));
});

</script>
<!-- Overlay mờ khi menu mở -->
<div id="menu-overlay"></div>
</body>
</html>
