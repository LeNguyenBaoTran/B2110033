<?php
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Danh sách đơn hàng
$donhang = [];
$sql_donhang = "SELECT dh.DH_MA, nd.ND_HOTEN, dh.DH_NGAYDAT, dh.DH_TONGTHANHTOAN, dh.DH_TRANGTHAI, dh.DVVC_MA, dvvc.DVVC_TEN, dh.DH_MA_GHN
                FROM don_hang dh
                LEFT JOIN nguoi_dung nd ON dh.ND_MA = nd.ND_MA
                LEFT JOIN don_vi_van_chuyen dvvc ON dh.DVVC_MA = dvvc.DVVC_MA
                ORDER BY dh.DH_MA DESC";
$result_donhang = $conn->query($sql_donhang);
while ($row = $result_donhang->fetch_assoc()) {
    $donhang[] = $row;
}

// Lấy danh sách đơn chưa duyệt
$sql_choxacnhan = "SELECT dh.DH_MA, nd.ND_HOTEN, dh.DH_NGAYDAT, dh.DH_TONGTHANHTOAN, latest.TT_TEN
FROM don_hang dh
LEFT JOIN nguoi_dung nd ON dh.ND_MA = nd.ND_MA
LEFT JOIN (
    SELECT lsdh.DH_MA, tt.TT_TEN
    FROM lich_su_don_hang lsdh
    JOIN trang_thai tt ON lsdh.TT_MA = tt.TT_MA
    WHERE (lsdh.DH_MA, lsdh.LSDH_THOIDIEM) IN (
        SELECT DH_MA, MAX(LSDH_THOIDIEM)
        FROM lich_su_don_hang
        GROUP BY DH_MA
    )
) AS latest ON dh.DH_MA = latest.DH_MA
WHERE latest.TT_TEN IN ('Chờ xác nhận', 'Đã thanh toán')
ORDER BY dh.DH_MA DESC";
$result_choxacnhan = $conn->query($sql_choxacnhan);

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản Lý Đơn Hàng</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="../assets/css/order_manager.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h2>📦 QUẢN LÝ ĐƠN HÀNG</h2>
            <a href="nhanvien.php" class="btn-back">← Quay lại Menu</a>
        </div>

        <div class="tab-buttons">
            <button class="tab-btn active" onclick="openTab(event, 'donhang')">Danh Sách Đơn Hàng</button>
            <button class="tab-btn" onclick="openTab(event, 'lichsu')">Đơn Hàng Chưa Duyệt</button>
        </div>

        <!-- TAB 1 -->
        <div id="donhang" class="tab-content active">
            <table>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>Ngày Đặt</th>
                    <th>Tổng Tiền</th>
                    <th>Trạng Thái</th>
                    <th>Hành Động</th>
                </tr>
                <?php foreach($donhang as $dh): ?>
                <tr>
                    <td><?= $dh['DH_MA'] ?></td>
                    <td><?= $dh['ND_HOTEN'] ?></td>
                    <td><?= date("d/m/Y", strtotime($dh['DH_NGAYDAT'])) ?></td>
                    <td><?= number_format($dh['DH_TONGTHANHTOAN'], 0, ',', '.') ?>₫</td>
                    <td class="
                            <?php 
                                if ($dh['DH_TRANGTHAI'] === 'Giao thành công') echo 'status-success';
                                elseif ($dh['DH_TRANGTHAI'] === 'Đã hủy') echo 'status-cancel';
                                else echo 'status-normal';
                            ?>
                        ">
                        <?= $dh['DH_TRANGTHAI'] ?>
                    </td>

                    <td>
                        <a class="btn-view-detail" href="chitietdh.php?dh_ma=<?= $dh['DH_MA'] ?>">Xem</a>

                        <?php if ($dh['DH_TRANGTHAI'] === 'Đang chuẩn bị hàng' && empty($dh['DH_MA_GHN'])): ?>
                            <button class="btn-send-dvvc"
                                    onclick="sendDVVC(<?= $dh['DH_MA'] ?>, <?= $dh['DVVC_MA'] ?>, '<?= htmlspecialchars($dh['DVVC_TEN']) ?>', this)">
                                Giao <?= htmlspecialchars($dh['DVVC_TEN']) ?>
                            </button>
                            <?php elseif (!empty($dh['DH_MA_GHN'])): ?>
                                <span class="btn-dagui">
                                    Đã gửi <?= htmlspecialchars($dh['DVVC_TEN']) ?>
                                    <br>
                                    <strong class="btn-ma">Mã GHN:</strong> <?= htmlspecialchars($dh['DH_MA_GHN']) ?>
                                </span>
                            <?php else: ?>
                            <span style="color:gray;">Không thể giao đơn hàng này</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- TAB 2: ĐƠN HÀNG CHƯA DUYỆT -->
        <div id="lichsu" class="tab-content">
           <?php if($result_choxacnhan && $result_choxacnhan->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>Ngày Đặt</th>
                    <th>Tổng Tiền</th>
                    <th>Trạng Thái</th>
                    <th>Hành Động</th>
                </tr>
                <?php while($row = $result_choxacnhan->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['DH_MA'] ?></td>
                        <td><?= htmlspecialchars($row['ND_HOTEN']) ?></td>
                        <td><?= date("d/m/Y", strtotime($row['DH_NGAYDAT'])) ?></td>
                        <td><?= number_format($row['DH_TONGTHANHTOAN'], 0, ',', '.') ?> ₫</td>
                        <td><?= htmlspecialchars($row['TT_TEN']) ?></td>
                        <td>
                            <button class="btn-approve" onclick="approveOrder(<?= $row['DH_MA'] ?>, this)">Duyệt đơn</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
                <p style="text-align:center; color:#007bff; margin-top:10px;">Không có đơn hàng cần duyệt</p>
            <?php endif; ?>
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
            const tab = params.get("tab") || "donhang";

            document.querySelectorAll('.tab-content').forEach(tabEl => tabEl.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            document.getElementById(tab).classList.add('active');
            document.querySelector(`button[onclick*="${tab}"]`).classList.add('active');
        });


        function sendDVVC(dh_ma, dvvc_ma, dvvc_ten, btn) {
            if(!confirm('Bạn có chắc muốn gửi đơn này cho ' + dvvc_ten + '?')) return;

            btn.disabled = true;
            btn.innerText = 'Đang gửi...';

            let url = '';
            if(dvvc_ma == 1){
                url = 'tao_don_ghn.php';
            } else if(dvvc_ma == 2){
                url = 'tao_don_ghtk.php';
            } else {
                alert('Đơn vị vận chuyển chưa xác định!');
                btn.disabled = false;
                btn.innerText = 'Giao ' + dvvc_ten;
                return;
            }

            fetch(url, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'ma_don='+dh_ma
            })
            .then(r => r.json())
            .then(res => {
                if(res.ok){
                    alert('Tạo đơn thành công: ' + res.order_code);
                    btn.outerHTML = '<span style="color:green;">Đã gửi ' + dvvc_ten + '</span>';
                } else {
                    alert('Lỗi tạo đơn: ' + JSON.stringify(res.response));
                    btn.disabled = false;
                    btn.innerText = 'Giao ' + dvvc_ten;
                }
            })
            .catch(err=>{
                alert('Lỗi hệ thống: '+err);
                btn.disabled = false;
                btn.innerText = 'Giao ' + dvvc_ten;
            });
        }

        // Duyệt đơn
        function approveOrder(dh_ma, btn) {
            if (!confirm("Bạn có chắc muốn duyệt đơn #" + dh_ma + " không?")) return;

            btn.disabled = true;
            btn.innerText = "Đang duyệt...";

            fetch('duyet_don.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'dh_ma=' + dh_ma
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    alert("Duyệt đơn thành công!");

                    // Cập nhật lại dòng trong bảng
                    btn.parentElement.innerHTML = `<span style="color:green;">Đã duyệt</span>`;
                } else {
                    alert("Lỗi: " + data.msg);
                    btn.disabled = false;
                    btn.innerText = "Duyệt đơn";
                }
            })
            .catch(err => {
                alert("Lỗi hệ thống: " + err);
                btn.disabled = false;
                btn.innerText = "Duyệt đơn";
            });
        }

    </script>

<script>
// Tự động gọi update_ghn.php mỗi 30s
setInterval(() => {
    fetch("update_ghn_status.php")
        .then(res => res.json())
        .then(data => {
            console.log("GHN auto update:", data);

            // Nếu đơn có update → refresh trang để admin thấy ngay
            if (data.updated && data.updated.length > 0) {
                console.log("Có đơn thay đổi trạng thái:", data.updated);

                // Reload chỉ phần bảng đơn hàng để tránh giật trang
                location.reload();
            }
        })
        .catch(err => console.error("GHN fetch error:", err));
}, 30000); // 30 giây
</script>

</body>
</html>






