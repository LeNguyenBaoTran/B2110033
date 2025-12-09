<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    echo json_encode(["error" => "Kết nối CSDL thất bại"]);
    exit;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['nd_ma'])) {
    echo json_encode(["error" => "Chưa đăng nhập"]);
    exit;
}

$nd_ma = intval($_SESSION['nd_ma']);

// Lấy giỏ hàng của người dùng
$sql = "SELECT GH_MA FROM gio_hang WHERE ND_MA = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $nd_ma);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["items" => [], "totalQty" => 0]);
    exit;
}

$row = $result->fetch_assoc();
$gh_ma = $row['GH_MA'];

// Lấy chi tiết giỏ hàng
$sql_items = "SELECT 
        ctgh.SP_MA, 
        sp.SP_TEN, 
        kt.KT_MA,    
        kt.KT_TEN, 
        ctgh.CTGH_SOLUONG AS qty, 
        ctgh.CTGH_DONGIA AS price, 
        (
            SELECT a.ANH_DUONGDAN
            FROM anh_san_pham a
            WHERE a.SP_MA = sp.SP_MA
            LIMIT 1
        ) AS SP_ANH
    FROM chi_tiet_gio_hang ctgh
    JOIN san_pham sp ON ctgh.SP_MA = sp.SP_MA
    JOIN kich_thuoc kt ON ctgh.KT_MA = kt.KT_MA
    WHERE ctgh.GH_MA = ?
    ORDER BY sp.SP_TEN ASC";

$stmt2 = $conn->prepare($sql_items);
$stmt2->bind_param("i", $gh_ma);
$stmt2->execute();
$result_items = $stmt2->get_result();

$items = [];
$totalQty = 0;

while ($row_item = $result_items->fetch_assoc()) {
    $row_item['qty'] = (int)$row_item['qty'];
    $row_item['price'] = (float)$row_item['price'];
    $row_item['SP_ANH'] = $row_item['SP_ANH'] ?: 'logo.png';
    $totalQty += $row_item['qty'];
    $items[] = $row_item;
}

echo json_encode([
    "items" => $items,
    "totalQty" => $totalQty
]);
?>
