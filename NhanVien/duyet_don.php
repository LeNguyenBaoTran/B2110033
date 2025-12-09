<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die(json_encode(["ok" => false, "msg" => "Kết nối thất bại CSDL"]));
}

$dh_ma = isset($_POST['dh_ma']) ? intval($_POST['dh_ma']) : 0;
if ($dh_ma <= 0) {
    echo json_encode(["ok" => false, "msg" => "Mã đơn không hợp lệ"]);
    exit;
}

/* 1. Lấy sản phẩm + kích thước để kiểm tra tồn theo size */
$sql_ct = "
SELECT 
    ctdh.SP_MA,
    sp.SP_TEN,
    ctdh.KT_MA,
    kt.KT_TEN,
    ctdh.CTDH_SOLUONG,
    ctsp.CTSP_SOLUONGTON
FROM chi_tiet_don_hang ctdh
JOIN san_pham sp ON ctdh.SP_MA = sp.SP_MA
JOIN kich_thuoc kt ON ctdh.KT_MA = kt.KT_MA
JOIN chi_tiet_san_pham ctsp 
    ON ctdh.SP_MA = ctsp.SP_MA 
    AND ctdh.KT_MA = ctsp.KT_MA
WHERE ctdh.DH_MA = $dh_ma
";

$result = $conn->query($sql_ct);
$errors = [];

while ($item = $result->fetch_assoc()) {

    // Kiểm tra tồn âm
    if ($item['CTSP_SOLUONGTON'] < 0) {
        $errors[] = "Sản phẩm <b>{$item['SP_TEN']}</b> (Size {$item['KT_TEN']}) tồn kho âm! Cần kiểm tra lại.";
    }

    // Kiểm tra đủ hàng để duyệt
    if ($item['CTSP_SOLUONGTON'] < $item['CTDH_SOLUONG']) {
        $errors[] = "Sản phẩm <b>{$item['SP_TEN']}</b> (Size {$item['KT_TEN']}) 
                     chỉ còn <b>{$item['CTSP_SOLUONGTON']}</b> cái, 
                     nhưng khách đặt <b>{$item['CTDH_SOLUONG']}</b>.";
    }
}

if (!empty($errors)) {
    echo json_encode(["ok" => false, "msg" => implode("<br>", $errors)]);
    exit;
}

/* 2. Cập nhật trạng thái đơn hàng */
$conn->query("UPDATE don_hang SET DH_TRANGTHAI = 'Đang chuẩn bị hàng' WHERE DH_MA = $dh_ma");

/* 3. Ghi lịch sử đơn hàng */
$conn->query("
INSERT INTO lich_su_don_hang (DH_MA, TT_MA, LSDH_THOIDIEM)
VALUES ($dh_ma, 3, NOW())
"); 
// TT_MA = 3 = trạng thái 'Đang chuẩn bị hàng'

echo json_encode(["ok" => true]);
?>
