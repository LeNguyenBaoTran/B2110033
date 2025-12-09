<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// --- Kiểm tra người dùng đã đăng nhập ---
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để thực hiện thao tác này!'); window.location='../Mode/dangnhap.php';</script>";
    exit;
}

// --- Nhận mã đơn hàng cần hủy ---
$dh_ma = $_POST['dh_ma'] ?? null;
if (!$dh_ma) {
    echo "<script>alert('Không xác định được đơn hàng!'); window.location='lichsu_donhang.php';</script>";
    exit;
}

// --- Kiểm tra đơn hàng có thuộc về người dùng không ---
$nd_ma = $_SESSION['nd_ma'];
$check = $conn->query("SELECT * FROM DON_HANG WHERE DH_MA = '$dh_ma' AND ND_MA = '$nd_ma'");
if ($check->num_rows == 0) {
    echo "<script>alert('Bạn không có quyền hủy đơn hàng này!'); window.location='lichsu_donhang.php';</script>";
    exit;
}

// --- Cập nhật trạng thái đơn hàng ---
$new_status = "Đã hủy";
$sql_update = "UPDATE DON_HANG SET DH_TRANGTHAI = ? WHERE DH_MA = ?";
$stmt = $conn->prepare($sql_update);
$stmt->bind_param("si", $new_status, $dh_ma);
$stmt->execute();


//--- Cập nhật lại số lượng tồn trong bảng CHI_TIET_SAN_PHAM ---
$sql_ct = "SELECT SP_MA, KT_MA, CTDH_SOLUONG FROM CHI_TIET_DON_HANG WHERE DH_MA = ?";
$stmt_ct = $conn->prepare($sql_ct);
$stmt_ct->bind_param("i", $dh_ma);
$stmt_ct->execute();
$result_ct = $stmt_ct->get_result();

while ($row = $result_ct->fetch_assoc()) {
    $sp_ma = $row['SP_MA'];
    $kt_ma = $row['KT_MA'];
    $soluong = $row['CTDH_SOLUONG'];

    // Cộng lại số lượng tồn
    $sql_update_stock = "UPDATE CHI_TIET_SAN_PHAM 
                         SET CTSP_SOLUONGTON = CTSP_SOLUONGTON + ? 
                         WHERE SP_MA = ? AND KT_MA = ?";
    $stmt_stock = $conn->prepare($sql_update_stock);
    $stmt_stock->bind_param("iii", $soluong, $sp_ma, $kt_ma);
    $stmt_stock->execute();
}


// --- Lưu lịch sử đơn hàng ---
$sql_tt = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
$stmt_tt = $conn->prepare($sql_tt);
$stmt_tt->bind_param("s", $new_status);
$stmt_tt->execute();
$result_tt = $stmt_tt->get_result();
if ($result_tt && $result_tt->num_rows > 0) {
    $tt_ma = $result_tt->fetch_assoc()['TT_MA'];
    $thoidiem = date('Y-m-d H:i:s');
    $stmt_ls = $conn->prepare("INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) VALUES (?, ?, ?)");
    $stmt_ls->bind_param("iis", $dh_ma, $tt_ma, $thoidiem);
    $stmt_ls->execute();
}

echo "<script>
    alert('Đơn hàng đã được hủy và số lượng tồn đã được cập nhật!');
    window.location='lichsu_donhang.php';
</script>";
exit;
?>
