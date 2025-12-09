<?php
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy thông tin khách hàng
$sql_khachhang = "SELECT nd.ND_MA, nd.ND_HOTEN, nd.ND_EMAIL, nd.ND_SDT, nd.ND_DIACHI, kh.KH_DIEMTICHLUY
FROM nguoi_dung nd
INNER JOIN khach_hang kh ON nd.ND_MA = kh.ND_MA
ORDER BY nd.ND_MA ASC";
$result_khachhang = $conn->query($sql_khachhang);

// Lấy thông tin nhân viên
$sql_nhanvien = "SELECT nd.ND_MA, nd.ND_HOTEN, nd.ND_EMAIL, nd.ND_SDT, nd.ND_DIACHI, nv.NV_CCCD
FROM nguoi_dung nd
INNER JOIN nhan_vien nv ON nd.ND_MA = nv.ND_MA
ORDER BY nd.ND_MA ASC";
$result_nhanvien = $conn->query($sql_nhanvien);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản Lý Người Dùng</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="../assets/css/order_manager.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h2><i class="fa-solid fa-users"></i> QUẢN LÝ NGƯỜI DÙNG</h2>
            <a href="nhanvien.php" class="btn-back">← Quay lại Menu</a>
        </div>

        <div class="tab-buttons">
            <button class="tab-btn active" onclick="openTab(event, 'khachhang')">Danh Sách Khách Hàng</button>
            <button class="tab-btn" onclick="openTab(event, 'nhanvien')">Danh Sách Nhân Viên</button>
        </div>

        <!-- TAB 1 -->
        <div id="khachhang" class="tab-content active">
            <div class="list-header">
                <input type="text" id="searchKH" class="search-box" placeholder="🔍 Tìm khách hàng...">
                <a href="them_khachhang.php" class="btn-add">+ Thêm Khách Hàng</a>
            </div>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>Mã KH</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>SĐT</th>
                        <th>Địa chỉ</th>
                        <th>Điểm TL</th>
                    </tr>
                </thead>
                <tbody id="tableKH">
                    <?php while ($row = $result_khachhang->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['ND_MA'] ?></td>
                        <td><?= htmlspecialchars($row['ND_HOTEN']) ?></td>
                        <td><?= htmlspecialchars($row['ND_EMAIL']) ?></td>
                        <td><?= htmlspecialchars($row['ND_SDT']) ?></td>
                        <td><?= htmlspecialchars($row['ND_DIACHI']) ?></td>
                        <td><?= number_format($row['KH_DIEMTICHLUY']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>


        <!-- TAB 2: LỊCH SỬ ĐƠN HÀNG -->
        <div id="nhanvien" class="tab-content">
            <div class="list-header">
                <input type="text" id="searchNV" class="search-box" placeholder="🔍 Tìm nhân viên...">
                <a href="them_nhanvien.php" class="btn-add">+ Thêm Nhân Viên</a>
            </div>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>Mã NV</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>SĐT</th>
                        <th>Địa chỉ</th>
                        <th>CCCD</th>
                    </tr>
                </thead>
                <tbody id="tableNV">
                    <?php while ($row = $result_nhanvien->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['ND_MA'] ?></td>
                        <td><?= htmlspecialchars($row['ND_HOTEN']) ?></td>
                        <td><?= htmlspecialchars($row['ND_EMAIL']) ?></td>
                        <td><?= htmlspecialchars($row['ND_SDT']) ?></td>
                        <td><?= htmlspecialchars($row['ND_DIACHI']) ?></td>
                        <td><?= htmlspecialchars($row['NV_CCCD']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
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
            const tab = params.get("tab") || "khachhang";

            document.querySelectorAll('.tab-content').forEach(tabEl => tabEl.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            document.getElementById(tab).classList.add('active');
            document.querySelector(`button[onclick*="${tab}"]`).classList.add('active');
        });

        // Tìm kiếm
        // Tìm kiếm khách hàng
        document.getElementById("searchKH").addEventListener("keyup", function () {
            let keyword = this.value.toLowerCase();
            document.querySelectorAll("#tableKH tr").forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(keyword) ? "" : "none";
            });
        });

        // Tìm kiếm nhân viên
        document.getElementById("searchNV").addEventListener("keyup", function () {
            let keyword = this.value.toLowerCase();
            document.querySelectorAll("#tableNV tr").forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(keyword) ? "" : "none";
            });
        });

    </script>
</body>
</html>
