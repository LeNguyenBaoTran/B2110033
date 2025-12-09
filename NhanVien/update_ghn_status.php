<?php
header('Content-Type: application/json');

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'response' => 'Lỗi kết nối CSDL']);
    exit;
}

// GHN
$shop_id = 198091;
$token   = '1081bace-bdff-11f0-a51e-f64be07fcf0a';

// Lấy danh sách đơn có mã GHN
$res = $conn->query("SELECT DH_MA, DH_MA_GHN, DH_TRANGTHAI FROM don_hang WHERE DH_MA_GHN IS NOT NULL");

if ($res->num_rows == 0) {
    echo json_encode(['ok' => false, 'response' => 'Không có đơn có mã GHN']);
    exit;
}

// Mapping GHN → TT_MA
$ghn_to_tt = [
    'ready_to_pick'  => 10, // Chờ lấy hàng
    'picking'        => 4,  // Đang lấy
    'transporting'   => 4,  // Đang giao hàng
    'delivered'      => 5,  // Giao thành công
    'cancel'         => 7,  // Hủy
    'return'         => 6   // Hoàn hàng
];

// Mapping TT_MA → TT_TEN
$tt_ma_to_ten = [
    4  => 'Đang giao hàng',
    5  => 'Giao thành công',
    6  => 'Hoàn hàng',
    7  => 'Đã hủy',
    10 => 'Chờ lấy hàng'
];

$updated = [];

while ($row = $res->fetch_assoc()) {
    $dh_ma = $row['DH_MA'];
    $order_code = $row['DH_MA_GHN'];

    // Gọi API GHN
    $ch = curl_init("https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "ShopId: $shop_id",
        "Token: $token"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['order_code' => $order_code]));

    $response = curl_exec($ch);
    curl_close($ch);

    $res_json = json_decode($response, true);
    if (!$res_json || !isset($res_json['data']['status'])) {
        continue;
    }

    $ghn_status = $res_json['data']['status'];

    // Kiểm tra trạng thái GHN có thuộc map không
    if (!isset($ghn_to_tt[$ghn_status])) continue;

    $tt_ma = $ghn_to_tt[$ghn_status];
    $tt_ten = $tt_ma_to_ten[$tt_ma];

    // Nếu khác trạng thái hiện tại
    if ($tt_ten != $row['DH_TRANGTHAI']) {

        // Update trạng thái đơn
        $conn->query("UPDATE don_hang SET DH_TRANGTHAI='$tt_ten' WHERE DH_MA=$dh_ma");

        // Ghi lịch sử
        $now = date('Y-m-d H:i:s');
        $conn->query("
            INSERT INTO lich_su_don_hang (TT_MA, DH_MA, LSDH_THOIDIEM) 
            VALUES ($tt_ma, $dh_ma, '$now')
        ");

        $updated[] = [
            'dh_ma' => $dh_ma,
            'status_code' => $tt_ma,
            'status_text' => $tt_ten
        ];
    }
}

echo json_encode(['ok' => true, 'updated' => $updated]);
