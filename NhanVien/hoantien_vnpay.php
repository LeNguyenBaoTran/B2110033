<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Ho_Chi_Minh');

// --- Lấy dữ liệu POST ---
$dh_ma = $_POST['order_id'] ?? null;
$amount_in_vnd = $_POST['amount'] ?? null; // ví dụ: 485000

if (!$dh_ma || !$amount_in_vnd) {
    die("Thiếu thông tin hoàn tiền!");
}

// --- Lấy thanh toán gốc từ DB ---
$sql = "SELECT TTD_VNP_TXNREF, TTD_NGAYTHANHTOAN 
        FROM THANH_TOAN_DON 
        WHERE DH_MA = ? AND TTD_TRANGTHAI='Đã thanh toán' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dh_ma);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) die("Không tìm thấy thanh toán gốc");

// --- Dữ liệu gốc ---
$vnp_TxnRef = $row['TTD_VNP_TXNREF'];
$vnp_TransactionDate = date('YmdHis', strtotime($row['TTD_NGAYTHANHTOAN']));

// --- Cấu hình VNPAY ---
$vnp_TmnCode = "ZN8VEUO9"; // Thay bằng TMN code của bạn
$vnp_HashSecret = trim("FDVRUROXBU8L4UXT6VMWU7PMI2I56BNA"); // Thay secret của bạn
$vnp_ApiUrl = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction";

// --- Chuẩn bị dữ liệu refund ---
$vnp_Amount = intval(round($amount_in_vnd * 100)); // nhân 100 theo yêu cầu VNPAY

$inputData = [
    "vnp_Version" => "2.1.0",
    "vnp_Command" => "refund",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_TxnRef" => $vnp_TxnRef,
    "vnp_TransactionType" => "02", // hoàn toàn
    "vnp_Amount" => $vnp_Amount,
    "vnp_CurrCode" => "VND",
    "vnp_OrderInfo" => "Hoan tien don hang $dh_ma",
    "vnp_RequestId" => uniqid(),
    "vnp_CreateBy" => "admin",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_IpAddr" => "127.0.0.1",
    "vnp_TransactionDate" => $vnp_TransactionDate,
    "vnp_TransactionNo" => "" // Nếu không có thì để trống
];

// --- Tạo Secure Hash theo thứ tự chuẩn VNPAY ---
$hashData = $inputData['vnp_RequestId'] . '|'
          . $inputData['vnp_Version'] . '|'
          . $inputData['vnp_Command'] . '|'
          . $inputData['vnp_TmnCode'] . '|'
          . $inputData['vnp_TransactionType'] . '|'
          . $inputData['vnp_TxnRef'] . '|'
          . $inputData['vnp_Amount'] . '|'
          . $inputData['vnp_TransactionNo'] . '|'
          . $inputData['vnp_TransactionDate'] . '|'
          . $inputData['vnp_CreateBy'] . '|'
          . $inputData['vnp_CreateDate'] . '|'
          . $inputData['vnp_IpAddr'] . '|'
          . $inputData['vnp_OrderInfo'];

// --- Tạo HMAC SHA512 ---
$vnp_SecureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$inputData['vnp_SecureHash'] = $vnp_SecureHash;

// --- Gửi request tới VNPAY ---
$ch = curl_init($vnp_ApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($inputData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

// --- Ghi log debug ---
$logText = date('Y-m-d H:i:s') . " REQUEST: " . json_encode($inputData) . "\nRESPONSE: $response\n\n";
file_put_contents('vnp_refund_log.txt', $logText, FILE_APPEND);

// --- Xử lý phản hồi ---
$result = json_decode($response, true);

if (isset($result['vnp_ResponseCode']) && $result['vnp_ResponseCode'] === '00') {
    // ✅ Hoàn tiền thành công
    $conn->query("UPDATE THANH_TOAN_DON SET TTD_TRANGTHAI='Đã hoàn tiền' WHERE DH_MA=" . intval($dh_ma));
    $conn->query("INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) VALUES (" . intval($dh_ma) . ", 8, NOW())");

    echo "<script>alert('✅ Hoàn tiền VNPAY thành công!'); window.location='quanly_thanhtoan.php?tab=hoantra';</script>";
} else {
    $msg = $result['vnp_Message'] ?? 'Không rõ lỗi';
    echo "<script>alert('❌ Hoàn tiền thất bại: $msg'); history.back();</script>";
}
?>
