<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// ===== KIỂM TRA ĐĂNG NHẬP =====
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để xem thông tin!'); window.location='../Mode/dangnhap.php';</script>";
    exit;
}

$nd_ma = $_SESSION['nd_ma'];

// ===== LẤY THÔNG TIN NGƯỜI DÙNG + KHÁCH HÀNG =====
$sql_kh = "
    SELECT nd.ND_MA, nd.ND_HOTEN, nd.ND_EMAIL, nd.ND_SDT, nd.ND_DIACHI, kh.KH_DIEMTICHLUY
    FROM nguoi_dung nd
    LEFT JOIN khach_hang kh ON nd.ND_MA = kh.ND_MA
    WHERE nd.ND_MA = '$nd_ma'
";
$result_kh = $conn->query($sql_kh);
$kh = $result_kh->fetch_assoc();

// ===== LẤY ĐƠN HÀNG ĐÃ HỦY =====
$sql_dhxoa = "SELECT dh.DH_MA, dh.DH_NGAYDAT, dh.DH_TRANGTHAI, dh.DH_TONGTIENHANG, 
           dh.DH_GIAMGIA, dh.DH_TONGTHANHTOAN, dh.DH_DIACHINHAN,
           dv.DVVC_TEN, v.VC_TEN
    FROM DON_HANG dh
    LEFT JOIN DON_VI_VAN_CHUYEN dv ON dh.DVVC_MA = dv.DVVC_MA
    LEFT JOIN VOUCHER v ON dh.VC_MA = v.VC_MA
    WHERE dh.ND_MA = '$nd_ma' AND dh.DH_TRANGTHAI = 'Đã hủy'
    ORDER BY dh.DH_NGAYDAT DESC";
$result_dhxoa = $conn->query($sql_dhxoa);

//Lấy đơn hàng đã giao
$sql_dhgiaohang = "SELECT dh.DH_MA, dh.DH_NGAYDAT, dh.DH_TRANGTHAI, dh.DH_TONGTIENHANG, dh.DH_GIAMGIA, dh.DH_TONGTHANHTOAN, dh.DH_DIACHINHAN, 
      dvvc.DVVC_TEN, vc.VC_TEN
      FROM don_hang dh
      LEFT JOIN don_vi_van_chuyen dvvc ON dh.DVVC_MA = dvvc.DVVC_MA
      LEFT JOIN voucher vc ON dh.VC_MA = vc.VC_MA
      WHERE dh.ND_MA = '$nd_ma' AND dh.DH_TRANGTHAI = 'Giao thành công'
      ORDER BY dh.DH_NGAYDAT DESC";
$result_dhgiaohang = $conn->query($sql_dhgiaohang);

// Lấy chi tiêt sản phẩm từng đơn
function getChiTietDonHang($conn, $dh_ma){
  $sql_chitietsp = "SELECT ctdh.DH_MA, ctdh.SP_MA, ctdh.CTDH_SOLUONG, ctdh.CTDH_DONGIA, sp.SP_TEN, kt.KT_TEN, 
  (SELECT a.ANH_DUONGDAN
   FROM anh_san_pham a
   WHERE a.SP_MA = sp.SP_MA
   LIMIT 1) AS SP_ANHDAIDIEN
  FROM chi_tiet_don_hang ctdh
  LEFT JOIN san_pham sp ON ctdh.SP_MA = sp.SP_MA
  LEFT JOIN kich_thuoc kt ON ctdh.KT_MA = kt.KT_MA
  WHERE ctdh.DH_MA = '$dh_ma'";

  $result_chitietsp = $conn->query($sql_chitietsp);
  $items = [];
  while($row = $result_chitietsp->fetch_assoc()) $items[] = $row;
  return $items;
}

// Lấy đánh giá hiện có
function getDanhGia($conn, $nd_ma, $sp_ma){
  $sql_danhgia = "SELECT * FROM PHAN_HOI WHERE ND_MA = '$nd_ma' AND SP_MA = '$sp_ma'";
  $result_danhgia = $conn->query($sql_danhgia);
  return $result_danhgia->fetch_assoc();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Trang Khách Hàng - MODÉ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="../assets/css/customer.css" rel="stylesheet">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm fixed-top">
  <div class="container">
    <div class="col-md-3 col-8">
      <a href="../Mode/trangchu.php" class="brand-wrap text-decoration-none d-flex align-items-center">
        <img src="../assets/images/logo.png" alt="Logo" class="logo me-2">
        <div>
          <div style="font-family:'Playfair Display', serif; font-weight:700; font-size:25px; color:#4682B4; letter-spacing:3px;">MODÉ</div>
          <div style="font-size:15px; color:#777">Thời trang nam nữ</div>
        </div>
      </a>
    </div>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto gap-3">
        <li class="nav-item"><a class="nav-link active-tab" onclick="showTab('gioithieu')">Giới Thiệu</a></li>
        <li class="nav-item"><a class="nav-link" onclick="showTab('taikhoan')">Tài Khoản</a></li>
        <li class="nav-item"><a class="nav-link" onclick="showTab('dahuy')">Đơn Hàng Đã Hủy</a></li>
        <li class="nav-item"><a class="nav-link" onclick="showTab('lotrinh')">Lịch Sử Đơn Hàng</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- NỘI DUNG -->
<div class="container" style="margin-top: 90px;">

  <!-- GIỚI THIỆU -->
  <section id="gioithieu">
    <div class="text-center mb-5">
      <h2>Chào mừng đến với MODÉ</h2>
      <p>Thời trang tinh tế cho cả nam và nữ, mang lại sự tự tin và phong cách hiện đại.</p>
    </div>
    <div class="row">
      <div class="col-md-8">
        <img src="../assets/images/anhbia.png" class="img-fluid rounded" alt="Cửa hàng MODÉ">
      </div>
      <div class="col-md-4">
        <h4>Bộ sưu tập nổi bật</h4>
        <ul>
          <li>Áo sơ mi, áo thun, quần tây, váy, đầm sang trọng</li>
          <li>Chất liệu cao cấp, thiết kế thời thượng</li>
          <li>Ưu đãi hấp dẫn dành riêng cho khách hàng thân thiết</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- THÔNG TIN TÀI KHOẢN -->
  <section id="taikhoan" class="d-none">
    <div class="card shadow-sm p-4">
      <div class="row align-items-center">
        <div class="col-md-4 text-center border-end">
          <img src="../assets/images/women_4.png" class="rounded-circle mb-3" width="150" height="150" alt="Avatar">
          <h4 class="mb-0"><?= htmlspecialchars($kh['ND_HOTEN']) ?></h4>
          <p class="text-primary mb-1">Khách hàng</p>
          <p>Điểm tích lũy: <b><?= number_format($kh['KH_DIEMTICHLUY']) ?></b></p>
        </div>

        <div class="col-md-8">
          <h5 class="fw-bold border-bottom pb-2 mb-3">Thông Tin Cá Nhân</h5>
          <div class="row mb-2 align-items-center">
            <div class="col-4 fw-bold">Email:</div>
            <div class="col-7"><?= htmlspecialchars($kh['ND_EMAIL']) ?></div>
            <div class="col-1 text-end">
              <i class="bi bi-pencil-square text-primary edit-btn" data-field="email" style="cursor:pointer;"></i>
            </div>
          </div>

          <div class="row mb-2 align-items-center">
            <div class="col-4 fw-bold">Số điện thoại:</div>
            <div class="col-7"><?= htmlspecialchars($kh['ND_SDT'] ?? 'Chưa cập nhật') ?></div>
            <div class="col-1 text-end">
              <i class="bi bi-pencil-square text-primary edit-btn" data-field="sdt" style="cursor:pointer;"></i>
            </div>
          </div>

          <div class="row mb-2 align-items-center">
            <div class="col-4 fw-bold">Địa chỉ:</div>
            <div class="col-7"><?= htmlspecialchars($kh['ND_DIACHI'] ?? 'Chưa cập nhật') ?></div>
            <div class="col-1 text-end">
              <i class="bi bi-pencil-square text-primary edit-btn" data-field="diachi" style="cursor:pointer;"></i>
            </div>
          </div>

          <div class="text-end mt-3">
            <a href="../Mode/dangxuat.php" class="btn btn-danger">Đăng xuất</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Modal chỉnh sửa thông tin -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editForm" method="POST" action="update_khachhang.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cập nhật thông tin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="field" id="field">
        <label id="labelText" class="form-label"></label>
        <input type="text" name="value" id="value" class="form-control" required>
        <small class="text-danger d-none" id="errorText"></small>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>


  <!-- ĐƠN HÀNG ĐÃ HỦY -->
  <section id="dahuy" class="d-none">
    <h3 class="text-center mb-4">ĐƠN HÀNG ĐÃ HỦY</h3>
    <?php if ($result_dhxoa && $result_dhxoa->num_rows > 0): ?>
      <table class="table table-bordered table-hover text-center align-middle">
        <thead class="custom-thead">
          <tr>
            <th>Mã đơn</th>
            <th>Ngày đặt</th>
            <th>Tổng tiền</th>
            <th>Giảm giá</th>
            <th>Thành tiền</th>
            <th>Địa chỉ nhận</th>
            <th>Đơn vị vận chuyển</th>
            <th>Voucher</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result_dhxoa->fetch_assoc()): ?>
            <tr>
              <td>#<?= $row['DH_MA'] ?></td>
              <td class="order-date">
                <?php $date = new DateTime($row['DH_NGAYDAT']); 
                    echo $date->format('d/m/Y'); 
                ?>
              </td>
              <td><?= number_format($row['DH_TONGTIENHANG']) ?> ₫</td>
              <td><?= number_format($row['DH_GIAMGIA']) ?> ₫</td>
              <td><?= number_format($row['DH_TONGTHANHTOAN']) ?> ₫</td>
              <td><?= htmlspecialchars($row['DH_DIACHINHAN']) ?></td>
              <td><?= $row['DVVC_TEN'] ?? '-' ?></td>
              <td><?= $row['VC_TEN'] ?? '-' ?></td>
              <td class="reset">
                <a class="btn-view-detail" href="chitiet_donhang.php?dh_ma=<?php echo $row['DH_MA']; ?>">
                    Xem Chi Tiết
                </a>
                <form action="datlai_donhang.php" method="POST" style="display:inline-block;">
                  <input type="hidden" name="dh_ma" value="<?= $row['DH_MA'] ?>">
                  <button type="submit" class="btn btn-success btn-sm">
                    <i class="fa fa-rotate-right"></i> Đặt Lại Hàng
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-center text-muted">Chưa có đơn hàng nào bị hủy.</p>
    <?php endif; ?>
  </section>

<!-- ĐƠN HÀNG ĐÃ GIAO, ĐÁNH GIÁ VÀ TÍCH ĐIỂM-->
<section id="lotrinh" class="d-none">
    <h3 class="text-center mb-4">ĐƠN HÀNG ĐÃ GIAO & ĐÁNH GIÁ</h3>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-end">Điểm tích lũy: <b><?= number_format($kh['KH_DIEMTICHLUY']) ?></b></p>

        <!-- Lọc theo khoảng ngày -->
        <div class="d-flex gap-2 align-items-center">
            <label for="startDate" class="mb-0">Từ:</label>
            <input type="date" id="startDate" class="form-control form-control-sm" style="width:150px;">
            <label for="endDate" class="mb-0">Đến:</label>
            <input type="date" id="endDate" class="form-control form-control-sm" style="width:150px;">
            <button id="filterBtn" class="btn btn-primary btn-sm">Lọc</button>
            <button id="resetBtn" class="btn btn-secondary btn-sm">Reset</button>
        </div>
    </div>

    <?php if($result_dhgiaohang && $result_dhgiaohang->num_rows>0): ?>
        <div class="accordion" id="accordionDonHang" style="max-height:600px; overflow-y:auto;">
        <?php while($dh = $result_dhgiaohang->fetch_assoc()):
            $items = getChiTietDonHang($conn, $dh['DH_MA']);
            $ngayDH = $dh['DH_NGAYDAT']; // YYYY-MM-DD
        ?>
            <div class="accordion-item" data-ngay="<?= $ngayDH ?>">
                <h2 class="accordion-header" id="heading<?= $dh['DH_MA'] ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $dh['DH_MA'] ?>" aria-expanded="false" aria-controls="collapse<?= $dh['DH_MA'] ?>">
                        Mã đơn: #<?= $dh['DH_MA'] ?> | Ngày: <?= (new DateTime($dh['DH_NGAYDAT']))->format('d/m/Y') ?> | Tổng: <?= number_format($dh['DH_TONGTHANHTOAN']) ?> ₫
                    </button>
                </h2>
                <div id="collapse<?= $dh['DH_MA'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $dh['DH_MA'] ?>" data-bs-parent="#accordionDonHang">
                    <div class="accordion-body">
                        <p><b>Địa chỉ nhận:</b> <?= htmlspecialchars($dh['DH_DIACHINHAN']) ?></p>
                        <p><b>Đơn vị vận chuyển:</b> <?= $dh['DVVC_TEN'] ?? '-' ?> | <b>Voucher:</b> <?= $dh['VC_TEN'] ?? '-' ?></p>

                        <table class="table table-bordered text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Ảnh</th>
                                    <th>Tên SP</th>
                                    <th>Số lượng</th>
                                    <th>Giá</th>
                                    <th>Đánh giá</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $item):
                                    $dg = getDanhGia($conn, $nd_ma, $item['SP_MA']);
                                ?>
                                <tr>
                                    <td><img src="<?= $item['SP_ANHDAIDIEN'] ?>" width="50" alt=""></td>
                                    <td><?= htmlspecialchars($item['SP_TEN']) ?></td>
                                    <td><?= $item['CTDH_SOLUONG'] ?></td>
                                    <td><?= number_format($item['CTDH_DONGIA']) ?> ₫</td>
                                    <td>
                                        <?php if($dg): ?>
                                            <div>⭐ <?= $dg['PH_SOSAO'] ?> | <?= htmlspecialchars($dg['PH_NOIDUNG']) ?></div>
                                        <?php else: ?>
                                            <form action="them_danhgia.php" method="POST" class="d-flex gap-2 justify-content-center align-items-center">
                                                <input type="hidden" name="DH_MA" value="<?= $dh['DH_MA'] ?>">
                                                <input type="hidden" name="SP_MA" value="<?= $item['SP_MA'] ?>">
                                                <input type="number" name="SOSAO" min="1" max="5" placeholder="⭐ 1-5" required class="form-control form-control-sm" style="width:70px;">
                                                <textarea name="NOIDUNG" placeholder="Nội dung đánh giá" required class="form-control form-control-sm"></textarea>
                                                <button class="btn btn-primary btn-sm">Gửi</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-muted">Chưa có đơn hàng đã giao.</p>
    <?php endif; ?>
</section>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function showTab(tabId) {
    document.querySelectorAll("section").forEach(sec => sec.classList.add("d-none"));
    document.getElementById(tabId).classList.remove("d-none");
    document.querySelectorAll(".nav-link").forEach(link => link.classList.remove("active-tab"));
    if(event) event.target.classList.add("active-tab"); // giữ để click nav vẫn active
  }

  // Đọc ?tab=lotrinh khi load trang
  window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab'); // Lấy giá trị tab từ URL

    if(tab) {
      showTab(tab);

      // đánh dấu nav-link tương ứng active
      const navLink = document.querySelector(`.nav-link[onclick="showTab('${tab}')"]`);
      if(navLink) navLink.classList.add('active-tab');
    } else {
      // Mặc định mở tab 'gioithieu'
      showTab('gioithieu');
    }
  });

  // Sửa thông tin
  document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    let field = this.dataset.field;
    let currentValue = this.parentElement.previousElementSibling.innerText.trim();

    document.getElementById("field").value = field;
    document.getElementById("value").value = currentValue;
    document.getElementById("labelText").innerText =
      (field === "email" ? "Email" : field === "sdt" ? "Số điện thoại" : "Địa chỉ");

    document.getElementById("errorText").classList.add("d-none");
    new bootstrap.Modal(document.getElementById("editModal")).show();
  });
});

document.getElementById("editForm").addEventListener("submit", function (e) {
  let value = document.getElementById("value").value.trim();
  let field = document.getElementById("field").value;
  let error = document.getElementById("errorText");

  error.classList.add("d-none");

  // Kiểm tra email
  if (field === "email") {
    let emailRegex = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
    if (!emailRegex.test(value)) {
      error.innerText = "Email không hợp lệ! Phải chứa @ và đúng định dạng.";
      error.classList.remove("d-none");
      e.preventDefault();
      return;
    }
  }

  // Kiểm tra SDT
  if (field === "sdt") {
    if (!/^[0-9]{10}$/.test(value)) {
      error.innerText = "Số điện thoại phải gồm 10 số và không chứa chữ!";
      error.classList.remove("d-none");
      e.preventDefault();
      return;
    }
  }

  // Kiểm tra địa chỉ
  if (field === "diachi" && value.length < 5) {
    error.innerText = "Địa chỉ quá ngắn!";
    error.classList.remove("d-none");
    e.preventDefault();
    return;
  }
});

</script>

<script>
document.getElementById('filterBtn').addEventListener('click', function() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    const items = document.querySelectorAll('#accordionDonHang .accordion-item');

    items.forEach(item => {
        const ngay = item.getAttribute('data-ngay');
        let show = true;

        if(start && ngay < start) show = false;
        if(end && ngay > end) show = false;

        item.style.display = show ? '' : 'none';
    });
});

document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.querySelectorAll('#accordionDonHang .accordion-item').forEach(item => item.style.display = '');
});
</script>

</body>
</html>
