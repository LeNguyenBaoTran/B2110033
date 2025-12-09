<?php
// xuly_them_dvvc.php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy dữ liệu từ form
$DVVC_TEN = trim($_POST['DVVC_TEN'] ?? '');

if ($DVVC_TEN === '') {
    echo "<script>alert('Tên đơn vị vận chuyển không được để trống!'); window.history.back();</script>";
    exit;
}

// Kiểm tra trùng tên
$stmt_check = $conn->prepare("SELECT DVVC_MA FROM don_vi_van_chuyen WHERE DVVC_TEN = ?");
$stmt_check->bind_param("s", $DVVC_TEN);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo "<script>alert('Đơn vị vận chuyển này đã tồn tại!'); window.history.back();</script>";
    exit;
}
$stmt_check->close();

// Thêm vào CSDL
$stmt = $conn->prepare("INSERT INTO don_vi_van_chuyen (DVVC_TEN) VALUES (?)");
$stmt->bind_param("s", $DVVC_TEN);

if ($stmt->execute()) {
    echo "<script>alert('Thêm đơn vị vận chuyển thành công!'); window.location.href='quanly_phigiao.php';</script>";
} else {
    echo "<script>alert('Có lỗi xảy ra, vui lòng thử lại!'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
