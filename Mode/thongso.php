<?php
// KẾT NỐI CSDL 
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

// LẤY DANH MỤC CẤP 1 
$sql_dm_cap1 = "
    SELECT d1.DM_MA, d1.DM_TEN, d2.DM_TEN AS TEN_CHA
    FROM DANH_MUC d1
    JOIN DANH_MUC d2 ON d1.DM_CHA = d2.DM_MA
    WHERE d2.DM_CHA IS NULL
    ORDER BY d2.DM_TEN, d1.DM_TEN
";
$result_dm_cap1 = $conn->query($sql_dm_cap1);

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Bảng thông số sản phẩm</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Link Css -->
  <link href="../assets/css/home.css" rel="stylesheet">
  <link href="../assets/css/parameters.css" rel="stylesheet">
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

<!-- bảng thông số -->
<div class="parameters-page">
  <div class="container">
    <h1>CHĂM SÓC KHÁCH HÀNG</h1>
    <h4>Hướng dẫn chọn kích cỡ MODÉ</h4>
    <h2>Bảng thông số sản phẩm</h2>

    <form method="get">
      <label for="dm_ma">Chọn kích cỡ của:</label>
      <select name="dm_ma" id="dm_ma" onchange="this.form.submit()">
        <option value="">-- Chọn danh mục --</option>
        <?php
        if ($result_dm_cap1 && $result_dm_cap1->num_rows > 0) {
            while ($row = $result_dm_cap1->fetch_assoc()) {
                $selected = (isset($_GET['dm_ma']) && $_GET['dm_ma'] == $row['DM_MA']) ? 'selected' : '';
                echo '<option value="'.$row['DM_MA'].'" '.$selected.'>'
                    . htmlspecialchars($row['TEN_CHA'].' → '.$row['DM_TEN'])
                    . '</option>';
            }
        }
        ?>
      </select>
    </form>

    <?php
    if (isset($_GET['dm_ma']) && $_GET['dm_ma'] != '') {
      $dm_ma = intval($_GET['dm_ma']);
      $sql_ten = "SELECT DM_TEN FROM DANH_MUC WHERE DM_MA = $dm_ma";
      $res_ten = $conn->query($sql_ten);
      $ten_dm = ($res_ten && $res_ten->num_rows > 0) ? $res_ten->fetch_assoc()['DM_TEN'] : '';
    
      echo "<h3>Ảnh thông số cho: <span style='color:#2563eb;'>$ten_dm</span></h3>";
    
      // Chuyển tên danh mục thành dạng thư mục (vd: "Áo Sơ Mi Nam" → "ao-so-mi-nam")
      function removeVietnameseAccents($str) {
        $unicode = [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
            'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ',
            'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ'
        ];
        foreach ($unicode as $nonAccent => $accent) {
            $str = preg_replace("/($accent)/i", $nonAccent, $str);
        }
        return $str;
    }
    
    $ten_slug = removeVietnameseAccents($ten_dm);
    $folder_name = '../assets/images/parameters/' . strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $ten_slug)));    
    
      // Lấy tất cả ảnh trong thư mục
      $images = glob($folder_name . "/*.{jpg,jpeg,PNG,gif,webp}", GLOB_BRACE);

      if ($images && count($images) > 0) {
          echo "<div class='img-wrapper'>";
          foreach ($images as $img) {
              echo "<img src='$img' alt='Ảnh thông số $ten_dm'>";
          }
          echo "</div>";
      } else {
          echo "<p class='note'>Hiện chưa có ảnh thông số cho danh mục này.</p>";
      }
    } else {
      echo "<p class='note'>Vui lòng chọn danh mục để xem ảnh thông số.</p>";
    }
    $conn->close();
    ?>
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
