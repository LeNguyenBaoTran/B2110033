<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Không nhận được JSON']);
    exit;
}

$nd_ma = intval($data['ND_MA']);
$cart = $data['cart'] ?? [];

if (!$nd_ma || empty($cart)) {
    echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu']);
    exit;
}

// Lấy hoặc tạo GH_MA
$stmtGH = $conn->prepare("SELECT GH_MA FROM gio_hang WHERE ND_MA=?");
$stmtGH->bind_param("i", $nd_ma);
$stmtGH->execute();
$resGH = $stmtGH->get_result();

if ($rowGH = $resGH->fetch_assoc()) {
    $gh_ma = $rowGH['GH_MA'];
} else {
    $stmtInsertGH = $conn->prepare("INSERT INTO gio_hang (ND_MA) VALUES (?)");
    $stmtInsertGH->bind_param("i", $nd_ma);
    $stmtInsertGH->execute();
    $gh_ma = $conn->insert_id;
}

foreach ($cart as $item) {
    $sp_ma = intval($item['SP_MA']);
    $qty   = intval($item['qty']);
    $price = floatval($item['price']);

    // Lấy KT_MA từ KT_TEN nếu chưa có
    $kt_ma = isset($item['KT_MA']) ? $item['KT_MA'] : null;
    if (!$kt_ma && isset($item['KT_TEN'])) {
        $kt_ten = $conn->real_escape_string($item['KT_TEN']);
        $resKT = $conn->query("SELECT KT_MA FROM kich_thuoc WHERE KT_TEN='$kt_ten' LIMIT 1");
        if ($resKT && $resKT->num_rows > 0) {
            $kt_ma = $resKT->fetch_assoc()['KT_MA'];
        } else {
            continue; // bỏ qua nếu không tìm được size
        }
    }
    $kt_ma = intval($kt_ma);

    // Kiểm tra sản phẩm + size có trong giỏ chưa
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
        // Thêm mới
        $stmtInsert = $conn->prepare("INSERT INTO chi_tiet_gio_hang (GH_MA, SP_MA, KT_MA, CTGH_SOLUONG, CTGH_DONGIA)
                                      VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->bind_param("iiiid", $gh_ma, $sp_ma, $kt_ma, $qty, $price);
        $stmtInsert->execute();
    }
}

echo json_encode(['success' => true]);
?>
