<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// ===== KIỂM TRA ĐĂNG NHẬP =====
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để xem lịch sử đơn hàng!'); window.location='../Mode/dangnhap.php';</script>";
    exit;
}

$nd_ma = $_SESSION['nd_ma'];

// ===== LẤY DANH SÁCH ĐƠN HÀNG =====
$sql_dh = "
    SELECT dh.DH_MA, dh.DH_NGAYDAT, dh.DH_TRANGTHAI, dh.DH_TONGTIENHANG, 
           dh.DH_GIAMGIA, dh.DH_TONGTHANHTOAN, dh.DH_DIACHINHAN,
           dv.DVVC_TEN, v.VC_TEN
    FROM DON_HANG dh
    LEFT JOIN DON_VI_VAN_CHUYEN dv ON dh.DVVC_MA = dv.DVVC_MA
    LEFT JOIN VOUCHER v ON dh.VC_MA = v.VC_MA
    WHERE dh.ND_MA = '$nd_ma'
    AND dh.DH_TRANGTHAI <> 'Đã hủy'
    ORDER BY dh.DH_NGAYDAT DESC, dh.DH_MA DESC
";

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
            echo '<a class="dropdown-item dropdown-toggle" href="../Mode/sanpham.php?dm=' . $id . '">' . $name . '</a>';
            getChildren($row['DM_MA'], $conn);
            echo '</li>';
        }
        echo '</ul>';
    }
}

// Lấy menu cấp 1
$sqlTop = "SELECT DM_MA, DM_TEN FROM DANH_MUC WHERE DM_CHA IS NULL ORDER BY DM_MA";
$topResult = $conn->query($sqlTop);

// Lọc đơn hàng theo ngày
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

$sql = "SELECT * FROM DON_HANG dh 
        LEFT JOIN DON_VI_VAN_CHUYEN dv ON dh.DVVC_MA = dv.DVVC_MA
        LEFT JOIN VOUCHER vc ON dh.VC_MA = vc.VC_MA
        WHERE dh.ND_MA = $nd_ma"; // lọc theo người dùng

// Nếu có lọc ngày thì thêm điều kiện
if ($start && $end) {
    $sql .= " AND DATE(dh.DH_NGAYDAT) BETWEEN '$start' AND '$end'";
}

$sql .= " ORDER BY dh.DH_MA DESC";

$result_dh = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch sử đơn hàng</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Link Css -->
    <link href="../assets/css/home.css" rel="stylesheet">
    <link href="../assets/css/order.css" rel="stylesheet">
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
              <li><a class="dropdown-item" href="khachhang.php">Trang cá nhân</a></li>
              <li><a class="dropdown-item" href="lichsu_donhang.php">Đơn hàng của tôi</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="../Mode/dangxuat.php">Đăng xuất</a></li>
            </ul>
          <?php else: ?>
            <a class="nav-link text-dark" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-user icon-user"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li><a class="dropdown-item" href="../Mode/dangnhap.php">Đăng nhập</a></li>
              <li><a class="dropdown-item" href="../Mode/dangky.php">Đăng ký</a></li>
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
        <a href="../Mode/cart.php" class="btn btn-dark">Đi đến giỏ hàng</a>
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
          <a class="nav-link <?= ($current_page == '../Mode/trangchu.php') ? 'active' : '' ?>" href="../Mode/trangchu.php">TRANG CHỦ</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($current_page == '../Mode/gioithieu.php') ? 'active' : '' ?>" href="../Mode/gioithieu.php">GIỚI THIỆU</a>
        </li>

        <?php
        if ($topResult && $topResult->num_rows > 0) {
            while ($row = $topResult->fetch_assoc()) {
                $name = htmlspecialchars($row['DM_TEN'], ENT_QUOTES, 'UTF-8'); 
                $id = (int)$row['DM_MA'];
                echo '<li class="nav-item dropdown">';
                echo '<a class="nav-link dropdown-toggle" href="../Mode/sanpham.php?dm=' . $id . '" role="button" data-bs-toggle="dropdown">' . $name . '</a>';
                getChildren($row['DM_MA'], $conn);
                echo '</li>';
            }
        }
        ?>

        <li class="nav-item">
          <a class="nav-link <?= ($current_page == '../Mode/voucher.php') ? 'active' : '' ?>" href="../Mode/voucher.php">ƯU ĐÃI</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($current_page == '../Mode/lienhe.php') ? 'active' : '' ?>" href="../Mode/lienhe.php">LIÊN HỆ</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Lọc theo khoảng ngày -->
<div class="filter-date-box d-flex gap-2 align-items-center">
    <label for="startDate" class="mb-0">Từ:</label>

    <!-- GẮN value để giữ ngày -->
    <input type="date" id="startDate"
           value="<?php echo isset($_GET['start']) ? $_GET['start'] : ''; ?>"
           class="form-control form-control-sm" style="width:150px;">

    <label for="endDate" class="mb-0">Đến:</label>

    <input type="date" id="endDate"
           value="<?php echo isset($_GET['end']) ? $_GET['end'] : ''; ?>"
           class="form-control form-control-sm" style="width:150px;">

    <button id="filterBtn" class="btn btn-primary btn-sm">Lọc</button>
    <button id="resetBtn" class="btn btn-secondary btn-sm">Reset</button>
</div>


<h2 class="page-title">ĐƠN HÀNG CỦA BẠN</h2>
<?php if ($result_dh && $result_dh->num_rows > 0): ?>
    <div class="order-history-container">
        <table class="order-table">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày đặt</th>
                    <th>Trạng thái</th>
                    <th>Tổng tiền hàng</th>
                    <th>Giảm giá</th>
                    <th>Tổng thanh toán</th>
                    <th>Địa chỉ nhận</th>
                    <th>Đơn vị vận chuyển</th>
                    <th>Voucher</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result_dh->fetch_assoc()): ?>
                    <tr class="order-row">
                        <td class="order-id">#<?php echo $row['DH_MA']; ?></td>
                        <td class="order-date">
                          <?php $date = new DateTime($row['DH_NGAYDAT']); 
                          echo $date->format('d/m/Y'); 
                          ?>
                        </td>
                        <td class="order-status <?php echo strtolower(str_replace(' ', '-', $row['DH_TRANGTHAI'])); ?>">
                            <?php echo $row['DH_TRANGTHAI']; ?>
                        </td>
                        <td class="order-subtotal"><?php echo number_format($row['DH_TONGTIENHANG']); ?> ₫</td>
                        <td class="order-discount"><?php echo number_format($row['DH_GIAMGIA']); ?> ₫</td>
                        <td class="order-total"><?php echo number_format($row['DH_TONGTHANHTOAN']); ?> ₫</td>
                        <td class="order-address"><?php echo htmlspecialchars($row['DH_DIACHINHAN']); ?></td>
                        <td class="order-shipping"><?php echo $row['DVVC_TEN'] ?? '-'; ?></td>
                        <td class="order-voucher"><?php echo $row['VC_TEN'] ?? '-'; ?></td>
                        <td class="order-action">
                            <a class="btn-view-detail" href="chitiet_donhang.php?dh_ma=<?php echo $row['DH_MA']; ?>">
                                Xem
                            </a>

                            <?php if ($row['DH_TRANGTHAI'] == 'Giao thành công'): ?>
                                <a class="btn-invoice" href="hoadon.php?dh_ma=<?php echo $row['DH_MA']; ?>" target="_blank">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </a>
                            <?php endif; ?>

                            <?php if(in_array($row['DH_TRANGTHAI'], ['Chờ xác nhận', 'Chờ thanh toán', 'Đang chuẩn bị hàng', 'Đã thanh toán'])): ?>
                                <form action="huy_donhang.php" method="POST"
                                      onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này không?');"
                                      style="display:inline-block;">
                                    <input type="hidden" name="dh_ma" value="<?php echo $row['DH_MA']; ?>">
                                    <button type="submit" class="btn-cancel-order">Xóa</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="no-order-msg">Bạn chưa có đơn hàng nào.</p>
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
          <li><a href="../Mode/trangchu.php">Trang chủ</a></li>
          <li><a href="../Mode/gioithieu.php">Giới thiệu</a></li>
          <li><a href="../Mode/sanpham.php?dm=1">Thời trang nam</a></li>
          <li><a href="../Mode/sanpham.php?dm=2">Thời trang nữ</a></li>
          <li><a href="../Mode/voucher.php">Ưu Đãi</a></li>
          <li><a href="../Mode/lienhe.php">Liên hệ</a></li>
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
    // Nếu đã đăng nhập → lấy tổng từ CSDL
    fetch('../Mode/get_cart.php')
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

  // Giỏ hàng thật (đã đăng nhập)
  function openCart() {
    const modal = new bootstrap.Modal(document.getElementById('cartRealModal'));
    const content = document.getElementById('cartRealContent');
    const totalEl = document.getElementById('cartRealTotal');

    content.innerHTML = `<p class="text-center text-muted">Đang tải...</p>`;
    totalEl.textContent = "Tổng: 0 đ";

    fetch("../Mode/get_cart.php")
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
        if (ndMa) openCart();
      });
    }
  });
</script>

<script>
document.getElementById("filterBtn").addEventListener("click", function () {
    let start = document.getElementById("startDate").value;
    let end = document.getElementById("endDate").value;

    // Nếu chưa chọn đủ ngày thì báo
    if (!start || !end) {
        alert("Vui lòng chọn đầy đủ ngày TỪ và ĐẾN!");
        return;
    }

    // Chuyển trang kèm tham số
    window.location.href = `lichsu_donhang.php?start=${start}&end=${end}`;
});

document.getElementById("resetBtn").addEventListener("click", function () {
    window.location.href = "lichsu_donhang.php";
});
</script>

</body>
</html>
