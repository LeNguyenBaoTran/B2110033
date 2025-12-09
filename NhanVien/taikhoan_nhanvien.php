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

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $sdt = trim($_POST['sdt']);
    $diachi = trim($_POST['diachi']);

    // Kiểm tra dữ liệu hợp lệ
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ! Phải có ký tự '@'.";
    } elseif (!preg_match('/^[0-9]{10}$/', $sdt)) {
        $error = "Số điện thoại phải gồm 10 chữ số và không có ký tự chữ!";
    } else {
        $stmt = $conn->prepare("UPDATE nguoi_dung SET ND_EMAIL=?, ND_SDT=?, ND_DIACHI=? WHERE ND_MA=?");
        $stmt->bind_param("sssi", $email, $sdt, $diachi, $nd_ma);
        if ($stmt->execute()) {
            $success = "Cập nhật thông tin thành công!";
        } else {
            $error = "Lỗi khi cập nhật thông tin. Vui lòng thử lại!";
        }
        $stmt->close();
    }
}

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Tài khoản nhân viên</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body {
  background: #f4f8fc;
  font-family: "Poppins", sans-serif;
}
.container {
  max-width: 600px;
  background: #fff;
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  margin-top: 80px;
}
h2 {
  text-align: center;
  color: #0277bd;
  margin-bottom: 25px;
}
.btn-save {
  background-color: #0288d1;
  color: #fff;
  border: none;
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  transition: 0.3s;
}
.btn-save:hover { background-color: #039be5; }
.alert { margin-bottom: 15px; }
</style>
</head>
<body>
<div class="container">
  <h2><i class="fa-solid fa-user"></i> Tài khoản của bạn</h2>

  <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
  <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label">Họ tên</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($nv['ND_HOTEN']) ?>" readonly>
    </div>

    <div class="mb-3">
      <label class="form-label">CCCD</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($nv['NV_CCCD']) ?>" readonly>
    </div>

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($nv['ND_EMAIL']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Số điện thoại</label>
      <input type="text" name="sdt" class="form-control" value="<?= htmlspecialchars($nv['ND_SDT']) ?>" required pattern="[0-9]{10}" maxlength="10">
    </div>

    <div class="mb-3">
      <label class="form-label">Địa chỉ</label>
      <input type="text" name="diachi" class="form-control" value="<?= htmlspecialchars($nv['ND_DIACHI']) ?>" required>
    </div>

    <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi</button>
    <a href="nhanvien.php" class="btn btn-outline-secondary w-100 mt-3"><i class="fa-solid fa-arrow-left"></i> Quay lại</a>
  </form>
</div>
</body>
</html>
