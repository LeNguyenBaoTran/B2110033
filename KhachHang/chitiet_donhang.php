<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để xem chi tiết đơn hàng!'); window.location='../Mode/dangnhap.php';</script>";
    exit;
}

$nd_ma = $_SESSION['nd_ma'];
$dh_ma = $_GET['dh_ma'] ?? 0;

// LẤY THÔNG TIN ĐƠN HÀNG
$sql_dh = "SELECT * FROM DON_HANG WHERE DH_MA = '$dh_ma' AND ND_MA = '$nd_ma'";
$result_dh = $conn->query($sql_dh);

if (!$result_dh || $result_dh->num_rows == 0) {
    echo "<script>alert('Không tìm thấy đơn hàng hợp lệ!'); window.location='lichsu_donhang.php';</script>";
    exit;
}

$dh = $result_dh->fetch_assoc();
$trangthai = $dh['DH_TRANGTHAI'] ?? ''; 

// LẤY DANH SÁCH SẢN PHẨM ĐÃ ĐẶT
$sql_sp = "SELECT dh.DH_MA, dh.DH_TONGTIENHANG, dh.DH_GIAMGIA, dh.DH_TONGTHANHTOAN, dh.DH_DIACHINHAN, 
        dvvc.DVVC_TEN, vc.VC_TEN,
        sp.SP_TEN, kt.KT_TEN, ctdh.CTDH_SOLUONG, ctdh.CTDH_DONGIA,
        (SELECT a.ANH_DUONGDAN FROM anh_san_pham a WHERE a.SP_MA = sp.SP_MA LIMIT 1) AS SP_ANHDAIDIEN
        FROM don_hang dh
        LEFT JOIN don_vi_van_chuyen dvvc ON dh.DVVC_MA = dvvc.DVVC_MA
        LEFT JOIN voucher vc ON dh.VC_MA = vc.VC_MA
        LEFT JOIN chi_tiet_don_hang ctdh ON dh.DH_MA = ctdh.DH_MA
        LEFT JOIN san_pham sp ON ctdh.SP_MA = sp.SP_MA
        LEFT JOIN kich_thuoc kt ON ctdh.KT_MA = kt.KT_MA
        WHERE dh.DH_MA = $dh_ma";
$result_sp = $conn->query($sql_sp);

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
    <title>Đơn hàng #<?php echo htmlspecialchars($dh_ma); ?></title>
    <link href="../assets/css/order_detail.css" rel="stylesheet">
</head>
<body>

<h2>Sản phẩm trong đơn hàng #<?php echo htmlspecialchars($dh_ma); ?></h2>

<?php if ($result_sp && $result_sp->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Ảnh</th>
                <th>Tên sản phẩm</th>
                <th>Kích thước</th>
                <th>Số lượng</th>
                <th>Đơn giá</th>
                <th>Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $tong = 0;
            $firstRow = null;
            while($row = $result_sp->fetch_assoc()):
                if ($firstRow === null) $firstRow = $row;
                $thanhtien = $row['CTDH_SOLUONG'] * $row['CTDH_DONGIA'];
                $tong += $thanhtien;
            ?>
            <tr>
                <td><img src="<?php echo htmlspecialchars($row['SP_ANHDAIDIEN']); ?>" alt="" style="width:70px; height:70px; object-fit:cover;"></td>
                <td><?php echo htmlspecialchars($row['SP_TEN']); ?></td>
                <td><?php echo htmlspecialchars($row['KT_TEN'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['CTDH_SOLUONG']); ?></td>
                <td><?php echo number_format($row['CTDH_DONGIA']); ?> ₫</td>
                <td><?php echo number_format($thanhtien); ?> ₫</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php if ($firstRow): 
        $phivc = $firstRow['DH_TONGTHANHTOAN'] - ($firstRow['DH_TONGTIENHANG'] - $firstRow['DH_GIAMGIA']);
    ?>
        <div class="thongtin-donhang">
            <p class="tong_hang"><strong>Tổng tiền hàng:</strong> <?= number_format($firstRow['DH_TONGTIENHANG']) ?> ₫</p>
            <div class="giam_gia">
                <p><strong>Voucher:</strong> <?= htmlspecialchars($firstRow['VC_TEN'] ?? '-') ?></p>
                <p><strong>Giảm giá:</strong> <?= number_format($firstRow['DH_GIAMGIA']) ?> ₫</p>
            </div>
            <div class="van_chuyen">
                <p><strong>Địa chỉ nhận:</strong> <?= htmlspecialchars($firstRow['DH_DIACHINHAN']) ?></p>
                <p><strong>Đơn vị vận chuyển:</strong> <?= htmlspecialchars($firstRow['DVVC_TEN'] ?? '-') ?></p>
                <p><strong>Phí vận chuyển:</strong> <?= number_format($phivc) ?> ₫</p>
            </div>
            <p class="tong"><strong>Tổng thanh toán:</strong> <?= number_format($firstRow['DH_TONGTHANHTOAN']) ?> ₫</p>
        </div>
    <?php endif; ?>

    <?php if ($trangthai == 'Chờ thanh toán'): ?>
        <div style="text-align:center; margin-top: 20px;">
            <a href="../Mode/thanhtoan_vnpay.php?dh_ma=<?php echo $dh_ma; ?>" 
               style="display:inline-block; background:#27ae60; color:white; padding:10px 20px; border-radius:5px; text-decoration:none; margin-bottom: 20px;">
               💳 Thanh toán lại qua VNPAY
            </a>
        </div>
    <?php endif; ?>

<?php else: ?>
    <p>Không có sản phẩm nào trong đơn này.</p>
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
    
<p><a href="lichsu_donhang.php" class="back">← Quay lại lịch sử đơn hàng</a></p>

</body>
</html>
