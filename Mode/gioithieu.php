<?php
// gioithieu.php - Trang giới thiệu MODÉ
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Giới thiệu - MODÉ</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Link Css -->
  <link href="../assets/css/home.css" rel="stylesheet">
  <link href="../assets/css/introduce.css" rel="stylesheet">
</head>
<body <?= isset($_SESSION['nd_ma']) ? 'data-nd-ma="'.intval($_SESSION['nd_ma']).'"' : '' ?>>

  <!-- Header row -->
<div class="header-row">
  <div class="container">
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

  <main class="container">
    <!-- HERO -->
    <section class="hero">
      <div class="hero-grid">
        <div>
          <span class="badge">Thương hiệu Việt</span>
          <h1>MODÉ — Nghệ thuật của phong cách</h1>
          <p class="lead">Chúng tôi tạo ra trang phục giúp bạn tự tin, tinh tế và nổi bật mỗi ngày. Khai thác chất liệu cao cấp, cắt may chuẩn mực và thiết kế thời thượng cho cả nam và nữ.</p>
          <a class="cta" href="sanpham.php?dm=1">Bộ sưu tập thời trang nam</a>
          <a class="cta" href="sanpham.php?dm=2">Bộ sưu tập thời trang nữ</a>
        </div>
        <div class="hero-img">
          <img src="../assets/images/anhbia.png" alt="MODÉ hero image">
        </div>
      </div>
    </section>

    <!-- Giới thiệu đôi nét -->
    <section class="section">
      <h2>Đôi nét về chúng tôi</h2>
      <p>MODÉ ra đời với sứ mệnh kết nối thiết kế tinh tế với trải nghiệm mặc thoải mái. Từ việc tuyển chọn vải, kiểm soát quy trình may đến dịch vụ chăm sóc khách hàng, mỗi bước đều được chúng tôi chăm chút tỉ mỉ.</p>

      <div class="grid-2">
        <div class="feature">
          <h3>Triết lý thiết kế</h3>
          <p>Phong cách tối giản, chú trọng tỉ lệ và đường cắt giúp sản phẩm phù hợp với nhiều vóc dáng. Chúng tôi ưu tiên sự tiện dụng nhưng không đánh mất tính thẩm mỹ.</p>
        </div>
        <div class="feature">
          <h3>Chất lượng & Phụ trách</h3>
          <p>Mỗi sản phẩm đều trải qua kiểm tra chất lượng nghiêm ngặt. Chúng tôi sử dụng nguồn vải được chọn lọc và hợp tác với xưởng may uy tín.</p>
        </div>
      </div>
    </section>

    <!-- Nam & Nữ -->
    <section class="section">
      <h2>Thời trang Nam & Nữ</h2>
      <p>MODÉ phát triển hai dòng chủ lực: Thời Trang Nam tập trung vào đường cắt mạnh mẽ, tinh gọn; Thời Trang Nữ khai thác vẻ mềm mại, thanh lịch nhưng hiện đại.</p>

      <div class="grid-2">
        <div class="img-card">
          <img src="../assets/images/gioithieu1.png" alt="Thời trang nam">
        </div>
        <div class="img-card">
          <img src="../assets/images/gioithieu.png" alt="Thời trang nữ">
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <h4>Bộ sưu tập Nam</h4>
          <p>Áo sơ mi, Áo thun, Vecton, Áo khoác, Giày và Quần cắt may giúp bạn toát lên phong thái chuyên nghiệp.</p>
        </div>
        <div class="card">
          <h4>Bộ sưu tập Nữ</h4>
          <p>Đầm, Áo sơ mi, Áo thun, Váy, Áo khoác, Giày và set công sở - mềm mại, tinh xảo và dễ phối đồ.</p>
        </div>
      </div>
    </section>

    <!-- Về sản phẩm -->
    <section class="section">
      <h2>Về sản phẩm</h2>
      <p>Sản phẩm MODÉ được thiết kế để đồng hành cùng nhịp sống hiện đại: dễ phối, giữ form sau nhiều lần giặt và thoải mái cả ngày dài.</p>

      <div class="cards">
        <div class="card">
          <h4>Chất liệu cao cấp</h4>
          <p>Vải nhập khẩu và vải Việt Nam tuyển chọn, tối ưu độ bền và cảm giác khi mặc.</p>
        </div>
        <div class="card">
          <h4>Gia công tinh xảo</h4>
          <p>Đường may chắc chắn, hoàn thiện tỉ mỉ - mỗi chi tiết đều được kiểm tra kỹ lưỡng.</p>
        </div>
        <div class="card">
          <h4>Thiết kế đa năng</h4>
          <p>Sản phẩm phù hợp sử dụng trong công sở, dạo phố hay sự kiện nhẹ nhàng.</p>
        </div>
      </div>
    </section>

    <!-- Về khách hàng -->
    <section class="section">
      <h2>Về khách hàng của chúng tôi</h2>
      <p>MODÉ tự hào phục vụ cộng đồng khách hàng yêu thích phong cách tinh tế, từ giới trẻ năng động đến người đi làm mong muốn hình ảnh chuyên nghiệp.</p>

      <div class="testi">
        <div class="bubble">
          <p>"Chất lượng tốt, form chuẩn vừa vặn. Giao hàng nhanh và dịch vụ chăm sóc khách hàng rất chu đáo."</p>
          <div class="meta">— Nguyễn Thanh Hưng, Hà Nội</div>
        </div>
        <div class="bubble">
          <p>"Tôi yêu các thiết kế nữ tại MODÉ: vừa thanh lịch vừa dễ mặc mỗi ngày."</p>
          <div class="meta">— Ngũ Ngọc Châu, TP.HCM</div>
        </div>
        <div class="bubble">
          <p>"Đã mua nhiều lần, sản phẩm bền và vẫn giữ form sau nhiều lần giặt."</p>
          <div class="meta">— Công Tiến Độ, Đà Nẵng</div>
        </div>
      </div>
    </section>

    <!-- CTA cuối -->
    <section class="cta-end">
      <div>
        <h3>Sẵn sàng trải nghiệm phong cách MODÉ?</h3>
        <p>Khám phá bộ sưu tập mới và nhận ưu đãi đặc biệt dành cho khách hàng lần đầu.</p>
      </div>
      <div>
        <a class="cta" href="sanpham.php">Mua ngay</a>
      </div>
    </section>
  </main>

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
  function updateCartCount() {
    const countEl = document.getElementById('cart-count');
    const ndMa = document.body.dataset.ndMa || null;

    if (!ndMa) {
      // Nếu chưa đăng nhập → đếm giỏ tạm trong localStorage
      const cartTemp = JSON.parse(localStorage.getItem('cartTemp') || '[]');
      const totalQty = cartTemp.reduce((sum, item) => sum + (item.qty || 0), 0);
      if (countEl) countEl.textContent = totalQty;
      return;
    }

    // Nếu đã đăng nhập → lấy tổng từ CSDL
    fetch('get_cart.php')
      .then(res => res.json())
      .then(data => {
        if (countEl) {
          if (data && Array.isArray(data.items)) {
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

  // Gọi khi load trang
  document.addEventListener('DOMContentLoaded', updateCartCount);

// Modal giỏ hàng tạm
const cartTempModal = new bootstrap.Modal(document.getElementById('cartTempModal'));

// Hiển thị giỏ tạm (khi chưa đăng nhập)
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
  let html = '<table class="table align-middle"><thead><tr><th>Ảnh</th><th>Sản phẩm</th><th>Size</th><th>Số lượng</th><th>Giá</th></tr></thead><tbody>';

  cartTemp.forEach(item => {
    const subtotal = item.price * item.qty;
    total += subtotal;
    html += `<tr>
      <td><img src="${item.img || '../assets/images/logo.png'}" width="50"></td>
      <td>${item.SP_TEN}</td>
      <td>${item.KT_TEN}</td>
      <td>${item.qty}</td>
      <td>${item.price.toLocaleString()} đ</td>
    </tr>`;
  });

  html += '</tbody></table>';
  container.innerHTML = html;
  totalEl.textContent = 'Tổng: ' + total.toLocaleString() + ' đ';

  cartTempModal.show();
}

// Giỏ hàng thật (đã đăng nhập)
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

      let html = `<table class="table align-middle"><thead><tr>
        <th>Ảnh</th><th>Sản phẩm</th><th>Size</th><th>Số lượng</th><th>Giá</th><th>Thành tiền</th>
      </tr></thead><tbody>`;

      let total = 0;
      data.items.forEach(item => {
        const subtotal = item.qty * item.price;
        total += subtotal;
        html += `<tr>
          <td><img src="${item.SP_ANH || '../assets/images/logo.png'}" width="50"></td>
          <td>${item.SP_TEN}</td>
          <td>${item.KT_TEN}</td>
          <td>${item.qty}</td>
          <td>${item.price.toLocaleString()} đ</td>
          <td>${subtotal.toLocaleString()} đ</td>
        </tr>`;
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

// Khi bấm icon giỏ hàng
document.addEventListener('DOMContentLoaded', function() {
  const cartIcon = document.querySelector('.cart-icon');
  if (cartIcon) {
    cartIcon.addEventListener('click', function(e) {
      e.preventDefault();
      const ndMa = document.body.dataset.ndMa || null;
      if (!ndMa) showCartTemp(); else openCart();
    });
  }
});
</script>

</body>
</html>
