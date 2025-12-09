<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if($conn->connect_error){
    die("Kết nối thất bại: ".$conn->connect_error);
}

// Lấy dữ liệu POST
$dvvc = $_POST['DVVC_MA'] ?? '';
$kc = $_POST['KC_MA'] ?? '';
$gia = intval($_POST['PVC_GIAGIAO'] ?? 0);

// Kiểm tra ràng buộc
if(!$dvvc || !$kc){
    echo "<script>alert('Vui lòng chọn đủ đơn vị vận chuyển và khoảng cách'); window.history.back();</script>";
    exit;
}
if($gia < 1000){
    echo "<script>alert('Phí giao phải >= 1000 VNĐ'); window.history.back();</script>";
    exit;
}

// Kiểm tra đã tồn tại chưa
$sqlCheck = "SELECT * FROM phi_van_chuyen WHERE DVVC_MA=$dvvc AND KC_MA=$kc";
$resCheck = $conn->query($sqlCheck);
if($resCheck->num_rows > 0){
    echo "<script>alert('Phí giao cho đơn vị và khoảng cách này đã tồn tại'); window.history.back();</script>";
    exit;
}

// Thêm mới
$sql = "INSERT INTO phi_van_chuyen (DVVC_MA, KC_MA, PVC_GIAGIAO) VALUES ($dvvc, $kc, $gia)";
if($conn->query($sql)){
    echo "<script>alert('Thêm phí giao thành công'); window.location.href='quanly_phigiao.php';</script>";
} else {
    echo "<script>alert('Có lỗi xảy ra: ".$conn->error."'); window.history.back();</script>";
}
?>
