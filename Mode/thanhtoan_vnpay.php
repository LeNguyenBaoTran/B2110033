<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$dh_ma = $_GET['dh_ma'] ?? null;
if (!$dh_ma) {
    die("Thiếu mã đơn hàng!");
}

// Lấy thông tin đơn hàng
$sql = "SELECT DH_TONGTHANHTOAN, DH_MA FROM DON_HANG WHERE DH_MA = '$dh_ma'";
$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) {
    die("Không tìm thấy đơn hàng!");
}
$order = $result->fetch_assoc();

// === Thông tin VNPAY ===
$vnp_TmnCode = "ZN8VEUO9"; // Mã website tại VNPAY 
$vnp_HashSecret = "FDVRUROXBU8L4UXT6VMWU7PMI2I56BNA"; // Chuỗi bí mật
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/LV_QuanLy_BanTrangPhuc/LV_QuanLy_BanTrangPhuc/Mode/vnpay_return.php";

$vnp_TxnRef = $order['DH_MA'] . '_' . time(); // Mã giao dịch duy nhất cho mỗi lần thanh toán
$vnp_OrderInfo = "Thanh toán đơn hàng #".$order['DH_MA'];
$vnp_OrderType = "billpayment";
$vnp_Amount = $order['DH_TONGTHANHTOAN'] * 100;
$vnp_Locale = 'vn';
$vnp_BankCode = 'NCB';
$vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef
);

ksort($inputData);
$query = "";
$i = 0;
$hashdata = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

$vnp_Url = $vnp_Url . "?" . $query;
if (isset($vnp_HashSecret)) {
    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
}

// Chuyển hướng đến cổng thanh toán VNPAY
header('Location: ' . $vnp_Url);
exit;
?>
