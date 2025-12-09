<?php
// tao_don_ghn.php
header('Content-Type: application/json');

// --- Bật log lỗi, tắt hiển thị ra trình duyệt ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/error.log');

function json_exit($data){
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) {
    json_exit(['ok'=>false,'response'=>'Lỗi kết nối CSDL']);
}

// Nhận DH_MA từ POST
$ma_don = isset($_POST['ma_don']) ? intval($_POST['ma_don']) : 0;
if(!$ma_don){
    json_exit(['ok'=>false,'response'=>'Mã đơn không hợp lệ']);
}

// Lấy thông tin đơn hàng
$sql = "SELECT dh.DH_MA, dh.DH_TONGTHANHTOAN, nd.ND_HOTEN
        FROM don_hang dh
        LEFT JOIN nguoi_dung nd ON dh.ND_MA = nd.ND_MA
        WHERE dh.DH_MA = $ma_don";
$res = $conn->query($sql);
if(!$res || $res->num_rows == 0){
    json_exit(['ok'=>false,'response'=>'Không tìm thấy đơn hàng']);
}
$dh = $res->fetch_assoc();

// Thông tin shop (cố định)
$shop_id = 198091; 
$token   = '1081bace-bdff-11f0-a51e-f64be07fcf0a'; 
$from_name    = "Shop MODÉ";
$from_phone   = "0901234567";
$from_address = "8 Đ. Nguyễn Đình Chiểu, Tân An, Ninh Kiều, Cần Thơ, Việt Nam";
$from_ward_name = "Phường Tân An";
$from_district_name = "Ninh Kiều";
$from_province_name = "Cần Thơ";

// Thông số mặc định
$weight = 500; 
$length = 20; 
$width  = 15;
$height = 10;

// ĐỊA CHỈ KHÁCH MẶC ĐỊNH (test)
$to_name        = "KH Test";
$to_phone       = "0987654321";
$to_address     = "72 Thành Thái, Phường 14, Quận 10, Hồ Chí Minh, Vietnam";
$to_ward_code   = "20308"; // ward code hợp lệ
$to_district_id = 1444;    // district id hợp lệ

// COD = tổng tiền đơn
$cod_amount = intval($dh['DH_TONGTHANHTOAN']);

// Sản phẩm mặc định
$items = [
    [
        "name"=>"Sản phẩm MODÉ",
        "code"=>"MOD123",
        "quantity"=>1,
        "price"=>$cod_amount,
        "length"=>$length,
        "width"=>$width,
        "height"=>$height,
        "weight"=>$weight,
        "category"=>["level1"=>"Hàng hóa"]
    ]
];

// Payload GHN
$data = [
    "payment_type_id"=>2,
    "note"=>"Đơn MODÉ - Test",
    "required_note"=>"KHONGCHOXEMHANG",
    "from_name"=>$from_name,
    "from_phone"=>$from_phone,
    "from_address"=>$from_address,
    "from_ward_name"=>$from_ward_name,
    "from_district_name"=>$from_district_name,
    "from_province_name"=>$from_province_name,
    "to_name"=>$to_name,
    "to_phone"=>$to_phone,
    "to_address"=>$to_address,
    "to_ward_code"=>$to_ward_code,
    "to_district_id"=>$to_district_id,
    "cod_amount"=>$cod_amount,
    "content"=>"Đơn MODÉ - Test",
    "weight"=>$weight,
    "length"=>$length,
    "width"=>$width,
    "height"=>$height,
    "service_type_id"=>2,
    "items"=>$items,
    "client_order_code"=>"TEST_".$ma_don  // mã riêng tránh trùng
];

// Gửi request GHN
$ch = curl_init('https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "ShopId: $shop_id",
    "Token: $token"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);

if(curl_errno($ch)){
    json_exit(['ok'=>false,'response'=>'Lỗi cURL: '.curl_error($ch)]);
}
curl_close($ch);

// Giải mã JSON
$res_json = json_decode($response,true);
if(json_last_error() !== JSON_ERROR_NONE){
    file_put_contents('error.log', date('Y-m-d H:i:s')." - Invalid JSON: $response\n", FILE_APPEND);
    json_exit(['ok'=>false,'response'=>'GHN trả về dữ liệu không hợp lệ','raw'=>$response]);
}

// Thành công
if(isset($res_json['code']) && $res_json['code']==200){
    $ghn_order_code = $res_json['data']['order_code'];
    $conn->query("UPDATE don_hang SET DH_MA_GHN='$ghn_order_code' WHERE DH_MA=$ma_don");
    json_exit(['ok'=>true,'order_code'=>$ghn_order_code]);
}else{
    json_exit(['ok'=>false,'response'=>$res_json]);
}
?>
