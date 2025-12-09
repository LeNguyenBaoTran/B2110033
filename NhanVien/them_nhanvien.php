<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $hoten = trim($_POST["hoten"]);
    $email = trim($_POST["email"]);
    $matkhau = trim($_POST["matkhau"]);
    $sdt = trim($_POST["sdt"]);
    $diachi = trim($_POST["diachi"]);
    $cccd = trim($_POST["cccd"]);

    // ===== KIỂM TRA RÀNG BUỘC =====
    if ($hoten == "" || $email == "" || $matkhau == "" || $cccd == "") {
        $error = "Vui lòng nhập đầy đủ các trường bắt buộc!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } elseif (!preg_match('/^[0-9]{10}$/', $sdt) && $sdt != "") {
        $error = "Số điện thoại phải gồm đúng 10 chữ số!";
    } elseif (!preg_match('/^[0-9]{12}$/', $cccd)) {
        $error = "CCCD phải gồm đúng 12 chữ số!";
    } else {

        // KIỂM TRA EMAIL TRÙNG 
        $checkEmail = $conn->prepare("SELECT ND_MA FROM nguoi_dung WHERE ND_EMAIL = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $error = "Email này đã tồn tại trong hệ thống!";
        } else {

            // KIỂM TRA CCCD TRÙNG
            $checkCCCD = $conn->prepare("SELECT ND_MA FROM nhan_vien WHERE NV_CCCD = ?");
            $checkCCCD->bind_param("s", $cccd);
            $checkCCCD->execute();
            $checkCCCD->store_result();

            if ($checkCCCD->num_rows > 0) {
                $error = "CCCD này đã tồn tại trong hệ thống!";
            } else {

            
                //      THÊM NHÂN VIÊN
                $sql1 = "INSERT INTO nguoi_dung (ND_HOTEN, ND_EMAIL, ND_MATKHAU, ND_SDT, ND_DIACHI)
                         VALUES (?, ?, ?, ?, ?)";

                $stmt1 = $conn->prepare($sql1);
                $stmt1->bind_param("sssss", $hoten, $email, $matkhau, $sdt, $diachi);

                if ($stmt1->execute()) {

                    $nd_ma = $stmt1->insert_id;

                    $sql2 = "INSERT INTO nhan_vien (ND_MA, NV_CCCD) VALUES (?, ?)";
                    $stmt2 = $conn->prepare($sql2);
                    $stmt2->bind_param("is", $nd_ma, $cccd);
                    $stmt2->execute();

                    $success = "Thêm nhân viên thành công!";
                } else {
                    $error = "Lỗi khi thêm nhân viên!";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thêm nhân viên</title>

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

<div class="container">
    <div class="form-box">
        <h2>THÊM NHÂN VIÊN</h2>

        <?php if ($success) echo "<p class='success'>$success</p>"; ?>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>

        <form method="POST">

            <input type="text" name="hoten" placeholder="Họ tên" required>

            <input type="email" name="email" placeholder="Email" required>

            <input type="password" name="matkhau" placeholder="Mật khẩu" required>

            <input type="text" name="sdt" placeholder="Số điện thoại (10 số)">

            <input type="text" name="diachi" placeholder="Địa chỉ">

            <input type="text" name="cccd" placeholder="CCCD (12 số)" required>

            <button type="submit">Thêm Nhân Viên</button>
        </form>
        <a href="quanly_nguoidung.php" class="btn-back">← Quay lại Quản lý người dùng</a>
    </div>

</div>

</body>
</html>
