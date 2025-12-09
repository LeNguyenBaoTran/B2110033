<?php
// Kết nối csdl
$conn =  new mysqli("localhost","root","","ql_ban_trang_phuc");
mysqli_set_charset($conn,"utf8");

if($conn->connect_error){
    die("Kết nối thất bại: " .$conn->connect_error);
}

// Lấy mã đơn hàng
$dh_ma = isset($_GET['dh_ma']) ? intval($_GET['dh_ma']) : 0;
if($dh_ma <= 0){
    die("Không tìm thấy đơn hàng hợp lệ");
}

// Lấy thông tin đơn hàng
$sql_chitietdh = "SELECT dh.DH_MA, dh.DH_TONGTIENHANG, dh.DH_GIAMGIA, dh.DH_TONGTHANHTOAN, dh.DH_DIACHINHAN, dvvc.DVVC_TEN, vc.VC_TEN,
    sp.SP_TEN, kt.KT_TEN, ctdh.CTDH_SOLUONG, ctdh.CTDH_DONGIA,
    (SELECT a.ANH_DUONGDAN FROM anh_san_pham a WHERE a.SP_MA = sp.SP_MA LIMIT 1) AS SP_ANHDAIDIEN
FROM don_hang dh
LEFT JOIN don_vi_van_chuyen dvvc ON dh.DVVC_MA = dvvc.DVVC_MA
LEFT JOIN voucher vc ON dh.VC_MA = vc.VC_MA
LEFT JOIN chi_tiet_don_hang ctdh ON dh.DH_MA = ctdh.DH_MA
LEFT JOIN san_pham sp ON ctdh.SP_MA = sp.SP_MA
LEFT JOIN kich_thuoc kt ON ctdh.KT_MA = kt.KT_MA
WHERE dh.DH_MA = $dh_ma";

$result_chitietdh = $conn->query($sql_chitietdh);

$sql_lichsu = "SELECT tt.TT_TEN, ls.LSDH_THOIDIEM
                FROM lich_su_don_hang ls
                LEFT JOIN trang_thai tt ON ls.TT_MA = tt.TT_MA
                WHERE   ls.DH_MA = $dh_ma
                ORDER BY ls.LSDH_THOIDIEM ASC";
$result_lichsu = $conn->query($sql_lichsu);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi Tiết Đơn Hàng <?php echo $dh_ma; ?></title>
    <link rel="stylesheet" href="../assets/css/chitiet_donhang.css">
</head>
<body>
    <h2>Chi Tiết Đơn Hàng #<?php echo $dh_ma; ?></h2>

    <?php if ($result_chitietdh && $result_chitietdh->num_rows > 0): ?>
        <table border="1" cellspacing="0" cellpadding="8">
            <thead>
                <tr>
                    <th>Ảnh</th>
                    <th>Tên Sản Phẩm</th>
                    <th>Kích Thước</th>
                    <th>Số Lượng</th>
                    <th>Đơn Giá</th>
                    <th>Thành Tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $firstRow = null; 
                while($row = $result_chitietdh->fetch_assoc()):
                    if ($firstRow === null) $firstRow = $row; 
                    $thanhtien = $row['CTDH_SOLUONG'] * $row['CTDH_DONGIA'];
                    $phivc = $row['DH_TONGTHANHTOAN'] - ($row['DH_TONGTIENHANG'] - $row['DH_GIAMGIA']);
                ?>
                <tr>
                    <td><img src="<?php echo $row['SP_ANHDAIDIEN']; ?>" alt="" width="70"></td>
                    <td><?php echo htmlspecialchars($row['SP_TEN']); ?></td>
                    <td><?php echo $row['KT_TEN']; ?></td>
                    <td><?php echo $row['CTDH_SOLUONG']; ?></td>
                    <td><?php echo number_format($row['CTDH_DONGIA']); ?> ₫</td>
                    <td><?php echo number_format($thanhtien); ?> ₫</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php 
            $phivc = $firstRow['DH_TONGTHANHTOAN'] - ($firstRow['DH_TONGTIENHANG'] - $firstRow['DH_GIAMGIA']);
        ?>
        <p class="tong_hang">Tổng tiền hàng: <?= number_format($firstRow['DH_TONGTIENHANG']) ?> ₫</p>
        <div class="giam_gia">
            <p>Voucher: <?= htmlspecialchars($firstRow['VC_TEN']) ?></p>
            <p>Giảm Giá: <?= number_format($firstRow['DH_GIAMGIA']) ?> ₫</p>
        </div>
        <div class="van_chuyen">
            <p>Địa Chỉ Nhận: <?= htmlspecialchars($firstRow['DH_DIACHINHAN']) ?></p>
            <p>Đơn Vị Vận Chuyển: <?= htmlspecialchars($firstRow['DVVC_TEN']) ?></p>
            <p>Phí Vận Chuyển: <?= number_format($phivc) ?> ₫</p>
        </div>
        <p class="tong">Tổng thanh toán: <?= number_format($firstRow['DH_TONGTHANHTOAN']) ?> ₫</p>
    <?php endif; ?>

    <!-- Lịch sử đơn hàng -->
    <h5>LỊCH SỬ ĐƠN HÀNG</h5>
    <?php if($result_lichsu && $result_lichsu->num_rows > 0): ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Trạng Thái</th>
                    <th>Thời Điểm</th>
                </tr>
            </thead>
            <tbody>
                <?php while($ls = $result_lichsu->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ls['TT_TEN']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($ls['LSDH_THOIDIEM'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Chưa có lịch sử cho đơn hàng này</p>
    <?php endif; ?>
    
    <p><a href="quanly_donhang.php" class="back">← Quay lại quản lý đơn hàng</a></p>
</body>
</html>
