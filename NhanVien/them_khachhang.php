<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

// THÔNG BÁO
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $hoten = trim($_POST['hoten']);
    $email = trim($_POST['email']);
    $sdt = trim($_POST['sdt']);
    $diachi = trim($_POST['diachi']);
    $matkhau = trim($_POST['matkhau']); // Lấy mật khẩu

    // ======= KIỂM TRA RÀNG BUỘC =======
    if (empty($hoten) || empty($email) || empty($sdt) || empty($diachi) || empty($matkhau)) {
        $message = "Vui lòng nhập đầy đủ thông tin!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ!";
    } elseif (!preg_match('/^[0-9]{10}$/', $sdt)) {
        $message = "Số điện thoại phải gồm 10 chữ số!";
    } elseif (strlen($matkhau) < 3) {
        $message = "Mật khẩu phải ít nhất 3 ký tự!";
    } else {

        // KIỂM TRA EMAIL TRÙNG
        $check = $conn->prepare("SELECT ND_MA FROM nguoi_dung WHERE ND_EMAIL = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email đã tồn tại!";
        } else {

            // MÃ HÓA MẬT KHẨU
            $matkhau_hash = password_hash($matkhau, PASSWORD_DEFAULT);

            // THÊM NGƯỜI DÙNG
            $stmt = $conn->prepare("
                INSERT INTO nguoi_dung (ND_HOTEN, ND_EMAIL, ND_MATKHAU, ND_SDT, ND_DIACHI)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $hoten, $email, $matkhau_hash, $sdt, $diachi);
            
            if ($stmt->execute()) {
                $nd_ma = $stmt->insert_id;

                // THÊM KHÁCH HÀNG
                $stmt2 = $conn->prepare("INSERT INTO khach_hang (ND_MA, KH_DIEMTICHLUY) VALUES (?, 0)");
                $stmt2->bind_param("i", $nd_ma);
                $stmt2->execute();

                $message = "Thêm khách hàng thành công!";
            } else {
                $message = "Lỗi khi thêm dữ liệu!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thêm khách hàng</title>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
    margin: 0;
    padding: 0;
}

.form-box {
    width: 500px;
    margin: 50px auto;
    background: #ffffffcc; 
    backdrop-filter: blur(10px);
    padding: 40px 50px; 
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    transition: transform 0.3s ease;
}

.form-box:hover {
    transform: translateY(-5px);
}

h2 {
    text-align: center;
    margin-bottom: 30px;
    color: #1e3a8a;
    font-weight: 700;
    letter-spacing: 1px;
}

input {
    width: calc(100% - 30px); 
    padding: 14px 20px;
    margin-top: 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 16px;
    transition: 0.3s;
}

input:focus {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
    outline: none;
}

button {
    width: calc(100% + 10px);
    padding: 14px 20px; 
    margin-top: 25px;
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #007bff, #00c6ff);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
}

button:hover {
    background: linear-gradient(135deg, #0056b3, #0099cc);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 123, 255, 0.35);
}

.message, .success {
    padding: 12px 20px; /* padding rộng hơn */
    border-radius: 8px;
    font-weight: 500;
    text-align: center;
    margin-top: 15px;
}

.message {
    background-color: #ffe6e6;
    color: #e74c3c;
    border: 1px solid #e74c3c;
}

.success {
    background-color: #e6ffed;
    color: #27ae60;
    border: 1px solid #27ae60;
}

.btn-back {
    display: inline-block;
    margin-top: 25px;
    text-decoration: none;
    color: #1cd857;
    font-weight: 600;
    transition: 0.3s;
    padding: 8px 16px;
    border-radius: 8px
}

.btn-back:hover {
    background: #38963d;
    color: white;
    box-shadow: 0 3px 10px rgba(52, 152, 219, 0.3);
}

</style>
</head>

<body>

<div class="form-box">
    <h2>THÊM KHÁCH HÀNG</h2>

    <?php if ($message != ""): ?>
        <p class="<?= strpos($message, 'thành công') !== false ? 'success' : 'message' ?>">
            <?= $message ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="hoten" placeholder="Họ và tên" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="sdt" placeholder="Số điện thoại" required>
        <input type="text" name="diachi" placeholder="Địa chỉ" required>
        <input type="password" name="matkhau" placeholder="Mật khẩu" required>
        <button type="submit">Thêm khách hàng</button>
    </form>
    <a href="quanly_nguoidung.php" class="btn-back">← Quay lại Quản lý người dùng</a>
</div>

</body>
</html>
