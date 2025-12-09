<?php
// sanpham.php — hiển thị sản phẩm theo danh mục

// Kết nối CSDL
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy ID danh mục từ URL
$dm_ma = isset($_GET['dm']) ? intval($_GET['dm']) : 0;
if ($dm_ma <= 0) {
    echo "<h2>Danh mục không hợp lệ.</h2>";
    exit;
}

// Lấy tên danh mục hiện tại và danh mục cha (nếu có)
$sql_breadcrumb = "
    WITH RECURSIVE dm_path AS (
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
while ($row = $result_breadcrumb->fetch_assoc()) {
    $breadcrumb[] = $row;
}

// Đảo ngược để cha nằm trước
$breadcrumb = array_reverse($breadcrumb);

// Lấy tên danh mục hiện tại
$ten_dm = htmlspecialchars($breadcrumb[count($breadcrumb)-1]['DM_TEN']);

// Số sản phẩm trên mỗi trang
$limit = 20;

// Xác định trang hiện tại
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// Tính offset (vị trí bắt đầu)
$offset = ($page - 1) * $limit;

// Xử lý sắp xếp
$order_by = "sp.SP_MA DESC"; // mặc định: mới nhất

if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'newest':
            $order_by = "sp.SP_MA DESC"; // mới nhất
            break;
        case 'oldest':
            $order_by = "sp.SP_MA ASC"; // cũ nhất
            break;
        case 'asc':
            $order_by = "GIA_MOI ASC"; // giá tăng dần
            break;
        case 'desc':
            $order_by = "GIA_MOI DESC"; // giá giảm dần
            break;
    }
}

// CÁC BỘ LỌC
$where_conditions = "sp.SP_CONSUDUNG = 1";
$gia_moi = "ROUND(g.DONGIA * (1 - IFNULL(km.CTKM_PHANTRAM_GIAM, 0) / 100), 0)";

// Nếu lọc theo màu sắc
if (isset($_GET['mausac']) && is_array($_GET['mausac']) && count($_GET['mausac']) > 0) {
  $colorConditions = [];
  foreach ($_GET['mausac'] as $color) {
      $color = $conn->real_escape_string($color);
      $colorConditions[] = "sp.SP_TEN COLLATE utf8mb4_general_ci LIKE '%$color%'";
  }
  $where_conditions .= " AND (" . implode(" OR ", $colorConditions) . ")";
}

// Nếu lọc theo khoảng giá
if (!empty($_GET['min_price'])) {
  $min_price = intval($_GET['min_price']);
  $where_conditions .= " AND $gia_moi >= $min_price";
}
if (!empty($_GET['max_price'])) {
  $max_price = intval($_GET['max_price']);
  $where_conditions .= " AND $gia_moi <= $max_price";
}

// Nếu lọc theo kích thước
if (!empty($_GET['kichthuoc'])) {
  $sizes = $_GET['kichthuoc'];
  $sizes_str = implode(",", array_map('intval', $sizes));
  $where_conditions .= " AND sp.SP_MA IN (
      SELECT SP_MA FROM CHI_TIET_SAN_PHAM WHERE KT_MA IN ($sizes_str)
  )";
}

// TRUY VẤN SẢN PHẨM 
$sql_sanpham = "WITH RECURSIVE dm_tree AS (
    SELECT DM_MA, DM_CHA 
    FROM DANH_MUC 
    WHERE DM_MA = $dm_ma
    UNION ALL
    SELECT d.DM_MA, d.DM_CHA 
    FROM DANH_MUC d
    JOIN dm_tree t ON d.DM_CHA = t.DM_MA
)
SELECT 
    sp.SP_MA, 
    sp.SP_TEN, 
    sp.SP_CONSUDUNG,
    g.DONGIA AS GIA_GOC,
    ROUND(g.DONGIA * (1 - IFNULL(km.CTKM_PHANTRAM_GIAM, 0) / 100), 0) AS GIA_MOI,
    km.CTKM_PHANTRAM_GIAM,
    MAX(CASE WHEN a.rn = 1 THEN a.ANH_DUONGDAN END) AS Anh1,
    MAX(CASE WHEN a.rn = 2 THEN a.ANH_DUONGDAN END) AS Anh2
FROM SAN_PHAM sp

LEFT JOIN (
    SELECT 
        SP_MA,
        DONGIA,
        ROW_NUMBER() OVER (PARTITION BY SP_MA ORDER BY TD_THOIDIEM DESC) AS rn
    FROM DON_GIA_BAN
) g ON sp.SP_MA = g.SP_MA AND g.rn = 1

LEFT JOIN (
    SELECT 
        SP_MA,
        ANH_DUONGDAN,
        ROW_NUMBER() OVER (PARTITION BY SP_MA ORDER BY ANH_MA ASC) AS rn
    FROM ANH_SAN_PHAM
) a ON sp.SP_MA = a.SP_MA AND a.rn <= 2

LEFT JOIN (
    SELECT 
        ctkm.SP_MA,
        ctkm.CTKM_PHANTRAM_GIAM
    FROM CHI_TIET_KHUYEN_MAI ctkm
    JOIN KHUYEN_MAI km 
      ON ctkm.KM_MA = km.KM_MA
    WHERE NOW() BETWEEN km.KM_NGAYBATDAU AND km.KM_NGAYKETTHUC
      AND km.KM_CONSUDUNG = 1
) km ON sp.SP_MA = km.SP_MA

WHERE sp.DM_MA IN (SELECT DM_MA FROM dm_tree)
  AND $where_conditions
GROUP BY sp.SP_MA, sp.SP_TEN, sp.SP_CONSUDUNG, g.DONGIA, km.CTKM_PHANTRAM_GIAM
ORDER BY $order_by
LIMIT $limit OFFSET $offset;
";

$result_sp = $conn->query($sql_sanpham);

// Đếm tổng số sản phẩm để chia trang
$sql_count = "WITH RECURSIVE dm_tree AS (
    SELECT DM_MA, DM_CHA 
    FROM DANH_MUC 
    WHERE DM_MA = $dm_ma
    UNION ALL
    SELECT d.DM_MA, d.DM_CHA 
    FROM DANH_MUC d
    JOIN dm_tree t ON d.DM_CHA = t.DM_MA
  )
  SELECT COUNT(*) AS total
  FROM SAN_PHAM sp
  WHERE sp.DM_MA IN (SELECT DM_MA FROM dm_tree)
    AND sp.SP_CONSUDUNG = 1;
";

$result_count = $conn->query($sql_count);
$total_row = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_row / $limit);



// Giới thiệu & ảnh minh họa tùy theo mã danh mục
switch ($dm_ma) {
  case 1: // Thời trang nam
    $intro_text = "Thời trang nam mang đến những thiết kế thanh lịch, hiện đại với đường cắt tinh gọn và chất liệu cao cấp, giúp bạn thoải mái ngời phong thái.";
    $intro_image = "../assets/images/anhdau4.png";
    break;
  
  case 3: // Áo sơ mi nam
      $intro_text = "Bộ sưu tập áo sơ mi mang đến những thiết kế thanh lịch, hiện đại với đường cắt tinh gọn và chất liệu cao cấp, giúp bạn khẳng định phong cách riêng trong mọi khoảnh khắc.";
      $intro_image = "../assets/images/anhdau1.png";
      break;

  case 4: // Áo sơ mi tay dài
      $intro_text = "Bộ sưu tập áo sơ mi tay dài thời trang với kiểu dáng đa dạng, chất liệu thoải mái giúp bạn tự tin trong mọi hoạt động.";
      $intro_image = "../assets/images/anhdau3.png";
      break;

  case 5: // Áo sơ mi tay ngắn
      $intro_text = "Bộ sưu tập áo sơ mi tay ngắn mang đến phong cách thoải mái nhưng vẫn giữ trọn nét thanh lịch. Chất liệu nhẹ, thoáng mát cùng đường cắt hiện đại giúp bạn dễ dàng thể hiện cá tính và tự tin trong mọi hoạt động thường ngày.";
      $intro_image = "../assets/images/anhdau2.png";
      break;

  case 6: // Áo thun nam
      $intro_text = "Bộ sư tập áo thun nam mang đến những thiết kế tinh tế, chỉn chu đến từng sợ chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau5.png";
      break;
  case 7: // Áo thun nam tay dài
      $intro_text = "Bộ sư tập áo thun nam dài tay mang đến những thiết kế tinh tế, chỉn chu đến từng sợ chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau7.png";
      break;
  case 8: // Áo thun nam
      $intro_text = "Bộ sư tập áo thun nam tay ngắn mang đến những thiết kế tinh tế, chỉn chu đến từng sợ chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau6.png";
      break;
  case 9: // Quần nam
      $intro_text = "Bộ sư tập quần nam mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, sang trọng tuyệt đối.";
      $intro_image = "../assets/images/anhdau8.png";
      break;
  case 14: // Vecton nam
      $intro_text = "Bộ sư tập vecton nam mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, sang trọng tuyệt đối.";
      $intro_image = "../assets/images/anhdau9.png";
      break;
  case 15: // Áo khoác nam
      $intro_text = "Bộ sư tập $ten_dm mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau10.png";
      break;
  case 16: // Áo jacket
      $intro_text = "Bộ sư tập $ten_dm mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau11.png";
      break;

  case 17: // Áo gió
        $intro_text = "Bộ sư tập $ten_dm mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
        $intro_image = "../assets/images/anhdau12.png";
        break;
  case 18: // Giày nam
        $intro_text = "Bộ sư tập $ten_dm mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
        $intro_image = "../assets/images/anhdau13.png";
        break;
  case 19: // Giày nam
      $intro_text = "Bộ sư tập $ten_dm mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau13.png";
      break;
  case 20: // Giày nam
      $intro_text = "Bộ sư tập $ten_dm mang đến những thiết kế tinh tế, chỉn chu đến từng vải chỉ mang lại cảm giác thoải mái, năng động tuyệt đối.";
      $intro_image = "../assets/images/anhdau14.png";
      break;
  
  default: // Danh mục khác
      $intro_text = "Khám phá bộ sưu tập $ten_dm với phong cách hiện đại và chất lượng tuyệt hảo.";
      $intro_image = "../assets/images/anhdau15.png";
      break;
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title><?= $ten_dm ?> | MODÉ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/product.css" rel="stylesheet">
  <link href="../assets/css/home.css" rel="stylesheet">
</head>
<body <?= isset($_SESSION['nd_ma']) ? 'data-nd-ma="'.intval($_SESSION['nd_ma']).'"' : '' ?>>

<!-- Header row -->
<div class="container header-row">
  <div class="row align-items-center">
    <div class="col-md-3 col-8">
      <a href="trangchu.php" class="brand-wrap text-decoration-none d-flex align-items-center gap-2">
        <img src="../assets/images/logo.png" alt="Logo" class="logo" style="height:60px;">
        <div>
          <div style="font-family:'Playfair Display', serif; font-weight:700; font-size:25px; color:#4682B4;">MODÉ</div>
          <div style="font-size:15px; color:#777">Thời trang nam nữ</div>
        </div>
      </a>
    </div>

    <div class="col-md-6 d-none d-md-flex">
      <form class="search-bar" action="timkiem.php" method="get">
        <input name="q" type="search" placeholder="Tìm kiếm sản phẩm...">
        <button type="button" class="btn-search-image"><i class="fa fa-camera"></i></button>
      </form>
    </div>

    <div class="col-md-3 col-4 d-flex justify-content-end align-items-center gap-4">
      <a href="#" id="btn-danhmuc" class="text-dark"><i class="fa-solid fa-list icon-category"></i></a>
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

<!-- Mở đầu danh mục -->
<div class="container mt-4 mb-5 category-intro">
  <div class="row align-items-center">
    <div class="col-md-7">
      <p class="intro-text mb-0">
        <?= $intro_text ?>
      </p>
    </div>
    <div class="col-md-5 text-center">
      <img src="<?= $intro_image ?>" alt="<?= $ten_dm ?>" class="img-fluid category-img">
    </div>
  </div>
</div>

<!-- Thanh bộ lọc & sắp xếp -->
<div class="container mb-3 d-flex justify-content-between align-items-center">
  <div>
    <button id="btn-filter" class="btn btn-outline-secondary">
      <i class="fa-solid fa-sliders-h"></i> Bộ lọc
    </button>
  </div>
  <div>
    <form method="get" class="d-flex align-items-center">
      <input type="hidden" name="dm" value="<?= $dm_ma ?>">
      <label for="sort-select" class="me-2">Sắp xếp:</label>
      <select name="sort" id="sort-select" class="form-select" style="width:200px;" onchange="this.form.submit()">
        <option value="">-- Mặc định --</option>
        <option value="newest" <?= (isset($_GET['sort']) && $_GET['sort']=='newest')?'selected':'' ?>>Mới nhất</option>
        <option value="oldest" <?= (isset($_GET['sort']) && $_GET['sort']=='oldest')?'selected':'' ?>>Cũ nhất</option>
        <option value="asc" <?= (isset($_GET['sort']) && $_GET['sort']=='asc')?'selected':'' ?>>Giá tăng dần</option>
        <option value="desc" <?= (isset($_GET['sort']) && $_GET['sort']=='desc')?'selected':'' ?>>Giá giảm dần</option>
      </select>
    </form>
  </div>
</div>

<!-- Sidebar bộ lọc -->
<div id="filter-sidebar">
  <div class="filter-header">
    <h5>Bộ lọc sản phẩm</h5>
    <button id="close-filter"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <form method="get" id="form-filter">
    <input type="hidden" name="dm" value="<?= $dm_ma ?>">

    <!-- Kích thước -->
    <div class="filter-section">
      <h6>Kích thước</h6>
      <?php
      $kt_res = $conn->query("SELECT * FROM KICH_THUOC ORDER BY KT_TEN ASC");
      while($kt = $kt_res->fetch_assoc()): ?>
        <div>
          <input type="checkbox" name="kichthuoc[]" value="<?= $kt['KT_MA'] ?>" 
            <?= (isset($_GET['kichthuoc']) && in_array($kt['KT_MA'], $_GET['kichthuoc']))?'checked':'' ?>>
          <label><?= htmlspecialchars($kt['KT_TEN']) ?></label>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- Màu sắc -->
    <div class="filter-section">
      <h6>Màu sắc</h6>
      <?php 
      $mau_sanpham = ['Trắng', 'Đen', 'Xanh', 'Be', 'Nâu', 'Xám', 'Đỏ', 'Vàng', 'Hồng'];
      foreach($mau_sanpham as $mau): ?>
        <div>
          <input type="checkbox" name="mausac[]" value="<?= $mau ?>"
            <?= (isset($_GET['mausac']) && in_array($mau, $_GET['mausac']))?'checked':'' ?>>
          <label><?= ucfirst($mau) ?></label>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Khoảng giá -->
    <div class="filter-section">
      <h6>Khoảng giá (đ)</h6>
      <input type="number" name="min_price" class="form-control mb-2" placeholder="Từ" value="<?= $_GET['min_price'] ?? '' ?>">
      <input type="number" name="max_price" class="form-control" placeholder="Đến" value="<?= $_GET['max_price'] ?? '' ?>">
    </div>

    <button type="submit" class="btn btn-primary w-100 mt-3">Áp dụng</button>
  </form>
</div>

<!-- Overlay mờ khi sidebar mở -->
<div id="filter-overlay"></div>


<!-- Sản phẩm theo danh mục -->
<div class="container mt-4 mb-5">
  <h2 class="text-center mb-4"><?= strtoupper($ten_dm) ?></h2>

  <?php if ($result_sp && $result_sp->num_rows > 0): ?>
    <div class="featured-products">
      <?php while ($row = $result_sp->fetch_assoc()): ?>
        <div class="product-card">
          <div class="product-img">
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
              <img src="<?= htmlspecialchars($row['Anh1']) ?>" class="img-main">
              <img src="<?= htmlspecialchars($row['Anh2']) ?>" class="img-hover">
            </a>
            <!-- Giỏ hàng overlay khi hover -->
            <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
              <i class="fas fa-shopping-cart"></i>
            </a>
            <!-- Badge giảm giá -->
            <?php if (!empty($row['CTKM_PHANTRAM_GIAM'])): ?>
              <span class="discount-badge">-<?= number_format($row['CTKM_PHANTRAM_GIAM'], 0) ?>%</span>
            <?php endif; ?>

            <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
              <i class="fas fa-shopping-cart"></i>
            </a>
          </div>
          <div class="product-info">
            <h4><a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>"><?= htmlspecialchars($row['SP_TEN']) ?></a></h4>
            <?php if (!empty($row['CTKM_PHANTRAM_GIAM'])): ?>
              <p>
                <span class="text-decoration-line-through text-muted">
                  <?= number_format($row['GIA_GOC'], 0, ',', '.') ?> đ
                </span>
                <span class="text-danger fw-bold ms-2">
                  <?= number_format($row['GIA_MOI'], 0, ',', '.') ?> đ
                </span>
              </p>
            <?php else: ?>
              <p><?= number_format($row['GIA_MOI'], 0, ',', '.') ?> đ</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p class="text-center text-muted">Hiện chưa có sản phẩm trong danh mục này.</p>
  <?php endif; ?>
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

<!-- Phân trang -->
<?php if ($total_pages > 1): ?>
  <nav aria-label="Page navigation" class="d-flex justify-content-center mt-4">
    <ul class="pagination">
      <!-- Nút Previous -->
      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?dm=<?= $dm_ma ?>&page=<?= $page - 1 ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>

      <!-- Các trang -->
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
          <a class="page-link" href="?dm=<?= $dm_ma ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>

      <!-- Nút Next -->
      <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
        <a class="page-link" href="?dm=<?= $dm_ma ?>&page=<?= $page + 1 ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
    </ul>
  </nav>
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
document.addEventListener("DOMContentLoaded", function() {
  const btnDanhMuc = document.getElementById("btn-danhmuc");
  const menuDanhMuc = document.getElementById("menu-danhmuc");
  const overlay = document.getElementById("overlay");

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

document.addEventListener('DOMContentLoaded', function(){
  const btn = document.getElementById('btn-filter');
  const sidebar = document.getElementById('filter-sidebar');
  const overlay = document.getElementById('filter-overlay');
  const closeBtn = document.getElementById('close-filter');

  btn.addEventListener('click', function(){
    sidebar.classList.add('active');
    overlay.classList.add('active');
  });

  overlay.addEventListener('click', closeSidebar);
  closeBtn.addEventListener('click', closeSidebar);

  function closeSidebar(){
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  }
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
</script>


<!-- Overlay mờ khi menu mở -->
<div id="overlay"></div>

</body>
</html>

<?php $conn->close(); ?>
