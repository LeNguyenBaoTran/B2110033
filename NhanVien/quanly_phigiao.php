<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);

// Lấy danh sách đơn vị vận chuyển
$dvvc = $conn->query("SELECT * FROM don_vi_van_chuyen ORDER BY DVVC_TEN ASC");

// Lấy mức khoảng cách
$kc = $conn->query("SELECT * FROM dinh_muc_khoang_cach ORDER BY KC_MIN ASC");

// Lấy bảng phí giao
$pvc = $conn->query("
    SELECT p.*, d.DVVC_TEN, k.KC_MIN, k.KC_MAX
    FROM phi_van_chuyen p
    JOIN don_vi_van_chuyen d ON d.DVVC_MA = p.DVVC_MA
    JOIN dinh_muc_khoang_cach k ON k.KC_MA = p.KC_MA
    ORDER BY d.DVVC_TEN ASC, k.KC_MIN ASC
");

// Lấy danh sách đơn vị vận chuyển
$ds_dvvc = $conn->query("SELECT * FROM don_vi_van_chuyen ORDER BY DVVC_TEN ASC");

// Lấy danh sách định mức khoảng cách
$ds_kc = $conn->query("SELECT * FROM dinh_muc_khoang_cach ORDER BY KC_MIN ASC");


?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản Lý Phí Giao Hàng</title>
<link rel="stylesheet" href="../assets/css/ql_phigiao.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="../assets/css/order_manager.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <div class="top-bar">
        <h2>🚚 QUẢN LÝ PHÍ GIAO HÀNG</h2>
        <a href="nhanvien.php" class="btn-back">← Quay lại</a>
    </div>

    <div class="tab-buttons">
        <button class="tab-btn active" onclick="openTab(event,'list')">Danh sách phí giao</button>
        <button class="tab-btn" onclick="openTab(event,'add')">Thêm phí giao</button>
        <button class="tab-btn" onclick="openTab(event,'list-dvvc')">Quản lý đơn vị vận chuyển</button>
        <button class="tab-btn" onclick="openTab(event,'list-kc')">Quản lý định mức khoảng cách</button>
    </div>

    <!-- TAB 1: DANH SÁCH -->
    <div id="list" class="tab-content active">
        <table>
            <tr>
                <th>ĐV vận chuyển</th>
                <th>Khoảng cách</th>
                <th>Phí giao</th>
                <th>Hành động</th>
            </tr>
            <?php while($r = $pvc->fetch_assoc()): ?>
            <tr>
                <td><?= $r['DVVC_TEN'] ?></td>
                <td><?= $r['KC_MIN'] ?> km → <?= $r['KC_MAX'] ?> km</td>
                <td class="gia-cell"><?= number_format($r['PVC_GIAGIAO'],0,'.','.') ?> đ</td>
                <td>
                    <i class="fa-solid fa-pen-to-square btn-edit" 
                    data-dvvc="<?= $r['DVVC_MA'] ?>" 
                    data-kc="<?= $r['KC_MA'] ?>" 
                    style="cursor:pointer;"></i>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- TAB 2: THÊM MỚI -->
    <div id="add" class="tab-content">
        <form action="xuly_them_phigiao.php" method="post" class="wp-style-form">

            <div class="wp-card">
                <h3><i class="fa-solid fa-plus"></i> Thêm phí vận chuyển</h3>

                <label>Đơn vị vận chuyển:</label>
                <select name="DVVC_MA" class="input-field" required>
                    <option value="">-- Chọn đơn vị --</option>
                    <?php while($d = $dvvc->fetch_assoc()): ?>
                        <option value="<?= $d['DVVC_MA'] ?>"><?= $d['DVVC_TEN'] ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Khoảng cách (km):</label>
                <select name="KC_MA" class="input-field" required>
                    <option value="">-- Chọn khoảng cách --</option>
                    <?php while($k = $kc->fetch_assoc()): ?>
                        <option value="<?= $k['KC_MA'] ?>">
                            <?= $k['KC_MIN'] ?> → <?= $k['KC_MAX'] ?> km
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Phí giao (VNĐ):</label>
                <input type="number" name="PVC_GIAGIAO" min="1000" class="input-field" required placeholder="Nhập phí giao">
            </div>

                <button class="btn-phigiao"><i class="fa-solid fa-plus"></i> Thêm phí giao</button>
        </form>
    </div>

    <!-- TAB 3: QUẢN LÝ ĐƠN VỊ VẬN CHUYỂN -->
    <div id="list-dvvc" class="tab-content">
        <h3><i class="fa-solid fa-truck-fast"></i> Danh sách đơn vị vận chuyển</h3>
        <table>
            <tr>
                <th>Mã</th>
                <th>Tên đơn vị</th>
            </tr>

            <?php while($d = $ds_dvvc->fetch_assoc()): ?>
            <tr>
                <td><?= $d['DVVC_MA'] ?></td>
                <td class="ten-dvvc"><?= htmlspecialchars($d['DVVC_TEN']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h3>➕ Thêm đơn vị vận chuyển</h3>
        <form action="xuly_them_dvvc.php" method="post" class="wp-style-form">
            <div class="wp-card">
                <label>Tên đơn vị vận chuyển:</label>
                <input type="text" name="DVVC_TEN" class="input-field" required>
            </div>
            <button class="btn-phigiao">
                <i class="fa-solid fa-plus"></i> Thêm đơn vị vận chuyển
            </button>
        </form>
    </div>


    <!-- TAB 4: QUẢN LÝ ĐỊNH MỨC KHOẢNG CÁCH -->
    <div id="list-kc" class="tab-content">
        <h3><i class="fa-solid fa-ruler-horizontal"></i> Định mức khoảng cách</h3>
        <table>
            <tr>
                <th>Mã</th>
                <th>Min (km)</th>
                <th>Max (km)</th>
            </tr>

            <?php while($k = $ds_kc->fetch_assoc()): ?>
            <tr>
                <td><?= $k['KC_MA'] ?></td>
                <td><?= $k['KC_MIN'] ?></td>
                <td><?= $k['KC_MAX'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h3>➕ Thêm định mức khoảng cách</h3>
        <form action="xuly_them_kc.php" method="post" class="wp-style-form">
            <div class="wp-card">

                <label>Khoảng min (km):</label>
                <input type="number" step="0.1" name="KC_MIN" class="input-field" required>

                <label>Khoảng max (km):</label>
                <input type="number" step="0.1" name="KC_MAX" class="input-field" required>

            </div>

            <button class="btn-phigiao">
                <i class="fa-solid fa-plus"></i> Thêm khoảng cách
            </button>
        </form>
    </div>

</div>

<script>
function openTab(event, tabId){
    document.querySelectorAll(".tab-content").forEach(e=>e.classList.remove("active"));
    document.querySelectorAll(".tab-btn").forEach(e=>e.classList.remove("active"));
    document.getElementById(tabId).classList.add("active");
    event.currentTarget.classList.add("active");
}

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(){
        const dvvc = this.dataset.dvvc;
        const kc = this.dataset.kc;

        const cell = this.closest('tr').querySelector('.gia-cell');
        let currentGia = cell.textContent.replace(/\D/g,''); // bỏ đ và dấu chấm
        currentGia = parseInt(currentGia);

        // Thay cell bằng input
        cell.innerHTML = `<input type="number" min="1000" value="${currentGia}" style="width:120px">
                          <button class="btn-save">Lưu</button>
                          <button class="btn-cancel">Hủy</button>`;

        // Lưu
        cell.querySelector('.btn-save').addEventListener('click', function(){
            let newGia = parseInt(cell.querySelector('input').value);

            if(isNaN(newGia) || newGia < 1000){
                alert('Giá phải là số >= 1000 VNĐ');
                return;
            }

            fetch('xuly_sua_phigiao.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `DVVC_MA=${dvvc}&KC_MA=${kc}&PVC_GIAGIAO=${newGia}`
            })
            .then(res=>res.text())
            .then(data=>{
                if(data.trim()=='OK'){
                    cell.innerHTML = new Intl.NumberFormat('vi-VN').format(newGia) + ' đ';
                } else {
                    alert('Có lỗi: ' + data);
                }
            });
        });

        // Hủy
        cell.querySelector('.btn-cancel').addEventListener('click', function(){
            cell.innerHTML = new Intl.NumberFormat('vi-VN').format(currentGia) + ' đ';
        });
    });
});
</script>


</body>
</html>
