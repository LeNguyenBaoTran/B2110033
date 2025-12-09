<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để thanh toán!'); window.location='dangnhap.php';</script>";
    exit;
}

// Địa chỉ shop (Cần Thơ)
$shop_lat = 10.037148553484077;
$shop_lng = 105.78688505212948;

$nd_ma = $_SESSION['nd_ma'];

// Lấy thông tin người dùng
$sql_user = "SELECT n.ND_HOTEN, n.ND_EMAIL, n.ND_SDT, n.ND_DIACHI, k.KH_DIEMTICHLUY
             FROM nguoi_dung n
             LEFT JOIN khach_hang k ON n.ND_MA = k.ND_MA
             WHERE n.ND_MA = $nd_ma";
$user = $conn->query($sql_user)->fetch_assoc();

// Lấy thông tin giỏ hàng chi tiết
$sql_cart = "SELECT 
        sp.SP_MA, 
        sp.SP_TEN, 
        kt.KT_TEN AS KichThuoc, 
        ct.CTGH_SOLUONG, 
        ct.CTGH_DONGIA,
        (SELECT a.ANH_DUONGDAN 
         FROM anh_san_pham a 
         WHERE a.SP_MA = sp.SP_MA 
         LIMIT 1) AS ANH_DAU
    FROM gio_hang g
    JOIN chi_tiet_gio_hang ct ON g.GH_MA = ct.GH_MA
    JOIN san_pham sp ON ct.SP_MA = sp.SP_MA
    JOIN kich_thuoc kt ON ct.KT_MA = kt.KT_MA
    WHERE g.ND_MA = $nd_ma
";

$result_cart = $conn->query($sql_cart);

$cart_items = [];
$tam_tinh = 0;

while ($row = $result_cart->fetch_assoc()) {
    $cart_items[] = $row;
    $tam_tinh += $row['CTGH_SOLUONG'] * $row['CTGH_DONGIA'];
}

if (empty($cart_items)) {
  echo "<script>alert('Giỏ hàng của bạn đang trống!'); window.location='cart.php';</script>";
  exit;
}

// Kiểm tra voucher tự động
$voucher_applied = null;
$voucher_discount = 0;
$now = date('Y-m-d H:i:s');
$sql_voucher = "SELECT v.VC_MA, v.VC_TEN, lv.LVC_TYLEGIAM, lv.LVC_MINGIATRI, lv.LVC_MAXGIATRI
                FROM voucher v
                JOIN loai_voucher lv ON v.LVC_MA = lv.LVC_MA
                WHERE v.VC_TRANGTHAI = 'Hoạt động'
                AND '$now' BETWEEN lv.LVC_NGAYBATDAU AND lv.LVC_NGAYKETTHUC
                AND $tam_tinh >= lv.LVC_MINGIATRI
                ORDER BY lv.LVC_TYLEGIAM DESC
                LIMIT 1";

$result_voucher = $conn->query($sql_voucher);
if($result_voucher && $result_voucher->num_rows >0){
  $voucher_applied = $result_voucher->fetch_assoc();
  // Tính số tiền được giảm 
  $voucher_discount = $tam_tinh * ($voucher_applied['LVC_TYLEGIAM'] / 100);

  //Gới hạn theo mức giảm tối đa
  if($voucher_discount > $voucher_applied['LVC_MAXGIATRI']){
    $voucher_discount = $voucher_applied['LVC_MAXGIATRI'];
  }
} else {
  $voucher_applied = null;
  $voucher_discount = 0;
}

$sql_vanchuyen = "SELECT DISTINCT dv.DVVC_MA, dv.DVVC_TEN
                  FROM don_vi_van_chuyen dv
                  JOIN phi_van_chuyen pvc ON dv.DVVC_MA = pvc.DVVC_MA
                  JOIN dinh_muc_khoang_cach kc ON kc.KC_MA = pvc.KC_MA
                  ORDER BY dv.DVVC_TEN";

$result_vanchuyen = $conn->query($sql_vanchuyen);

// Lấy hình thức thanh toán
$sql_hinhthuc = "SELECT HTTT_MA, HTTT_TEN FROM hinh_thuc_thanh_toan ORDER BY HTTT_MA";
$result_hinhthuc = $conn->query($sql_hinhthuc);

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thanh toán</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/home.css" rel="stylesheet">
<link href="../assets/css/pay.css" rel="stylesheet">
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

<div class="container-pay">
  <!-- BÊN TRÁI: THÔNG TIN NGƯỜI NHẬN -->
  <div class="left">
    <h3>Thông tin nhận hàng</h3>
    <form id="checkoutForm" method="POST" action="xuly_thanhtoan.php">
      <div class="form-group">
        <label>Họ tên</label>
        <input type="text" name="hoten" value="<?= htmlspecialchars($user['ND_HOTEN']) ?>" readonly>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['ND_EMAIL']) ?>" readonly>
      </div>

      <div class="form-group">
        <label>Số điện thoại</label>
        <input type="text" name="sdt" value="<?= htmlspecialchars($user['ND_SDT']) ?>" readonly>
      </div>

      <div class="form-group">
        <label>Địa chỉ</label>
        <textarea name="diachi" rows="2"><?= htmlspecialchars($user['ND_DIACHI']) ?></textarea>
      </div>

      <label>Địa chỉ giao hàng</label>
      <div class="css_select_div">
        <select class="css_select" id="tinh" name="tinh" title="Chọn Tỉnh Thành">
            <option value="0">Tỉnh Thành</option>
        </select> 
        <select class="css_select" id="quan" name="quan" title="Chọn Quận Huyện">
            <option value="0">Quận Huyện</option>
        </select> 
        <select class="css_select" id="phuong" name="phuong" title="Chọn Phường Xã">
            <option value="0">Phường Xã</option>
        </select>
      </div>

      <div class="form-group">
        <label>Địa chỉ giao cụ thể (Số nhà,  tên đường...)</label>
        <input type="text" id="diaChiChiTiet" class="form-control" placeholder="VD: 12 Đ. Nguyễn Đình Chiểu" required>
      </div>

      <div class="van-chuyen">
        <label>Đơn vị vận chuyển</label>
        <div style="display: flex; align-items: center; gap: 8px;">
          <select id="dv-vc" name="dvvc_ma" required class="css_select">
            <option value="">-- Chọn đơn vị vận chuyển --</option>
            <?php
              if ($result_vanchuyen && $result_vanchuyen->num_rows > 0) {
                while ($row = $result_vanchuyen->fetch_assoc()) {
                  echo "<option value='{$row['DVVC_MA']}'>{$row['DVVC_TEN']}</option>";
                }
              } else {
                echo "<option value=''>Không có đơn vị vận chuyển</option>";
              }
            ?>
          </select>

          <!-- Nút xem bảng giá -->
          <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#modalPhiVanChuyen" style="font-size:14px;">
            <i class="fa-solid fa-circle-info"></i> Xem bảng giá
          </button>
        </div>
      </div>


      <div class="form-group payment-method">
        <label>Phương thức thanh toán</label>
        <select name="thanhtoan" class="css_select" required>
          <?php if($result_hinhthuc->num_rows > 0): ?>
          <?php while($row = $result_hinhthuc->fetch_assoc()): ?>
            <option value="<?= $row['HTTT_MA'] ?>">
              <?= htmlspecialchars($row['HTTT_TEN']) ?>
            </option>
          <?php endwhile; ?>
          <?php else: ?>
            <option value="">Chưa có phương thức thanh toán</option>
          <?php endif; ?>
        </select>
      </div>
  </div>

  <!-- BÊN PHẢI: THÔNG TIN ĐƠN HÀNG -->
  <div class="right">
    <h3>Đơn hàng (<?= count($cart_items) ?> sản phẩm)</h3>

    <?php if (empty($cart_items)) { ?>
      <p>Giỏ hàng của bạn đang trống!</p>
    <?php } else { 
      foreach ($cart_items as $item) { ?>
        <div class="product-item">
          <img src="<?= htmlspecialchars($item['ANH_DAU'] ?? 'logo.png') ?>" alt="<?= htmlspecialchars($item['SP_TEN']) ?>">
          <div class="product-info">
            <strong><?= htmlspecialchars($item['SP_TEN']) ?></strong><br>
            Size: <?= htmlspecialchars($item['KichThuoc']) ?><br>
            SL: <?= $item['CTGH_SOLUONG'] ?>
          </div>
          <div class="product-price">
            <?= number_format($item['CTGH_SOLUONG'] * $item['CTGH_DONGIA'], 0, ',', '.') ?> đ
          </div>
        </div>
      <?php } ?>
    <?php } ?>

    <?php if ($voucher_applied) { ?>
      <div class="voucher-box success">
        Mã giảm giá tự động áp dụng: 
        <strong><?= htmlspecialchars($voucher_applied['VC_TEN']) ?></strong>
        (- <?= rtrim(rtrim(number_format($voucher_applied['LVC_TYLEGIAM'], 2), '0'), '.') ?>%)
        <br>
        Giảm được: <strong><?= number_format($voucher_discount, 0, ',', '.') ?> đ</strong>
      </div>
    <?php } else { ?>
      <div class="voucher-box warning">
        Voucher: Chưa đủ điều kiện áp dụng mã giảm giá.
      </div>
    <?php } ?>

    <div class="summary">
      <?php $tong_sau_voucher = $tam_tinh - $voucher_discount; ?>
      <p>Tạm tính: <strong><?= number_format($tam_tinh, 0, ',', '.') ?> đ</strong></p>
      <p>Giảm giá: <strong>- <?= number_format($voucher_discount, 0, ',', '.') ?> đ</strong></p>
      <p>Phí giao hàng: <span id="phiGiao">-------</span></p>
      <p class="total">Tổng cộng: <span id="tongCong"><?= number_format($tong_sau_voucher, 0, ',', '.') ?> đ</span></p>

      <input type="hidden" name="tam_tinh" value="<?= $tam_tinh ?>">
      <input type="hidden" name="voucher_ma" value="<?= isset($voucher_applied['VC_MA']) ? $voucher_applied['VC_MA'] : '' ?>">
      <input type="hidden" name="voucher_giam" value="<?= $voucher_discount ?>">
      <input type="hidden" id="phiGiaoInput" name="phi_giao" value="0">
      <input type="hidden" id="tongCongInput" name="tong_cong" value="<?= $tong_sau_voucher ?>">
      <input type="hidden" name="dia_chi_nhan" id="dia_chi_nhan" value="">

      <button type="submit">ĐẶT HÀNG</button>
      <a href="cart.php" class="btn-back-cart"> <i class="fa-solid fa-arrow-left"></i> Quay lại giỏ hàng</a>
    </div>
  </div>
</form>
</div>


<!-- Modal hiển thị bảng giá vận chuyển -->
<div class="modal fade" id="modalPhiVanChuyen" tabindex="-1" aria-labelledby="modalPhiVanChuyenLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalPhiVanChuyenLabel">Bảng giá vận chuyển</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>

      <div class="modal-body">
        <table class="table table-bordered text-center align-middle">
          <thead class="table-light">
            <tr>
              <th>Đơn vị vận chuyển</th>
              <th>Khoảng cách Min (km)</th>
              <th>Khoảng cách Max (km)</th>
              <th>Giá (VNĐ)</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $sqlGia = "SELECT 
                            dvvc.DVVC_TEN, 
                            dmkc.KC_MIN, 
                            dmkc.KC_MAX, 
                            pvc.PVC_GIAGIAO
                          FROM phi_van_chuyen pvc
                          LEFT JOIN don_vi_van_chuyen dvvc ON pvc.DVVC_MA = dvvc.DVVC_MA
                          LEFT JOIN dinh_muc_khoang_cach dmkc ON pvc.KC_MA = dmkc.KC_MA";
              
              $resultGia = $conn->query($sqlGia);
              if ($resultGia && $resultGia->num_rows > 0) {
                while ($row = $resultGia->fetch_assoc()) {
                  echo "<tr>
                    <td>" . htmlspecialchars($row['DVVC_TEN']) . "</td>
                    <td>" . htmlspecialchars($row['KC_MIN']) . "</td>
                    <td>" . htmlspecialchars($row['KC_MAX']) . "</td>
                    <td>" . number_format($row['PVC_GIAGIAO'], 0, ',', '.') . "</td>
                  </tr>";
                }
              } else {
                echo "<tr><td colspan='4'>Chưa có dữ liệu bảng giá vận chuyển</td></tr>";
              }
            ?>
          </tbody>
        </table>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
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


<script src="https://esgoo.net/scripts/jquery.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function updateCartCount() {
    const countEl = document.getElementById('cart-count');
    const ndMa = document.body.dataset.ndMa || null;
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
        if (ndMa) openCart();
      });
    }
  });



$(document).ready(function() {

    // Lấy danh sách Tỉnh/Thành
    $.getJSON('https://esgoo.net/api-tinhthanh/1/0.htm', function(data_tinh) {
        if (data_tinh.error === 0) {
            $.each(data_tinh.data, function(_, val_tinh) {
                $("#tinh").append(`<option value="${val_tinh.id}">${val_tinh.full_name}</option>`);
            });
        }
    });

    // Khi chọn Tỉnh/Thành
    $("#tinh").change(function() {
        const idTinh = $(this).val();
        $("#quan").html('<option value="0">Quận Huyện</option>');
        $("#phuong").html('<option value="0">Phường Xã</option>');

        if (idTinh === "0") return;

        $.getJSON(`https://esgoo.net/api-tinhthanh/2/${idTinh}.htm`, function(data_quan) {
            if (data_quan.error === 0) {
                $.each(data_quan.data, function(_, val_quan) {
                    $("#quan").append(`<option value="${val_quan.id}">${val_quan.full_name}</option>`);
                });
            }
        });
    });

    // Khi chọn Quận/Huyện
    $("#quan").change(function() {
        const idQuan = $(this).val();
        $("#phuong").html('<option value="0">Phường Xã</option>');

        if (idQuan === "0") return;

        $.getJSON(`https://esgoo.net/api-tinhthanh/3/${idQuan}.htm`, function(data_phuong) {
            if (data_phuong.error === 0) {
                $.each(data_phuong.data, function(_, val_phuong) {
                    $("#phuong").append(`<option value="${val_phuong.id}">${val_phuong.full_name}</option>`);
                });
            }
        });
    });

    // Khi chọn Đơn vị vận chuyển
$("#dv-vc").change(function() {
    const dvvc_ma = $(this).val();
    if (!dvvc_ma) return;

    const tinh = $("#tinh option:selected").text();
    const quan = $("#quan option:selected").text();
    const phuong = $("#phuong option:selected").text();

    if (tinh === "Tỉnh Thành" || quan === "Quận Huyện" || phuong === "Phường Xã") {
        alert("Vui lòng chọn đầy đủ Tỉnh, Quận, Phường!");
        return;
    }

    const fullAddress = `${phuong}, ${quan}, ${tinh}, Việt Nam`;
    console.log("Địa chỉ gửi tới:", fullAddress);

    // Gọi PHP lấy toạ độ
    fetch(`get_location_mapbox.php?address=${encodeURIComponent(fullAddress)}`)
      .then(res => res.json())
      .then(data => {
        if (!data || data.length === 0) {
            // Nếu không lấy được tọa độ, mặc định phí = 0
            $("#phiGiao").text("0 đ");
            $("#tongCong").text((<?= $tam_tinh - $voucher_discount ?>).toLocaleString() + " đ");
            $("#phiGiaoInput").val(0);
            $("#tongCongInput").val(<?= $tam_tinh - $voucher_discount ?>);
            alert("Xin lỗi, không xác định được vị trí giao hàng. Shop sẽ liên hệ bạn sớm nhất để tính phí vận chuyển.");
            return;
        }

        const userLat = parseFloat(data[0].lat);
        const userLng = parseFloat(data[0].lon);

        // Gọi PHP tính phí vận chuyển
        fetch(`tinh_phi_vanchuyen.php?dvvc_ma=${dvvc_ma}&lat=${userLat}&lng=${userLng}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const phi = parseFloat(data.phi);
              const kc = parseFloat(data.kc);
              const tongHienTai = parseFloat(<?= $tam_tinh - $voucher_discount ?>);
              const tongMoi = tongHienTai + phi;

              $("#phiGiao").text(phi.toLocaleString() + " đ (≈ " + kc.toFixed(1) + " km)");
              $("#tongCong").text(tongMoi.toLocaleString() + " đ");
              $("#phiGiaoInput").val(phi);
              $("#tongCongInput").val(tongMoi);
            } else {
              // Nếu PHP không trả phí hợp lệ, mặc định 0
              $("#phiGiao").text("0 đ");
              $("#tongCong").text((<?= $tam_tinh - $voucher_discount ?>).toLocaleString() + " đ");
              $("#phiGiaoInput").val(0);
              $("#tongCongInput").val(<?= $tam_tinh - $voucher_discount ?>);
              alert("Xin lỗi, không tìm thấy mức phí vận chuyển phù hợp. Shop sẽ liên hệ bạn sớm nhất.");
            }
          })
          .catch(err => {
            console.error("Lỗi tính phí vận chuyển:", err);
            $("#phiGiao").text("0 đ");
            $("#tongCong").text((<?= $tam_tinh - $voucher_discount ?>).toLocaleString() + " đ");
            $("#phiGiaoInput").val(0);
            $("#tongCongInput").val(<?= $tam_tinh - $voucher_discount ?>);
            alert("Xin lỗi, không thể tính phí vận chuyển. Shop sẽ liên hệ bạn sớm nhất.");
          });
      })
      .catch(err => {
        console.error("Lỗi lấy vị trí:", err);
        $("#phiGiao").text("0 đ");
        $("#tongCong").text((<?= $tam_tinh - $voucher_discount ?>).toLocaleString() + " đ");
        $("#phiGiaoInput").val(0);
        $("#tongCongInput").val(<?= $tam_tinh - $voucher_discount ?>);
        alert("Xin lỗi, không thể xác định vị trí để tính phí. Shop sẽ liên hệ bạn sớm nhất.");
      });
  });
});

// KIỂM TRA TRƯỚC KHI GỬI FORM THANH TOÁN
$("#checkoutForm").on("submit", function (e) {
  const tinh = $("#tinh option:selected").text();
  const quan = $("#quan option:selected").text();
  const phuong = $("#phuong option:selected").text();
  const dvvc = $("#dv-vc").val();
  const thanhtoan = $("select[name='thanhtoan']").val();
  const diaChiChiTiet = $("#diaChiChiTiet").val().trim();

  if (
    tinh === "Tỉnh Thành" ||
    quan === "Quận Huyện" ||
    phuong === "Phường Xã" ||
    diaChiChiTiet === "" ||
    !dvvc ||
    !thanhtoan
  ) {
    e.preventDefault();
    alert("Vui lòng nhập đầy đủ địa chỉ (số nhà, tên đường) và chọn đủ Tỉnh/Quận/Phường, Đơn vị vận chuyển, Hình thức thanh toán!");
    return false;
  }

  // Ghép địa chỉ đầy đủ để gửi về PHP
  const fullAddress = `${diaChiChiTiet}, ${phuong}, ${quan}, ${tinh}, Việt Nam`;

  // Nếu chưa có input ẩn thì tạo mới
  let addressInput = $("input[name='dia_chi_nhan']");
  if (addressInput.length === 0) {
    $("<input>")
      .attr({
        type: "hidden",
        name: "dia_chi_nhan",
        value: fullAddress
      })
      .appendTo("#checkoutForm");
  } else {
    addressInput.val(fullAddress);
  }
});


</script>

</body>
</html>
