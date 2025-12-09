<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Kết nối CSDL thất bại"]);
    exit;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['nd_ma'])) {
    echo json_encode(["success" => false, "message" => "Chưa đăng nhập"]);
    exit;
}

$nd_ma = intval($_SESSION['nd_ma']);

// Nhận dữ liệu từ fetch
$data = json_decode(file_get_contents("php://input"), true);
$sp_ma = intval($data['SP_MA'] ?? 0);
$kt_ma = intval($data['KT_MA'] ?? 0);
$delta = intval($data['delta'] ?? 0);

if (!$sp_ma || !$kt_ma || $delta === 0) {
    echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ"]);
    exit;
}

// Lấy mã giỏ hàng của người dùng
$stmt = $conn->prepare("SELECT GH_MA FROM gio_hang WHERE ND_MA = ? LIMIT 1");
$stmt->bind_param("i", $nd_ma);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Không tìm thấy giỏ hàng"]);
    exit;
}

$gh_ma = $res->fetch_assoc()['GH_MA'];

// Lấy số lượng hiện tại trong giỏ hàng
$stmt2 = $conn->prepare("SELECT CTGH_SOLUONG FROM chi_tiet_gio_hang WHERE GH_MA = ? AND SP_MA = ? AND KT_MA = ?");
$stmt2->bind_param("iii", $gh_ma, $sp_ma, $kt_ma);
$stmt2->execute();
$res2 = $stmt2->get_result();

if ($res2->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Sản phẩm không tồn tại trong giỏ hàng"]);
    exit;
}

$currentQty = intval($res2->fetch_assoc()['CTGH_SOLUONG']);
$newQty = $currentQty + $delta;
if ($newQty < 1) $newQty = 1;

// 🔎 Kiểm tra tồn kho trong bảng chi_tiet_san_pham
$stmt3 = $conn->prepare("SELECT CTSP_SOLUONGTON FROM chi_tiet_san_pham WHERE SP_MA = ? AND KT_MA = ?");
$stmt3->bind_param("ii", $sp_ma, $kt_ma);
$stmt3->execute();
$res3 = $stmt3->get_result();

if ($res3->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Không tìm thấy dữ liệu tồn kho"]);
    exit;
}

$tonKho = intval($res3->fetch_assoc()['CTSP_SOLUONGTON']);

// ⚠️ Kiểm tra vượt tồn kho
if ($newQty > $tonKho) {
    echo json_encode([
        "success" => false,
        "message" => "Số lượng vượt quá tồn kho (" . $tonKho . " sản phẩm)"
    ]);
    exit;
}

// ✅ Cập nhật lại số lượng giỏ hàng
$stmt4 = $conn->prepare("UPDATE chi_tiet_gio_hang SET CTGH_SOLUONG = ? WHERE GH_MA = ? AND SP_MA = ? AND KT_MA = ?");
$stmt4->bind_param("iiii", $newQty, $gh_ma, $sp_ma, $kt_ma);

if ($stmt4->execute()) {
    echo json_encode(["success" => true, "newQty" => $newQty]);
} else {
    echo json_encode(["success" => false, "message" => "Cập nhật thất bại"]);
}
?>
