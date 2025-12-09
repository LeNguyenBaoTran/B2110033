<?php
// Lấy mã đơn hàng từ URL
$dh_ma = isset($_GET['dh_ma']) ? intval($_GET['dh_ma']) : 0;

$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($dh_ma <= 0) die("Mã đơn hàng không hợp lệ!");

// Lấy thông tin đơn + sản phẩm
$sql = "
SELECT dh.DH_MA, nd.ND_HOTEN, dh.DH_NGAYDAT, dh.DH_DIACHINHAN,
       ctdh.SP_MA, sp.SP_TEN, kt.KT_TEN, 
       ctdh.CTDH_SOLUONG, ctdh.CTDH_DONGIA,
       dh.DH_TONGTIENHANG, dh.DH_GIAMGIA, dh.DH_TONGTHANHTOAN
FROM don_hang dh
LEFT JOIN nguoi_dung nd ON dh.ND_MA = nd.ND_MA
LEFT JOIN chi_tiet_don_hang ctdh ON ctdh.DH_MA = dh.DH_MA
LEFT JOIN kich_thuoc kt ON ctdh.KT_MA = kt.KT_MA
LEFT JOIN san_pham sp ON ctdh.SP_MA = sp.SP_MA
WHERE dh.DH_MA = $dh_ma
";

$result = $conn->query($sql);
if ($result->num_rows == 0) die("Không tìm thấy đơn hàng!");

$orderInfo = $result->fetch_assoc();
$result->data_seek(0);

// Danh sách sản phẩm
$products = [];
while ($row = $result->fetch_assoc()) $products[] = $row;

// Tính phí vận chuyển
$phi_vanchuyen = $orderInfo['DH_TONGTHANHTOAN'] - ($orderInfo['DH_TONGTIENHANG'] - $orderInfo['DH_GIAMGIA']);

// --- TCPDF ---
require_once __DIR__ . '/../includes/TCPDF-main/tcpdf.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('MODÉ Thời trang');
$pdf->SetAuthor('MODÉ Thời trang');
$pdf->SetTitle('Hóa đơn #' . $orderInfo['DH_MA']);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();
$pdf->SetFont('freesans', '', 12);


// --- Thông tin Shop ---
$html = '<b>MODÉ Thời trang nam nữ</b><br>
12 Đ. Nguyễn Đình Chiểu, Tân An, Ninh Kiều, Cần Thơ, Việt Nam<br>
Điện thoại: 0765 958 481 | Email: iuidolofyou@gmail.com<br><br>';

$pdf->writeHTML($html, true, false, false, false, '');

// --- Tiêu đề hóa đơn ---
$pdf->SetFont('', 'B', 16);
$pdf->Cell(0, 10, 'HÓA ĐƠN THANH TOÁN', 0, 1, 'C');
$pdf->Ln(5);

// --- Thông tin khách hàng ---
$pdf->SetFont('', '', 12);
$html = '<table cellpadding="4">
<tr><td><b>Mã đơn hàng:</b></td><td>' . $orderInfo['DH_MA'] . '</td></tr>
<tr><td><b>Khách hàng:</b></td><td>' . $orderInfo['ND_HOTEN'] . '</td></tr>
<tr><td><b>Ngày đặt:</b></td><td>' . date("d/m/Y", strtotime($orderInfo['DH_NGAYDAT'])) . '</td></tr>
<tr><td><b>Địa chỉ giao hàng:</b></td><td>' . $orderInfo['DH_DIACHINHAN'] . '</td></tr>
</table><br>';

$pdf->writeHTML($html, true, false, false, false, '');

// --- Bảng sản phẩm ---
$html = '<table border="1" cellpadding="4">
<tr>
<th>Mã SP</th>
<th>Tên sản phẩm</th>
<th>Kích thước</th>
<th>Số lượng</th>
<th>Đơn giá</th>
<th>Thành tiền</th>
</tr>';

foreach ($products as $p) {
    $thanhTien = $p['CTDH_SOLUONG'] * $p['CTDH_DONGIA'];
    $html .= '<tr>
        <td>' . $p['SP_MA'] . '</td>
        <td>' . $p['SP_TEN'] . '</td>
        <td>' . $p['KT_TEN'] . '</td>
        <td>' . $p['CTDH_SOLUONG'] . '</td>
        <td>' . number_format($p['CTDH_DONGIA']) . '₫</td>
        <td>' . number_format($thanhTien) . '₫</td>
    </tr>';
}
$html .= '</table><br>';

$pdf->writeHTML($html, true, false, false, false, '');

// --- Tổng thanh toán ---
$html = '<table cellpadding="4" align="right">
<tr><td><b>Tổng tiền hàng:</b></td><td>' . number_format($orderInfo['DH_TONGTIENHANG']) . 'đ</td></tr>
<tr><td><b>Giảm giá:</b></td><td>-' . number_format($orderInfo['DH_GIAMGIA']) . 'đ</td></tr>
<tr><td><b>Phí vận chuyển:</b></td><td>' . number_format($phi_vanchuyen) . 'đ</td></tr>
<tr><td><b>Tổng thanh toán:</b></td><td><b>' . number_format($orderInfo['DH_TONGTHANHTOAN']) . 'đ</b></td></tr>
</table>';

$pdf->writeHTML($html, true, false, false, false, '');

// --- Xuất PDF ---
$pdf->Output('HoaDon_' . $orderInfo['DH_MA'] . '.pdf', 'I');
