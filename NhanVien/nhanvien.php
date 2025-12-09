<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

// Kiểm tra đăng nhập
if (!isset($_SESSION['nd_ma']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'nhanvien') {
    echo "<script>
        alert('Bạn không có quyền truy cập trang này!');
        window.location='../Mode/dangnhap.php';
    </script>";
    exit;
}

$nd_ma = $_SESSION['nd_ma'];

// Lấy thông tin nhân viên
$sql = "SELECT n.ND_HOTEN, n.ND_EMAIL, n.ND_SDT, n.ND_DIACHI, v.NV_CCCD
        FROM nguoi_dung n
        JOIN nhan_vien v ON n.ND_MA = v.ND_MA
        WHERE n.ND_MA = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $nd_ma);
$stmt->execute();
$result = $stmt->get_result();
$nv = $result->fetch_assoc();

// ====== LẤY SỐ LIỆU THỐNG KÊ ====== //
$count_donhang = $conn->query("SELECT COUNT(*) AS total FROM don_hang")->fetch_assoc()['total'];
$count_khachhang = $conn->query("SELECT COUNT(*) AS total FROM khach_hang")->fetch_assoc()['total'];
$count_sanpham = $conn->query("SELECT COUNT(*) AS total FROM san_pham")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Nhân viên MODÉ Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/nhanvien.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <h3><i class="fa-solid fa-user-tie"></i> NHÂN VIÊN MODÉ</h3>
  <a href="nhanvien.php" class="active"><i class="fa-solid fa-house"></i> Trang chủ</a>
  <a href="quanly_donhang.php"><i class="fa-solid fa-box"></i> Quản lý đơn hàng</a>
  <a href="quanly_thanhtoan.php"><i class="fas fa-credit-card"></i> Quản lý thanh toán</a>
  <a href="quanly_nguoidung.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
  <a href="quanly_danhmuc.php"><i class="fa-solid fa-list"></i> Quản lý danh mục</a>
  <a href="quanly_sanpham.php"><i class="fa-solid fa-shirt"></i> Quản lý sản phẩm</a>
  <a href="quanly_phigiao.php"><i class="fa-solid fa-truck"></i> Quản lý phí giao</a>
  <a href="thongke.php"><i class="fa-solid fa-chart-line"></i> Thống kê</a>
  <hr class="text-light">
  <a href="../Mode/dangxuat.php"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
</div>

<!-- Content -->
<div class="content">
  <div class="welcome">
    <h2>👋 Xin chào, <?= htmlspecialchars($nv['ND_HOTEN']) ?>!</h2>
    <button onclick="window.location='taikhoan_nhanvien.php'" class="btn-user">
      <i class="fa-solid fa-user icon-user"></i> Tài Khoản
    </button>
  </div>

  <div class="stats">
    <div class="stat-box">
      <i class="fa-solid fa-box"></i>
      <h5>Đơn Hàng</h5>
      <p><b><?= $count_donhang ?></b></p>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #fbc2eb, #a6c1ee);">
      <i class="fa-solid fa-users"></i>
      <h5>Khách Hàng</h5>
      <p><b><?= $count_khachhang ?></b></p>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #fad0c4, #ffd1ff);">
      <i class="fa-solid fa-shirt"></i>
      <h5>Sản Phẩm</h5>
      <p><b><?= $count_sanpham ?></b></p>
    </div>
  </div>

</div>
</body>
</html>