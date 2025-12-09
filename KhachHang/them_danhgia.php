<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

$nd_ma = $_SESSION['nd_ma'];
$dh_ma = $_POST['DH_MA'];
$sp_ma = $_POST['SP_MA'];
$sosao = intval($_POST['SOSAO']);
$noidung = $_POST['NOIDUNG'];

// 1. Thêm hoặc cập nhật đánh giá
$sql_check = "SELECT * FROM PHAN_HOI WHERE ND_MA='$nd_ma' AND SP_MA='$sp_ma'";
$res_check = $conn->query($sql_check);

if($res_check->num_rows > 0){
    $sql_update = "UPDATE PHAN_HOI 
                   SET PH_SOSAO='$sosao', PH_NOIDUNG='$noidung', PH_NGAYGIO=NOW() 
                   WHERE ND_MA='$nd_ma' AND SP_MA='$sp_ma'";
    $conn->query($sql_update);
} else {
    $sql_insert = "INSERT INTO PHAN_HOI (ND_MA, SP_MA, PH_SOSAO, PH_NOIDUNG, PH_NGAYGIO) 
                   VALUES ('$nd_ma', '$sp_ma', '$sosao', '$noidung', NOW())";
    $conn->query($sql_insert);
}

// 2. Kiểm tra xem người dùng đã đánh giá hết sản phẩm trong đơn chưa
$sql_items = "SELECT COUNT(*) AS total_sp FROM CHI_TIET_DON_HANG WHERE DH_MA='$dh_ma'";
$total_sp = $conn->query($sql_items)->fetch_assoc()['total_sp'];

$sql_rated = "SELECT COUNT(*) AS rated_sp 
              FROM PHAN_HOI ph
              JOIN CHI_TIET_DON_HANG ctdh ON ph.SP_MA = ctdh.SP_MA
              WHERE ph.ND_MA='$nd_ma' AND ctdh.DH_MA='$dh_ma'";
$rated_sp = $conn->query($sql_rated)->fetch_assoc()['rated_sp'];

// 3. Nếu đánh giá hết, cộng điểm tích lũy
if($rated_sp >= $total_sp){
    $sql_dh = "SELECT DH_TONGTHANHTOAN FROM DON_HANG WHERE DH_MA='$dh_ma'";
    $dh = $conn->query($sql_dh)->fetch_assoc();
    $diem = floor($dh['DH_TONGTHANHTOAN'] / 10000); // 1% => 1.000.000₫ = 100 điểm
    $sql_update_kh = "UPDATE KHACH_HANG SET KH_DIEMTICHLUY = KH_DIEMTICHLUY + $diem WHERE ND_MA='$nd_ma'";
    $conn->query($sql_update_kh);
}

header("Location: khachhang.php?tab=lotrinh");
exit;
?>
