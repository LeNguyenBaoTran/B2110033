<?php
header('Content-Type: application/json; charset=utf-8');

// KẾT NỐI CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Kết nối CSDL thất bại: ' . $conn->connect_error
    ]);
    exit;
}

// TOẠ ĐỘ CỬA HÀNG (SHOP)
$shop_lat = 10.037148553484077; // Vĩ độ - Cần Thơ
$shop_lng = 105.78688505212948; // Kinh độ - Cần Thơ

// NHẬN DỮ LIỆU TỪ AJAX
$dvvc_ma = isset($_GET['dvvc_ma']) ? (int)$_GET['dvvc_ma'] : 0; 
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;            
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0;            

// Kiểm tra dữ liệu đầu vào
if ($dvvc_ma <= 0 || !$lat || !$lng) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu vị trí hoặc mã đơn vị vận chuyển'
    ]);
    exit;
}

// HÀM TÍNH KHOẢNG CÁCH (ĐƯỜNG CHIM BAY)
// Dùng công thức Haversine để tính khoảng cách giữa 2 toạ độ (km)
function tinhKhoangCach($lat1, $lng1, $lat2, $lng2)
{
    $R = 6371; // Bán kính Trái Đất (km)
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

$kc = tinhKhoangCach($shop_lat, $shop_lng, $lat, $lng); // khoảng cách tính được (km)

// TRUY VẤN PHÍ VẬN CHUYỂN
$sql = "
    SELECT pvc.PVC_GIAGIAO
    FROM phi_van_chuyen pvc
    JOIN dinh_muc_khoang_cach kc ON pvc.KC_MA = kc.KC_MA
    WHERE pvc.DVVC_MA = $dvvc_ma
      AND $kc >= kc.KC_MIN
      AND $kc < kc.KC_MAX
    LIMIT 1
";

$result = $conn->query($sql);

// XỬ LÝ KẾT QUẢ
if ($result && $result->num_rows > 0) {
    // Có mức phí phù hợp
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'phi' => (float)$row['PVC_GIAGIAO'],
        'kc' => round($kc, 2),
        'message' => 'Tính phí thành công'
    ]);
} else {
    // Không có mức phù hợp → Lấy mức xa nhất
    $fallback = $conn->query("
        SELECT pvc.PVC_GIAGIAO
        FROM phi_van_chuyen pvc
        JOIN dinh_muc_khoang_cach kc ON pvc.KC_MA = kc.KC_MA
        WHERE pvc.DVVC_MA = $dvvc_ma
        ORDER BY kc.KC_MAX DESC
        LIMIT 1
    ");

    if ($fallback && $fallback->num_rows > 0) {
        $row = $fallback->fetch_assoc();
        echo json_encode([
            'success' => true,
            'phi' => (float)$row['PVC_GIAGIAO'],
            'kc' => round($kc, 2),
            'message' => 'Khoảng cách vượt mức tối đa — áp dụng phí xa nhất'
        ]);
    } else {
        // Không có dữ liệu cấu hình phí
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy cấu hình phí vận chuyển',
            'kc' => round($kc, 2)
        ]);
    }
}

$conn->close();
?>
