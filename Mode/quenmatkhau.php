<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

$thongbao = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Kiểm tra xem email có tồn tại không
    $sql = "SELECT * FROM nguoi_dung WHERE ND_EMAIL = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Tạo mật khẩu mới ngẫu nhiên
        $new_pass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);

        // Cập nhật mật khẩu mới
        $update = $conn->prepare("UPDATE nguoi_dung SET ND_MATKHAU = ? WHERE ND_EMAIL = ?");
        $update->bind_param("ss", $new_pass, $email);
        $update->execute();

        $thongbao = "<div class='alert alert-success text-center mt-3'>
                        Mật khẩu mới của bạn là: <strong>$new_pass</strong><br>
                        Vui lòng đăng nhập và đổi mật khẩu sau khi vào hệ thống.
                     </div>";
    } else {
        $thongbao = "<div class='alert alert-danger text-center mt-3'>
                        Email không tồn tại trong hệ thống!
                     </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quên mật khẩu - MODÉ</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/login.css" rel="stylesheet">
</head>
<body>

<div class="center-page">
  <div class="login-wrapper">
    <div class="login-left"></div>
    <div class="login-right">
      <h2>Quên mật khẩu</h2>
      <form method="post">
        <input type="email" name="email" placeholder="Nhập email đã đăng ký" required>
        <button type="submit" class="btn-login">Lấy lại mật khẩu</button>
      </form>
      <?= $thongbao ?>
      <p class="text-center mt-3">
        <a href="dangnhap.php" style="color:#007bff; text-decoration:none;">Quay lại đăng nhập</a>
      </p>
    </div>
  </div>
</div>

</body>
</html>
