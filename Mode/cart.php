<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

$ndMa = $_SESSION['nd_ma'] ?? null;

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
<title>Giỏ hàng</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<!-- Link Css -->
<link href="../assets/css/home.css" rel="stylesheet">
<link href="../assets/css/cart.css" rel="stylesheet">

</head>
<body data-nd-ma="<?= $ndMa ?>">

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


<div class="container my-5">
  <h3>GIỎ HÀNG CỦA BẠN</h3>
  <div id="cart-container">
    <p class="text-muted">Đang tải giỏ hàng...</p>
  </div>

  <div class="cart-actions mt-3">
    <button id="btn-back" class="btn btn-secondary me-2" onclick="history.back()">Quay lại</button>
    <button id="btn-checkout" class="btn btn-primary">Đặt hàng</button>
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
// Lấy ND_MA để kiểm tra đăng nhập
const ndMa = document.body.dataset.ndMa;

// ==== Giỏ tạm (localStorage) ====
function renderCartTemp() {
    const cart = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    const container = document.getElementById('cart-container');

    if (cart.length === 0) {
        container.innerHTML = '<p>Giỏ tạm trống.</p>';
        return;
    }

    let html = `<table class="table align-middle">
        <thead>
            <tr>
                <th>Ảnh</th>
                <th>Sản phẩm</th>
                <th>Size</th>
                <th>Số lượng</th>
                <th>Giá</th>
                <th>Thành tiền</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>`;
    let total = 0;

    cart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        html += `<tr>
            <td><img src="${item.img || '../assets/images/logo.png'}" width="50"></td>
            <td>${item.SP_TEN}</td>
            <td>${item.KT_TEN}</td>
            <td>
                <button class="btn btn-sm btn-outline-secondary" onclick="changeQtyTemp(${index}, -1)">-</button>
                <span class="mx-2">${item.qty}</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="changeQtyTemp(${index}, 1)">+</button>
            </td>
            <td>${item.price.toLocaleString()} đ</td>
            <td>${subtotal.toLocaleString()} đ</td>
            <td><button class="btn btn-sm btn-danger" onclick="removeCartTemp(${index})">Xóa</button></td>
        </tr>`;
    });

    html += `</tbody></table>`;
    html += `<h5 class="text-end">Tổng: ${total.toLocaleString()} đ</h5>`;
    container.innerHTML = html;
}

function changeQtyTemp(index, delta) {
    const cart = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    if (!cart[index]) return;

    cart[index].qty += delta;

    if (cart[index].qty < 1) cart[index].qty = 1;

    // Kiểm tra giới hạn tồn kho (nếu có)
    if (cart[index].maxQty && cart[index].qty > cart[index].maxQty) {
        alert('Số lượng vượt quá tồn kho (' + cart[index].maxQty + ')');
        cart[index].qty = cart[index].maxQty;
    }

    localStorage.setItem('cartTemp', JSON.stringify(cart));
    renderCartTemp();
}


function removeCartTemp(index) {
    const cart = JSON.parse(localStorage.getItem('cartTemp') || '[]');
    cart.splice(index, 1);
    localStorage.setItem('cartTemp', JSON.stringify(cart));
    renderCartTemp();
}

// ==== Giỏ thật (CSDL) ====
function renderCartReal() {
    const container = document.getElementById('cart-container');
    container.innerHTML = '<p class="text-muted">Đang tải giỏ hàng...</p>';

    fetch('get_cart.php')
    .then(res => res.json())
    .then(data => {
        if (!data.items || data.items.length === 0) {
            container.innerHTML = '<p>Giỏ hàng trống.</p>';
            return;
        }

        let html = `<table class="table align-middle">
            <thead>
                <tr>
                    <th>Ảnh</th>
                    <th>Sản phẩm</th>
                    <th>Size</th>
                    <th>Số lượng</th>
                    <th>Giá</th>
                    <th>Thành tiền</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>`;
        let total = 0;

        data.items.forEach(item => {
            const subtotal = item.qty * item.price;
            total += subtotal;
            html += `<tr>
                <td><img src="${item.SP_ANH || '../assets/images/logo.png'}" width="50"></td>
                <td>${item.SP_TEN}</td>
                <td>${item.KT_TEN}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary" onclick="changeQtyReal(${item.SP_MA}, ${item.KT_MA}, -1)">-</button>
                    <span class="mx-2">${item.qty}</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="changeQtyReal(${item.SP_MA}, ${item.KT_MA}, 1)">+</button>
                </td>
                <td>${item.price.toLocaleString()} đ</td>
                <td>${subtotal.toLocaleString()} đ</td>
                <td><button class="btn btn-sm btn-danger" onclick="removeCartReal(${item.SP_MA}, ${item.KT_MA})">Xóa</button></td>
            </tr>`;
        });

        html += `</tbody></table>`;
        html += `<h5 class="text-end">Tổng: ${total.toLocaleString()} đ</h5>`;
        container.innerHTML = html;
    })
    .catch(() => {
        container.innerHTML = '<p class="text-danger text-center">Lỗi tải giỏ hàng.</p>';
    });
}

function changeQtyReal(spMa, ktMa, delta) {
    fetch('update_cart.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({SP_MA: spMa, KT_MA: ktMa, delta})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) renderCartReal();
        else alert(data.message || 'Lỗi cập nhật giỏ hàng');
    });
}

function removeCartReal(spMa, ktMa) {
    fetch('remove_cart.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({SP_MA: spMa, KT_MA: ktMa})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) renderCartReal();
        else alert(data.message || 'Lỗi xóa sản phẩm');
    });
}

// ==== Load khi trang load ====
document.addEventListener('DOMContentLoaded', function() {
    if (!ndMa) {
        renderCartTemp();
    } else {
        renderCartReal();
    }
});

// ==== Nút Đặt hàng ====
document.getElementById('btn-checkout').addEventListener('click', function() {
    if (!ndMa) {
        alert('Bạn cần đăng nhập trước khi đặt hàng');
        window.location.href = 'dangnhap.php?redirect=thanhtoan.php';
    } else {
        window.location.href = 'thanhtoan.php';
    }
});
</script>

</body>
</html>
