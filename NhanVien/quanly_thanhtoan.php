<?php
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Danh sách đơn hàng
$thanhtoan = [];
$sql_thanhtoan = "SELECT ttd.DH_MA, nd.ND_HOTEN, httt.HTTT_TEN, ttd.TTD_SOTIEN, ttd.TTD_NGAYTHANHTOAN, ttd.TTD_TRANGTHAI, ttd.TTD_QRCODE, ttd.TTD_MAGIAODICH
                FROM thanh_toan_don ttd
                LEFT JOIN don_hang dh ON ttd.DH_MA = dh.DH_MA
                LEFT JOIN nguoi_dung nd ON dh.ND_MA = nd.ND_MA
                LEFT JOIN hinh_thuc_thanh_toan httt ON ttd.HTTT_MA = httt.HTTT_MA
                ORDER BY ttd.DH_MA DESC";
$result_thanhtoan = $conn->query($sql_thanhtoan);
while ($row = $result_thanhtoan->fetch_assoc()) {
    $thanhtoan[] = $row;
}

// --- Đơn cần hoàn tiền ---
$hoantra = [];
$sql_hoantra = "SELECT ttd.DH_MA, nd.ND_HOTEN, httt.HTTT_TEN, ttd.TTD_SOTIEN, 
                       ttd.TTD_NGAYTHANHTOAN, ttd.TTD_TRANGTHAI, ttd.TTD_MAGIAODICH, ttd.TTD_QRCODE, dh.DH_TRANGTHAI
                FROM thanh_toan_don ttd
                JOIN don_hang dh ON ttd.DH_MA = dh.DH_MA
                JOIN nguoi_dung nd ON dh.ND_MA = nd.ND_MA
                LEFT JOIN hinh_thuc_thanh_toan httt ON ttd.HTTT_MA = httt.HTTT_MA
                WHERE dh.DH_TRANGTHAI IN ('Đã hủy', 'Hoàn hàng')
                  AND ttd.TTD_TRANGTHAI = 'Đã thanh toán'
                ORDER BY ttd.DH_MA DESC";
$result_hoantra = $conn->query($sql_hoantra);
while ($row = $result_hoantra->fetch_assoc()) {
    $hoantra[] = $row;
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản Lý Thanh Toán</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="../assets/css/order_manager.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h2>💳 QUẢN LÝ THANH TOÁN</h2>
            <a href="nhanvien.php" class="btn-back">← Quay lại Menu</a>
        </div>

        <div class="tab-buttons">
            <button class="tab-btn active" onclick="openTab(event, 'thanhtoan')">Đơn Thanh Toán</button>
            <button class="tab-btn" onclick="openTab(event, 'hoantra')">Đơn Hoàn Trả</button>
        </div>

        <!-- TAB 1 -->
        <div id="thanhtoan" class="tab-content active">
            <table>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>HTTT</th>
                    <th>Tổng Tiền</th>
                    <th>Ngày Thanh Toán</th>
                    <th>Trạng Thái</th>
                    <th>QR CODE</th>
                    <th>Mã Giao Dịch</th>
                </tr>
                <?php foreach($thanhtoan as $tt): ?>
                <tr>
                    <td><?= $tt['DH_MA'] ?></td>
                    <td><?= $tt['ND_HOTEN'] ?></td>
                    <td><?= $tt['HTTT_TEN'] ?></td>
                    <td><?= number_format($tt['TTD_SOTIEN'], 0, ',', '.') ?>₫</td>
                    <td><?= date("d/m/Y", strtotime($tt['TTD_NGAYTHANHTOAN'])) ?></td>
                    <td><?= $tt['TTD_TRANGTHAI'] ?></td>
                    <td><?= $tt['TTD_QRCODE'] ?></td>
                    <td><?= $tt['TTD_MAGIAODICH'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- TAB 2: LỊCH SỬ ĐƠN HÀNG -->
        <div id="hoantra" class="tab-content">
            <table>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>HTTT</th>
                    <th>Số Tiền</th>
                    <th>Ngày Thanh Toán</th>
                    <th>Trạng Thái Đơn</th>
                    <th>QR CODE</th>
                    <th>Mã Giao Dịch</th>
                    <th>Thao tác</th>
                </tr>
                <?php if (!empty($hoantra)): ?>
                    <?php foreach($hoantra as $ht): ?>
                    <tr>
                        <td><?= $ht['DH_MA'] ?></td>
                        <td><?= $ht['ND_HOTEN'] ?></td>
                        <td><?= $ht['HTTT_TEN'] ?></td>
                        <td><?= number_format($ht['TTD_SOTIEN'], 0, ',', '.') ?>₫</td>
                        <td><?= date("d/m/Y", strtotime($ht['TTD_NGAYTHANHTOAN'])) ?></td>
                        <td><?= $ht['DH_TRANGTHAI'] ?></td>
                        <td><?= $ht['TTD_QRCODE'] ?></td>
                        <td><?= $ht['TTD_MAGIAODICH'] ?></td>
                        <td>
                            <form action="hoantien_vnpay.php" method="POST">
                                <input type="hidden" name="order_id" value="<?= $ht['DH_MA'] ?>">
                                <input type="hidden" name="txn_ref" value="<?= $ht['TTD_MAGIAODICH'] ?>">
                                <input type="hidden" name="amount" value="<?= $ht['TTD_SOTIEN'] ?>">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fa-solid fa-rotate-left"></i> Hoàn tiền
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align:center;">Không có đơn cần hoàn tiền</td></tr>
                <?php endif; ?>
            </table>
        </div>


    <script>
        function openTab(event, tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        // Giữ lại tab đang mở dựa trên URL
        document.addEventListener("DOMContentLoaded", function () {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get("tab") || "thanhtoan";

            document.querySelectorAll('.tab-content').forEach(tabEl => tabEl.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            document.getElementById(tab).classList.add('active');
            document.querySelector(`button[onclick*="${tab}"]`).classList.add('active');
        });

    </script>
</body>
</html>
