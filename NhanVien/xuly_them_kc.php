<?php
// xuly_them_kc.php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy dữ liệu từ form
$KC_MIN = isset($_POST['KC_MIN']) ? floatval($_POST['KC_MIN']) : null;
$KC_MAX = isset($_POST['KC_MAX']) ? floatval($_POST['KC_MAX']) : null;

// Kiểm tra dữ liệu hợp lệ
if ($KC_MIN === null || $KC_MAX === null) {
    echo "<script>alert('Vui lòng nhập đầy đủ khoảng cách!'); window.history.back();</script>";
    exit;
}

if ($KC_MIN >= $KC_MAX) {
    echo "<script>alert('Khoảng min phải nhỏ hơn khoảng max!'); window.history.back();</script>";
    exit;
}

// Kiểm tra khoảng chồng lấn với các khoảng đã có
$stmt_check = $conn->prepare("
    SELECT KC_MA 
    FROM dinh_muc_khoang_cach
    WHERE NOT (? >= KC_MAX OR ? <= KC_MIN)
");
$stmt_check->bind_param("dd", $KC_MIN, $KC_MAX);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo "<script>alert('Khoảng cách này chồng lấn với khoảng đã có!'); window.history.back();</script>";
    exit;
}
$stmt_check->close();

// Thêm vào CSDL
$stmt = $conn->prepare("INSERT INTO dinh_muc_khoang_cach (KC_MIN, KC_MAX) VALUES (?, ?)");
$stmt->bind_param("dd", $KC_MIN, $KC_MAX);

if ($stmt->execute()) {
    echo "<script>alert('Thêm định mức khoảng cách thành công!'); window.location.href='quanly_phigiao.php';</script>";
} else {
    echo "<script>alert('Có lỗi xảy ra, vui lòng thử lại!'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
