<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

$sp_ma = isset($_GET['sp']) ? intval($_GET['sp']) : 0;
$data = [];

// Lấy thông tin sản phẩm + giá khuyến mãi
$sql = "SELECT 
    sp.SP_MA, sp.SP_TEN, sp.SP_CHATLIEU,
    dg.DONGIA AS GIA_GOC,
    ROUND(dg.DONGIA * (100 - COALESCE(ctkm.CTKM_PHANTRAM_GIAM, 0)) / 100, 0) AS GIA_HIEN_THI,
    COALESCE(ctkm.CTKM_PHANTRAM_GIAM, 0) AS GIAM
FROM SAN_PHAM sp
JOIN (
    SELECT SP_MA, DONGIA
    FROM DON_GIA_BAN
    WHERE (SP_MA, TD_THOIDIEM) IN (
        SELECT SP_MA, MAX(TD_THOIDIEM) FROM DON_GIA_BAN GROUP BY SP_MA
    )
) dg ON sp.SP_MA = dg.SP_MA
LEFT JOIN CHI_TIET_KHUYEN_MAI ctkm ON sp.SP_MA = ctkm.SP_MA
LEFT JOIN KHUYEN_MAI km ON ctkm.KM_MA = km.KM_MA 
  AND km.KM_CONSUDUNG = 1
  AND CURDATE() BETWEEN km.KM_NGAYBATDAU AND km.KM_NGAYKETTHUC
WHERE sp.SP_MA = $sp_ma LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $sp = $result->fetch_assoc();

    // Giá số nguyên để JS tính toán
    $data['gia'] = intval($sp['GIA_HIEN_THI']);

    // Giá hiển thị HTML (có gạch ngang nếu có giảm giá)
    if ($sp['GIAM'] > 0) {
        $data['gia_text'] = "<span class=\"text-muted text-decoration-line-through\">" . 
                            number_format($sp['GIA_GOC'], 0, ',', '.') . " đ</span> " .
                            "<span class=\"text-danger\">" . number_format($sp['GIA_HIEN_THI'], 0, ',', '.') . " đ</span>";
    } else {
        $data['gia_text'] = number_format($sp['GIA_HIEN_THI'], 0, ',', '.') . " đ";
    }

    $data['ten'] = $sp['SP_TEN'];
    $data['chatlieu'] = $sp['SP_CHATLIEU'];
}

// Lấy ảnh sản phẩm
$sql_img = "SELECT ANH_DUONGDAN FROM ANH_SAN_PHAM WHERE SP_MA = $sp_ma LIMIT 5";
$res_img = $conn->query($sql_img);
$data['images'] = [];
while ($r = $res_img->fetch_assoc()) {
    $data['images'][] = $r['ANH_DUONGDAN'];
}

// Lấy size sản phẩm
$sql_size = "SELECT kt.KT_TEN, ct.CTSP_SOLUONGTON, kt.KT_MA 
             FROM CHI_TIET_SAN_PHAM ct
             JOIN KICH_THUOC kt ON ct.KT_MA = kt.KT_MA
             WHERE ct.SP_MA = $sp_ma";
$res_size = $conn->query($sql_size);
$data['sizes'] = [];
while ($r = $res_size->fetch_assoc()) {
    $data['sizes'][] = [
        'ten' => $r['KT_TEN'],
        'ton' => intval($r['CTSP_SOLUONGTON']),
        'KT_MA' => intval($r['KT_MA'])
    ];
}

// Trả JSON
header('Content-Type: application/json');
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
