<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Kết nối CSDL
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

// Lấy dữ liệu JSON
$data = json_decode(file_get_contents("php://input"), true);
$sp_ma = intval($data['SP_MA'] ?? 0);
$kt_ma = intval($data['KT_MA'] ?? 0);

if (!$sp_ma || !$kt_ma) {
    echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ"]);
    exit;
}

// Lấy GH_MA của người dùng
$stmt = $conn->prepare("SELECT GH_MA FROM gio_hang WHERE ND_MA = ? LIMIT 1");
$stmt->bind_param("i", $nd_ma);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Không tìm thấy giỏ hàng"]);
    exit;
}
$row = $res->fetch_assoc();
$gh_ma = $row['GH_MA'];

// Xóa sản phẩm khỏi chi_tiet_gio_hang
$stmt2 = $conn->prepare("DELETE FROM chi_tiet_gio_hang WHERE GH_MA = ? AND SP_MA = ? AND KT_MA = ?");
$stmt2->bind_param("iii", $gh_ma, $sp_ma, $kt_ma);

if ($stmt2->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Xóa sản phẩm thất bại"]);
}
?>
