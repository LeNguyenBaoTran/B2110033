<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Kết nối CSDL thất bại']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Không nhận được dữ liệu JSON']);
    exit;
}

$nd_ma = intval($data['ND_MA']);
$sp_ma = intval($data['SP_MA']);
$kt_ma = intval($data['KT_MA']);
$qty   = intval($data['qty']);
$price = floatval($data['price']);

if (!$nd_ma || !$sp_ma || !$kt_ma || !$qty) {
    echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu']);
    exit;
}

// Lấy GH_MA của người dùng
$stmtGH = $conn->prepare("SELECT GH_MA FROM gio_hang WHERE ND_MA=?");
$stmtGH->bind_param("i", $nd_ma);
$stmtGH->execute();
$resGH = $stmtGH->get_result();

if ($resGH->num_rows > 0) {
    $gh_ma = $resGH->fetch_assoc()['GH_MA'];
} else {
    // Tạo giỏ mới nếu chưa có
    $stmtInsertGH = $conn->prepare("INSERT INTO gio_hang (ND_MA) VALUES (?)");
    $stmtInsertGH->bind_param("i", $nd_ma);
    $stmtInsertGH->execute();
    $gh_ma = $conn->insert_id;
}

// Kiểm tra chi tiết giỏ hàng có tồn tại sản phẩm + size chưa
$stmtCheck = $conn->prepare("SELECT CTGH_SOLUONG FROM chi_tiet_gio_hang WHERE GH_MA=? AND SP_MA=? AND KT_MA=?");
$stmtCheck->bind_param("iii", $gh_ma, $sp_ma, $kt_ma);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows > 0) {
    // Update số lượng
    $row = $resCheck->fetch_assoc();
    $newQty = $row['CTGH_SOLUONG'] + $qty;
    $stmtUpdate = $conn->prepare("UPDATE chi_tiet_gio_hang SET CTGH_SOLUONG=? WHERE GH_MA=? AND SP_MA=? AND KT_MA=?");
    $stmtUpdate->bind_param("iiii", $newQty, $gh_ma, $sp_ma, $kt_ma);
    $stmtUpdate->execute();
} else {
    // Insert mới
    $stmtInsert = $conn->prepare("INSERT INTO chi_tiet_gio_hang (GH_MA, SP_MA, KT_MA, CTGH_SOLUONG, CTGH_DONGIA)
                                  VALUES (?, ?, ?, ?, ?)");
    $stmtInsert->bind_param("iiiid", $gh_ma, $sp_ma, $kt_ma, $qty, $price);
    $stmtInsert->execute();
}

echo json_encode(['success' => true]);
?>
