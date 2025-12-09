<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if($conn->connect_error) die("Kết nối thất bại: ".$conn->connect_error);

$dvvc = $_POST['DVVC_MA'] ?? '';
$kc = $_POST['KC_MA'] ?? '';
$gia = intval($_POST['PVC_GIAGIAO'] ?? 0);

if(!$dvvc || !$kc) { echo "Thiếu thông tin"; exit; }
if($gia < 1000) { echo "Giá phải >= 1000 VNĐ"; exit; }

$sql = "UPDATE phi_van_chuyen SET PVC_GIAGIAO=$gia WHERE DVVC_MA=$dvvc AND KC_MA=$kc";
if($conn->query($sql)) echo "OK";
else echo $conn->error;
?>
