<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$thongbao = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hoten = trim($_POST['hoten']);
    $email = trim($_POST['email']);
    $sdt = trim($_POST['sdt']);
    $matkhau = trim($_POST['matkhau']);
    $diachi = trim($_POST['diachi']);

    // KIỂM TRA DỮ LIỆU HỢP LỆ
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $thongbao = "<div class='alert-error'>Email không hợp lệ! Vui lòng nhập đúng định dạng.</div>";
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        $thongbao = "<div class='alert-error'>Số điện thoại phải gồm đúng 10 chữ số và không chứa ký tự khác!</div>";
    } else {
        // Kiểm tra email hoặc số điện thoại đã tồn tại
        $sql_check = "SELECT * FROM nguoi_dung WHERE ND_EMAIL = ? OR ND_SDT = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $email, $sdt);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $thongbao = "<div class='alert-error'>Email hoặc số điện thoại đã tồn tại!</div>";
        } else {
            // Thêm người dùng mới
            $sql_insert_nd = "INSERT INTO nguoi_dung (ND_HOTEN, ND_EMAIL, ND_SDT, ND_MATKHAU, ND_DIACHI)
                              VALUES (?, ?, ?, ?, ?)";
            $stmt_insert_nd = $conn->prepare($sql_insert_nd);
            $stmt_insert_nd->bind_param("sssss", $hoten, $email, $sdt, $matkhau, $diachi);
            $stmt_insert_nd->execute();

            $nd_ma = $conn->insert_id;

            // Thêm khách hàng mới
            $sql_insert_kh = "INSERT INTO khach_hang (ND_MA, KH_DIEMTICHLUY) VALUES (?, 0)";
            $stmt_insert_kh = $conn->prepare($sql_insert_kh);
            $stmt_insert_kh->bind_param("i", $nd_ma);
            $stmt_insert_kh->execute();

            $_SESSION['nd_ma'] = $nd_ma;
            $_SESSION['nd_hoten'] = $hoten;
            $_SESSION['role'] = 'khachhang';
            
            $thongbao = "<div class='alert alert-success text-center mt-3'>
                Đăng ký thành công! <a href='dangnhap.php'>Nhấn vào đây để đăng nhập</a>.
            </div>";
        }
    }
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

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng ký - MODÉ</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="../assets/css/login.css" rel="stylesheet">
<link href="../assets/css/home.css" rel="stylesheet">
</head>
<body>

<!-- Header -->
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

<!-- Đăng ký -->
<div class="center-page">
  <div class="login-wrapper">
    <div class="login-left"></div>
    <div class="login-right">
      <h2>Đăng ký tài khoản MODÉ</h2>
      <form method="post">
        <input type="text" name="hoten" placeholder="Họ và tên" required>

        <!-- Email: kiểm tra có chứa @ -->
        <input type="email" name="email" placeholder="Email" required
               pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
               title="Vui lòng nhập đúng định dạng email (ví dụ: ten@gmail.com)">

        <!-- Số điện thoại: chỉ cho phép 10 chữ số -->
        <input type="text" name="sdt" placeholder="Số điện thoại" required
               pattern="^[0-9]{10}$"
               maxlength="10"
               title="Vui lòng nhập đúng 10 chữ số">

        <input type="password" name="matkhau" placeholder="Mật khẩu" required>
        <input type="text" name="diachi" placeholder="Địa chỉ" required>
        <button type="submit" class="btn-login">Đăng ký</button>
        <p class="text-center mt-3">
          Bạn đã có tài khoản? 
          <a href="dangnhap.php" style="color:#007bff;">Đăng Nhập</a>
        </p>
      </form>

      <?= $thongbao ?>
      <div class="commitment">
        <img src="../assets/images/logo.png" alt="Cam kết bảo mật">
        <span>Cam kết bảo mật thông tin 100%</span>
      </div>
    </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // --- Cập nhật số lượng hiển thị trên icon giỏ hàng ---
  function updateCartCount() {
    const countEl = document.getElementById('cart-count');
    if (!countEl) return;

    const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    const totalQty = cartTemp.reduce((sum, item) => sum + (item.qty || 0), 0);
    countEl.textContent = totalQty;
  }

  // --- Hiển thị giỏ tạm (chưa đăng nhập) ---
  function showCartTemp() {
    const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    const container = document.getElementById('cartTempContent');
    const totalEl = document.getElementById('cartTempTotal');

    if (!container || !totalEl) return;

    if (cartTemp.length === 0) {
      container.innerHTML = '<p class="text-center text-muted">Giỏ hàng của bạn đang trống.</p>';
      totalEl.textContent = '';
      const modal = new bootstrap.Modal(document.getElementById('cartTempModal'));
      modal.show();
      return;
    }

    let total = 0;
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

    cartTemp.forEach(item => {
      const subtotal = item.price * item.qty;
      total += subtotal;
      html += `
        <tr>
          <td><img src="${item.img || '../assets/images/logo.png'}" width="50" class="rounded"></td>
          <td>${item.SP_TEN}</td>
          <td>${item.KT_TEN}</td>
          <td>${item.qty}</td>
          <td>${item.price.toLocaleString()} đ</td>
          <td>${subtotal.toLocaleString()} đ</td>
        </tr>
      `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
    totalEl.textContent = 'Tổng: ' + total.toLocaleString() + ' đ';

    const modal = new bootstrap.Modal(document.getElementById('cartTempModal'));
    modal.show();
  }

  // --- Khi trang load ---
  document.addEventListener('DOMContentLoaded', () => {
    // Cập nhật số lượng
    updateCartCount();

    // Khi bấm icon giỏ hàng → mở giỏ tạm
    const cartIcon = document.querySelector('.cart-icon');
    if (cartIcon) {
      cartIcon.addEventListener('click', e => {
        e.preventDefault();
        showCartTemp();
      });
    }
  });
</script>

</body>
</html>
