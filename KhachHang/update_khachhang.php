<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if (!isset($_SESSION['nd_ma'])) {
    die("Bạn chưa đăng nhập");
}

$nd_ma = $_SESSION['nd_ma'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field = $_POST['field'];
    $value = trim($_POST['value']);

    // Kiểm tra trường hợp email
    if ($field == "email" && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        die("Email không hợp lệ");
    }

    // Kiểm tra số điện thoại
    if ($field == "sdt" && !preg_match('/^[0-9]{10}$/', $value)) {
        die("Số điện thoại không hợp lệ");
    }

    // Kiểm tra địa chỉ
    if ($field == "diachi" && strlen($value) < 5) {
        die("Địa chỉ quá ngắn");
    }

    // Map field sang cột DB
    $column = [
        "email" => "ND_EMAIL",
        "sdt" => "ND_SDT",
        "diachi" => "ND_DIACHI"
    ];

    if (!isset($column[$field])) die("Lỗi trường");

    $col = $column[$field];

    $stmt = $conn->prepare("UPDATE nguoi_dung SET $col=? WHERE ND_MA=?");
    $stmt->bind_param("si", $value, $nd_ma);

    if ($stmt->execute()) {
        header("Location: khachhang.php?update=success");
    } else {
        echo "Lỗi khi cập nhật!";
    }
}
?>
