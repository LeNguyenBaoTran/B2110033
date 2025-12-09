<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);

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

// Lấy toàn bộ voucher hoạt động kèm thông tin loại
$sql = "
SELECT 
  V.VC_MA, V.VC_TEN, V.VC_TRANGTHAI,
  L.LVC_TYLEGIAM, L.LVC_MINGIATRI, L.LVC_MAXGIATRI,
  L.LVC_NGAYBATDAU, L.LVC_NGAYKETTHUC
FROM VOUCHER V
JOIN LOAI_VOUCHER L ON V.LVC_MA = L.LVC_MA
WHERE V.VC_TRANGTHAI = 'Hoạt động'
ORDER BY L.LVC_NGAYKETTHUC ASC
";
$result = $conn->query($sql);


// Số sản phẩm hiển thị mỗi trang
$limit = 16; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Đếm tổng số sản phẩm khuyến mãi
$sql_count_promo = "
    SELECT COUNT(*) AS total
    FROM chi_tiet_khuyen_mai ctkm
    JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
    JOIN san_pham sp ON ctkm.SP_MA = sp.SP_MA
    WHERE k.KM_CONSUDUNG = 1
";
$result_count_promo = $conn->query($sql_count_promo);
$total_row_promo = $result_count_promo->fetch_assoc()['total'];
$total_pages_promo = ceil($total_row_promo / $limit);

// Sản phẩm khuyến mãi
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
LIMIT $start, $limit
";

$promoResult = $conn->query($sqlPromo);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Voucher - MODÉ</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Link Css -->
  <link href="../assets/css/voucher.css" rel="stylesheet">
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



<div class="container py-5">
  <h3 class="section-title">Voucher Ưu Đãi</h3>

  <div class="row g-4 justify-content-center">
    <?php
    $today = date('Y-m-d H:i:s');
    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $het_han = ($today > $row['LVC_NGAYKETTHUC']) ? 'expired' : '';
        $giam = rtrim(rtrim($row['LVC_TYLEGIAM'], '0'), '.') . '%';
        $min = number_format($row['LVC_MINGIATRI'], 0, ',', '.');
        $max = number_format($row['LVC_MAXGIATRI'], 0, ',', '.');
        $ngayKT = date('d/m/Y', strtotime($row['LVC_NGAYKETTHUC']));
        $code = htmlspecialchars($row['VC_TEN']);

        echo "
        <div class='col-12 col-sm-6 col-md-4 col-lg-3'>
          <div class='voucher-card $het_han'>
            <div class='voucher-header'>Giảm $giam</div>
            <div class='voucher-body'>
              <p>Đơn từ <b>{$min}₫</b></p>
              <p>Giảm tối đa <b>{$max}₫</b></p>
              <div class='voucher-code'>$code</div>
              <p class='text-muted small mb-2'>HSD: $ngayKT</p>
            </div>
          </div>
        </div>
        ";
      }
    } else {
      echo "<p class='text-center text-muted'>Hiện chưa có voucher nào hoạt động</p>";
    }
    ?>
    <p class="voucher-note">
        💡 Nếu đơn hàng của bạn đủ điều kiện, hệ thống sẽ tự động áp dụng voucher tương ứng khi thanh toán.
    </p>
  </div>
</div>

<!-- sản phẩm khuyến mãi -->
<div class="product-container">
    <h3>SẢN PHẨM KHUYẾN MÃI</h3>
    <div class="featured-products">
        <?php while($row = $promoResult->fetch_assoc()) { ?>
        <div class="product-card promo-card">
            <div class="product-img">
            <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
                <img src="<?= $row['Anh1'] ?>" class="img-main">
                <img src="<?= $row['Anh2'] ?>" class="img-hover">
            </a>

            <!-- Giỏ hàng overlay khi hover -->
            <a href="cart.php?add=<?= $row['SP_MA'] ?>" class="cart-overlay">
                <i class="fas fa-shopping-cart"></i>
            </a>

            <!-- Hiển thị giảm giá -->
            <span class="discount-badge">
                -<?= rtrim(rtrim(number_format($row['CTKM_PHANTRAM_GIAM'], 2), '0'), '.') ?>%
            </span>
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


<!-- Phân trang cho sản phẩm khuyến mãi -->
<?php if ($total_pages_promo > 1): ?>
  <nav aria-label="Page navigation" class="d-flex justify-content-center mt-4">
    <ul class="pagination">
      <!-- Nút Previous -->
      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>

      <!-- Các trang -->
      <?php for ($i = 1; $i <= $total_pages_promo; $i++): ?>
        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>

      <!-- Nút Next -->
      <li class="page-item <?= ($page >= $total_pages_promo) ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
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
</body>
</html>
