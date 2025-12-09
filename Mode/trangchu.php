<?php
// Kết nối CSDL
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

/**
 * Hàm đệ quy hiển thị danh mục con
 */
function getChildren($parent_id, $conn) {
    $sql = "SELECT DM_MA, DM_TEN FROM DANH_MUC WHERE DM_CHA = $parent_id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo '<ul class="dropdown-menu">';
        while ($row = $result->fetch_assoc()) {
            $name = htmlspecialchars($row['DM_TEN'], ENT_QUOTES, 'UTF-8');
            $id = (int)$row['DM_MA'];
            echo '<li class="dropdown-submenu">';
            echo '<a class="dropdown-item dropdown-toggle" href="sanpham.php?dm=' . $id . '">' . $name . '</a>';
            getChildren($row['DM_MA'], $conn);
            echo '</li>';
        }
        echo '</ul>';
    }
}

// Lấy menu cấp 1
$sqlTop = "SELECT DM_MA, DM_TEN FROM DANH_MUC WHERE DM_CHA IS NULL ORDER BY DM_MA";
$topResult = $conn->query($sqlTop);

// SQL lấy sản phẩm nổi bật
$sqlProduct = "WITH RECURSIVE dm_tree AS (
  SELECT DM_MA, DM_CHA, DM_TEN, DM_MA AS ROOT_ID
  FROM danh_muc
  WHERE DM_MA IN (1, 2)
  UNION ALL
  SELECT d.DM_MA, d.DM_CHA, d.DM_TEN, t.ROOT_ID
  FROM danh_muc d
  JOIN dm_tree t ON d.DM_CHA = t.DM_MA
),
sp_cte AS (
  SELECT 
      sp.SP_MA,
      sp.SP_TEN,
      t.ROOT_ID AS DM_CHA,
      ROW_NUMBER() OVER (PARTITION BY t.ROOT_ID ORDER BY sp.SP_MA ASC) AS rn,
      (
          SELECT g.DONGIA
          FROM don_gia_ban g
          JOIN thoi_diem td ON g.TD_THOIDIEM = td.TD_THOIDIEM
          WHERE g.SP_MA = sp.SP_MA
          ORDER BY td.TD_THOIDIEM DESC
          LIMIT 1
      ) AS GIA_GOC,
      (
          SELECT ctkm.CTKM_PHANTRAM_GIAM
          FROM chi_tiet_khuyen_mai ctkm
          JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
          WHERE ctkm.SP_MA = sp.SP_MA
            AND k.KM_CONSUDUNG = 1
            AND CURDATE() BETWEEN k.KM_NGAYBATDAU AND k.KM_NGAYKETTHUC
          ORDER BY k.KM_NGAYBATDAU DESC
          LIMIT 1
      ) AS PHAN_TRAM_GIAM
  FROM san_pham sp
  JOIN dm_tree t ON sp.DM_MA = t.DM_MA
  WHERE sp.SP_CONSUDUNG = 1
),
sp_top AS (
  SELECT *,
      ROUND(GIA_GOC * (100 - COALESCE(PHAN_TRAM_GIAM, 0)) / 100, 0) AS GIA_HIEN_THI
  FROM sp_cte
  WHERE rn <= 4
),
anh_cte AS (
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
)
SELECT 
  s.SP_MA,
  s.SP_TEN,
  s.GIA_GOC,
  s.PHAN_TRAM_GIAM,
  s.GIA_HIEN_THI,
  s.DM_CHA,
  a.Anh1,
  a.Anh2
FROM sp_top s
LEFT JOIN anh_cte a ON s.SP_MA = a.SP_MA
ORDER BY s.DM_CHA, s.SP_MA;
";

$result = $conn->query($sqlProduct);


// SQL lấy sản phẩm mới nhất
$sqlNewProduct = "WITH anh_cte AS (
  -- Lấy 2 ảnh đầu mỗi sản phẩm
  SELECT
      sp_mas.SP_MA,
      MAX(CASE WHEN rn = 1 THEN sp_mas.ANH_DUONGDAN END) AS Anh1,
      MAX(CASE WHEN rn = 2 THEN sp_mas.ANH_DUONGDAN END) AS Anh2
  FROM (
      SELECT 
          a.SP_MA,
          a.ANH_DUONGDAN,
          ROW_NUMBER() OVER (PARTITION BY a.SP_MA ORDER BY a.ANH_MA ASC) AS rn
      FROM anh_san_pham a
  ) sp_mas
  GROUP BY sp_mas.SP_MA
),
gia_cte AS (
  -- Lấy giá gốc mới nhất cho từng sản phẩm
  SELECT g.SP_MA, g.DONGIA AS GIA_GOC
  FROM don_gia_ban g
  JOIN thoi_diem td ON g.TD_THOIDIEM = td.TD_THOIDIEM
  WHERE (g.SP_MA, td.TD_THOIDIEM) IN (
      SELECT SP_MA, MAX(td.TD_THOIDIEM)
      FROM don_gia_ban
      JOIN thoi_diem td2 ON don_gia_ban.TD_THOIDIEM = td2.TD_THOIDIEM
      GROUP BY SP_MA
  )
),
km_cte AS (
  -- Lấy khuyến mãi hiện tại của từng sản phẩm (nếu có)
  SELECT 
      ctkm.SP_MA,
      ctkm.CTKM_PHANTRAM_GIAM
  FROM chi_tiet_khuyen_mai ctkm
  JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
  WHERE k.KM_CONSUDUNG = 1
    AND CURDATE() BETWEEN k.KM_NGAYBATDAU AND k.KM_NGAYKETTHUC
),
sp_cte AS (
  -- Lấy sản phẩm đang sử dụng
  SELECT *
  FROM san_pham
  WHERE SP_CONSUDUNG = 1
)
SELECT 
  sp.SP_MA,
  sp.SP_TEN,
  g.GIA_GOC,
  ROUND(g.GIA_GOC * (100 - COALESCE(km.CTKM_PHANTRAM_GIAM, 0))/100, 0) AS GIA_HIEN_THI,
  km.CTKM_PHANTRAM_GIAM AS PHAN_TRAM_GIAM,
  a.Anh1,
  a.Anh2
FROM sp_cte sp
LEFT JOIN gia_cte g ON sp.SP_MA = g.SP_MA
LEFT JOIN km_cte km ON sp.SP_MA = km.SP_MA
LEFT JOIN anh_cte a ON sp.SP_MA = a.SP_MA
ORDER BY sp.SP_MA DESC
LIMIT 8;
";

$newResult = $conn->query($sqlNewProduct);


// Sản phẩm khuyến mãi mới nhất
$sqlPromo = "SELECT 
    sp.SP_MA,
    sp.SP_TEN,
    (
        SELECT g.DONGIA
        FROM don_gia_ban g
        JOIN thoi_diem td ON g.TD_THOIDIEM = td.TD_THOIDIEM
        WHERE g.SP_MA = sp.SP_MA
        ORDER BY td.TD_THOIDIEM DESC
        LIMIT 1
    ) AS GIA_GOC,
    ctkm.CTKM_PHANTRAM_GIAM,
    ROUND(
        (
            (
                SELECT g.DONGIA
                FROM don_gia_ban g
                JOIN thoi_diem td ON g.TD_THOIDIEM = td.TD_THOIDIEM
                WHERE g.SP_MA = sp.SP_MA
                ORDER BY td.TD_THOIDIEM DESC
                LIMIT 1
            ) * (100 - ctkm.CTKM_PHANTRAM_GIAM) / 100
        ), 0
    ) AS GIA_KM,
    a.Anh1,
    a.Anh2,
    k.KM_TEN,
    k.KM_NGAYKETTHUC
FROM chi_tiet_khuyen_mai ctkm
JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
JOIN san_pham sp ON ctkm.SP_MA = sp.SP_MA
LEFT JOIN (
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
) a ON sp.SP_MA = a.SP_MA
WHERE k.KM_CONSUDUNG = 1
  AND k.KM_NGAYBATDAU <= CURDATE()
  AND k.KM_NGAYKETTHUC >= CURDATE()
ORDER BY k.KM_NGAYBATDAU DESC
LIMIT 8;
";

$promoResult = $conn->query($sqlPromo);

$promoEndDate = null;
$promoName = 'FLASH SALE 🔥'; // mặc định nếu không có chương trình nào

if ($promoResult && $promoResult->num_rows > 0) {
    $firstPromo = $promoResult->fetch_assoc();
    $promoEndDate = $firstPromo['KM_NGAYKETTHUC'];
    $promoName = $firstPromo['KM_TEN'];
    $promoResult->data_seek(0);
}

// Số lượng đã bán
$sold = [];
$sql_slban = "SELECT sp.SP_MA, 
       COALESCE(SUM(CASE WHEN dh.DH_TRANGTHAI='Giao thành công' THEN ctdh.CTDH_SOLUONG ELSE 0 END),0) AS SL_DA_BAN
        FROM san_pham sp
        LEFT JOIN chi_tiet_don_hang ctdh ON sp.SP_MA = ctdh.SP_MA
        LEFT JOIN don_hang dh ON ctdh.DH_MA = dh.DH_MA
        GROUP BY sp.SP_MA
        ";
$res_slban = $conn->query($sql_slban);
while($r = $res_slban->fetch_assoc()) {
    $sold[$r['SP_MA']] = $r['SL_DA_BAN'];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>MODÉ - Trang Chủ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Link Css -->
  <link href="../assets/css/home.css" rel="stylesheet">
</head>
<body <?= isset($_SESSION['nd_ma']) ? 'data-nd-ma="'.intval($_SESSION['nd_ma']).'"' : '' ?>>

<!-- Header row -->
<div class="container header-row">
  <div class="row align-items-center">
    <div class="col-md-3 col-8">
      <a href="trangchu.php" class="brand-wrap text-decoration-none">
        <img src="../assets/images/logo.png" alt="Logo" class="logo">
        <div>
          <div style="font-family:'Playfair Display', serif; font-weight:700; font-size:25px; color:#4682B4; letter-spacing:3px;">MODÉ</div>
          <div style="font-size:15px; color:#777">Thời trang nam nữ</div>
        </div>
      </a>
    </div>

    <div class="col-md-6 d-none d-md-flex">
      <form class="search-bar" action="timkiem.php" method="post" enctype="multipart/form-data">
        <input name="q" type="search" placeholder="Tìm kiếm sản phẩm...">

        <!-- Input upload ảnh ẩn -->
        <input type="file" id="uploadImage" name="image" accept="image/*" style="display: none;">

        <!-- Nút máy ảnh -->
        <button type="button" class="btn-search-image" id="btnCamera">
            <i class="fa fa-camera"></i>
        </button>
      </form>
    </div>


    <div class="col-md-3 col-4 d-flex justify-content-end align-items-center gap-4">
      <div class="d-none d-md-block text-muted">
        <i class="fa-solid fa-phone icon-phone"></i> 0765 958 481
      </div>
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
<div class="modal fade" id="cartTempModal" tabindex="-1" aria-labelledby="cartTempModalLabel" aria-hidden="true">
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
<div class="modal fade" id="cartRealModal" tabindex="-1" aria-labelledby="cartRealModalLabel" aria-hidden="true">
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


<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- NAV -->
<nav class="navbar navbar-expand-lg main-nav">
  <div class="container">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbarBelow" aria-controls="mainNavbarBelow" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbarBelow">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link <?= ($current_page == 'trangchu.php') ? 'active' : '' ?>" href="trangchu.php">TRANG CHỦ</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($current_page == 'gioithieu.php') ? 'active' : '' ?>" href="gioithieu.php">GIỚI THIỆU</a>
        </li>

        <?php
        if ($topResult && $topResult->num_rows > 0) {
            while ($row = $topResult->fetch_assoc()) {
                $name = htmlspecialchars($row['DM_TEN'], ENT_QUOTES, 'UTF-8'); 
                $id = (int)$row['DM_MA'];
                echo '<li class="nav-item dropdown">';
                echo '<a class="nav-link dropdown-toggle" href="sanpham.php?dm=' . $id . '" role="button" data-bs-toggle="dropdown">' . $name . '</a>';
                getChildren($row['DM_MA'], $conn);
                echo '</li>';
            }
        }
        ?>

        <li class="nav-item">
          <a class="nav-link <?= ($current_page == 'voucher.php') ? 'active' : '' ?>" href="voucher.php">ƯU ĐÃI</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($current_page == 'lienhe.php') ? 'active' : '' ?>" href="lienhe.php">LIÊN HỆ</a>
        </li>
      </ul>
    </div>
  </div>
</nav>  

<!-- Banner Carousel -->
<div id="bannerCarousel" class="carousel slide carousel-fade mx-auto m-0" data-bs-ride="carousel" data-bs-interval="3000">
  <div class="carousel-inner rounded">
    <div class="carousel-item active"><img src="../assets/images/anhbia.png" class="d-block w-100" alt="Banner 1"></div>
    <div class="carousel-item"><img src="../assets/images/anhbia1.png" class="d-block w-100" alt="Banner 2"></div>
    <div class="carousel-item"><img src="../assets/images/anhbia2.png" class="d-block w-100" alt="Banner 3"></div>
    <div class="carousel-item"><img src="../assets/images/anhbia3.png" class="d-block w-100" alt="Banner 4"></div>
    <div class="carousel-item"><img src="../assets/images/anhbia4.png" class="d-block w-100" alt="Banner 5"></div>
    <div class="carousel-item"><img src="../assets/images/anhbia5.png" class="d-block w-100" alt="Banner 6"></div>
  </div>

  <!-- Indicators -->
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="0" class="active"></button>
    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="1"></button>
    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="2"></button>
    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="3"></button>
    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="4"></button>
    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="5"></button>
  </div>

  <!-- Nút điều hướng -->
  <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>
</div>

<section class="mode-history">
  <div class="container">
    <h2>Chân thành cảm ơn khi lựa chọn MODÉ</h2>
    <p>
      MODÉ được thành lập với sứ mệnh mang đến những sản phẩm thời trang tinh tế, sang trọng và đẳng cấp. 
      Trải qua nhiều năm phát triển, MODÉ đã trở thành thương hiệu được giới trẻ yêu thích nhờ sự kết hợp 
      hoàn hảo giữa phong cách hiện đại và giá trị truyền thống.
    </p>
    <p>
      Không chỉ chú trọng chất lượng, MODÉ còn luôn quan tâm đến trải nghiệm mua sắm của khách hàng. 
      Chúng tôi thường xuyên có những <a href="voucher.php" class="promo-link">voucher hấp dẫn</a> 
      để tri ân khách hàng thân thiết.
    </p>
  </div>
</section>

<!-- Giới thiệu sản phẩm -->
<div class="category-list">
  <a href="sanpham.php?dm=3" class="category-item">
    <div class="category-img">
      <img src="../assets/images/men_1.png" alt="Áo sơ mi nam">
    </div>
    <p>Áo sơ mi nam</p>
  </a>

  <a href="sanpham.php?dm=6" class="category-item">
    <div class="category-img">
      <img src="../assets/images/men_2.png" alt="Áo thun nam">
    </div>
    <p>Áo thun nam</p>
  </a>

  <a href="sanpham.php?dm=9" class="category-item">
    <div class="category-img">
      <img src="../assets/images/men_3.png" alt="Quần nam">
    </div>
    <p>Quần nam</p>
  </a>

  <a href="sanpham.php?dm=14" class="category-item">
    <div class="category-img">
      <img src="../assets/images/men.png" alt="Vecton nam">
    </div>
    <p>Vecton nam</p>
  </a>

  <a href="sanpham.php?dm=15" class="category-item">
    <div class="category-img">
      <img src="../assets/images/men_4.png" alt="Áo khoác nam">
    </div>
    <p>Áo khoác nam</p>
  </a>

  <a href="sanpham.php?dm=18" class="category-item">
    <div class="category-img">
      <img src="../assets/images/men_5.png" alt="Giày nam">
    </div>
    <p>Giày nam</p>
  </a>
</div>

<div class="category-list">
  <a href="sanpham.php?dm=21" class="category-item">
    <div class="category-img">
      <img src="../assets/images/women_1.png" alt="Áo sơ mi nữ">
    </div>
    <p>Áo sơ mi nữ</p>
  </a>

  <a href="sanpham.php?dm=24" class="category-item">
    <div class="category-img">
      <img src="../assets/images/women_2.png" alt="Áo thun nữ">
    </div>
    <p>Áo thun nữ</p>
  </a>

  <a href="sanpham.php?dm=25" class="category-item">
    <div class="category-img">
      <img src="../assets/images/women_3.png" alt="Quần nữ">
    </div>
    <p>Quần nữ</p>
  </a>

  <a href="sanpham.php?dm=31" class="category-item">
    <div class="category-img">
      <img src="../assets/images/women.png" alt="Đầm nữ">
    </div>
    <p>Đầm nữ</p>
  </a>

  <a href="sanpham.php?dm=34" class="category-item">
    <div class="category-img">
      <img src="../assets/images/women_4.png" alt="Áo khoác nữ">
    </div>
    <p>Áo khoác nữ</p>
  </a>

  <a href="sanpham.php?dm=35" class="category-item">
    <div class="category-img">
      <img src="../assets/images/women_5.png" alt="Giày nữ">
    </div>
    <p>Giày nữ</p>
  </a>
</div>

<!-- FLASH SALE / KHUYẾN MÃI -->
<div class="product-container flash-sale">
  <h3>
    <?= !empty($promoName) ? htmlspecialchars($promoName) : 'FLASH SALE 🔥' ?>
  </h3>

  <?php if (!empty($promoEndDate)): ?>
    <div id="countdown" class="countdown-timer" data-end="<?= htmlspecialchars($promoEndDate) ?>"></div>
  <?php endif; ?>

  <div class="featured-products">
    <?php if ($promoResult && $promoResult->num_rows > 0): ?>
      <?php while($row = $promoResult->fetch_assoc()) { ?>
        <div class="product-card promo-card">
          <div class="product-img">
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
              <img src="<?= $row['Anh1'] ?>" class="img-main" alt="<?= htmlspecialchars($row['SP_TEN']) ?>">
              <img src="<?= $row['Anh2'] ?>" class="img-hover" alt="<?= htmlspecialchars($row['SP_TEN']) ?>">
            </a>

            <!-- Giỏ hàng overlay khi hover -->
            <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
              <i class="fas fa-shopping-cart"></i>
            </a>

            <!-- Hiển thị giảm giá -->
            <?php if (!empty($row['CTKM_PHANTRAM_GIAM'])): ?>
              <span class="discount-badge">
                -<?= rtrim(rtrim(number_format($row['CTKM_PHANTRAM_GIAM'], 2), '0'), '.') ?>%
              </span>
            <?php endif; ?>
          </div>

          <div class="product-info">
            <h4>
              <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
                <?= htmlspecialchars($row['SP_TEN']) ?>
              </a>
            </h4>
            <p>
              <span class="new-price"><?= number_format($row['GIA_KM'], 0, ',', '.') ?> đ</span>
              <span class="old-price"><?= number_format($row['GIA_GOC'], 0, ',', '.') ?> đ</span>
            </p>
          </div>

          <div class="sl_ban">
            <?php if(!empty($sold[$row['SP_MA']])): ?>
              <p class="sold-count">Đã bán: <?= $sold[$row['SP_MA']] ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php } ?>
      <?php else: ?>
        <div class="no-promo-wrapper">
          <p>Hiện chưa có chương trình khuyến mãi nào đang diễn ra.</p>
        </div>
      <?php endif; ?>
  </div>

  <!-- Nút Xem thêm sản phẩm -->
  <div class="text-center mt-4">
    <a href="voucher.php" class="btn-xem-them">Xem thêm sản phẩm</a>
  </div>
</div>


<!-- Sản phẩm nổi bật -->
<div class="product-container">
  <h3>SẢN PHẨM NỔI BẬT</h3>
  <div class="featured-products">
    <?php while($row = $result->fetch_assoc()) { ?>
      <div class="product-card">
        <div class="product-img">
          <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
            <img src="<?= $row['Anh1'] ?>" class="img-main">
            <img src="<?= $row['Anh2'] ?>" class="img-hover">
          </a>
          <!-- Giỏ hàng overlay khi hover -->
          <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
              <i class="fas fa-shopping-cart"></i>
          </a>

          <?php if($row['PHAN_TRAM_GIAM'] > 0) { ?>
            <span class="discount-badge">-<?= rtrim(rtrim($row['PHAN_TRAM_GIAM'], '0'), '.') ?>%</span>
          <?php } ?>
        </div>
        <div class="product-info">
          <h4>
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
              <?= htmlspecialchars($row['SP_TEN']) ?>
            </a>
          </h4>
          <p>
            <?php if($row['PHAN_TRAM_GIAM'] > 0) { ?>
              <span class="old-price"><?= number_format($row['GIA_GOC'], 0, ',', '.') ?> đ</span>
              <span class="new-price"><?= number_format($row['GIA_HIEN_THI'], 0, ',', '.') ?> đ</span>
            <?php } else { ?>
              <span class="new-price"><?= number_format($row['GIA_HIEN_THI'], 0, ',', '.') ?> đ</span>
            <?php } ?>
          </p>
        </div>
      </div>
    <?php } ?>
  </div>
</div>

<!-- Xem nhanh chi tiết sản phẩm -->
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
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



<!-- Sản phẩm mới nhất -->
<div class="product-container">
  <h3>SẢN PHẨM MỚI NHẤT</h3>
  <div class="featured-products">
    <?php while($row = $newResult->fetch_assoc()) { ?>
      <div class="product-card">
        <div class="product-img">
          <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
            <img src="<?= $row['Anh1'] ?>" class="img-main">
            <img src="<?= $row['Anh2'] ?>" class="img-hover">
          </a>
          <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
            <i class="fas fa-shopping-cart"></i>
          </a>
          <?php if(!empty($row['PHAN_TRAM_GIAM'])): ?>
            <span class="discount-badge">-<?= rtrim(rtrim($row['PHAN_TRAM_GIAM'], '0'), '.') ?>%</span>
          <?php endif; ?>
        </div>
        <div class="product-info">
          <h4>
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
              <?= htmlspecialchars($row['SP_TEN']) ?>
            </a>
          </h4>
          <?php if(!empty($row['PHAN_TRAM_GIAM'])): ?>
            <p class="price">
              <span class="old-price"><?= number_format($row['GIA_GOC'],0,',','.') ?> đ</span>
              <span class="new-price"><?= number_format($row['GIA_HIEN_THI'],0,',','.') ?> đ</span>
            </p>
          <?php else: ?>
            <p class="new-price"><?= number_format($row['GIA_HIEN_THI'],0,',','.') ?> đ</p>
          <?php endif; ?>
        </div>
      </div>
    <?php } ?>
  </div>
</div>


 <!-- Vị trí -->
<div class="contact" id="vi-tri">
  <div class="contact-info">
    <h2>DI CHUYỂN ĐẾN MODÉ</h2>
    <p>MODÉ tọa lạc tại trung tâm thành phố Cần Thơ, thuận tiện kết nối với các tuyến đường chính.  
    Quý khách có thể dễ dàng di chuyển bằng ô tô hoặc taxi, hoặc sử dụng dịch vụ đặt xe từ sân bay Cần Thơ.</p>
    <p>Đội ngũ nhân viên MODÉ luôn sẵn sàng hỗ trợ quý khách trong việc mua sắm tại cửa hàng và tư vấn qua mạng, nhằm mang đến trải nghiệm mua sắm và tham quan trọn vẹn.</p>
  </div>

  <div class="map-contact">
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1964.3697420779843!2d105.78528773876302!3d10.038343388667064!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31a062a145b51c3f%3A0x575054c7bcd5398a!2zMTIgxJAuIE5ndXnhu4VuIMSQw6xuaCBDaGnhu4N1LCBUw6JuIEFuLCBOaW5oIEtp4buBdSwgQ-G6p24gVGjGoSwgVmnhu4d0IE5hbQ!5e0!3m2!1svi!2s!4v1760624846686!5m2!1svi!2s" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
  </div>
</div>


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


<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

  document.querySelectorAll('.dropdown').forEach(function(dd){
    dd.addEventListener('hidden.bs.dropdown', function () {
      dd.querySelectorAll('.dropdown-menu.show').forEach(function(sm){ sm.classList.remove('show'); });
    });
  });
});

document.querySelectorAll('.main-link').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const sub = this.nextElementSibling;
    if (sub) {
      sub.style.display = sub.style.display === "flex" ? "none" : "flex";
    }
  });
});

document.addEventListener("DOMContentLoaded", function() {
  const countdown = document.getElementById("countdown");
  const promoEnd = "<?= $promoEndDate ?>";

  // Nếu không có ngày thì thông báo
  if (!promoEnd || promoEnd.trim() === "") {
    countdown.innerHTML = "<span>Không có Flash Sale hiện tại</span>";
    return;
  }

  // Chuyển ngày sang đối tượng Date
  const endTime = new Date(promoEnd.replace(" ", "T")).getTime();
  if (isNaN(endTime)) {
    countdown.innerHTML = "<span>Ngày khuyến mãi không hợp lệ</span>";
    return;
  }

  function updateCountdown() {
    const now = new Date().getTime();
    const distance = endTime - now;

    if (distance <= 0) {
      countdown.innerHTML = "<span>FLASH SALE đã kết thúc!</span>";
      clearInterval(timer);
      return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    countdown.innerHTML = `
      <div class="timer-box"><span>${days}</span><small>Ngày</small></div>
      <div class="timer-box"><span>${hours}</span><small>Giờ</small></div>
      <div class="timer-box"><span>${minutes}</span><small>Phút</small></div>
      <div class="timer-box"><span>${seconds}</span><small>Giây</small></div>
    `;
  }

  updateCountdown();
  const timer = setInterval(updateCountdown, 1000);
});

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
        img 
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

// Upload ảnh để tìm kiếm sp
document.getElementById("btnCamera").addEventListener("click", function() {
  document.getElementById("uploadImage").click();
});

document.getElementById("uploadImage").addEventListener("change", function() {
  if (this.files && this.files[0]) {
    // Tự động gửi form sau khi chọn ảnh
    this.form.submit();
  }
});
</script>

</body>
</html>
<?php $conn->close(); ?>




