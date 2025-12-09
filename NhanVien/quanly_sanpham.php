<?php
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Phân trang
$limit = 30; 
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Lọc theo từ khóa và danh mục
$keyword = $_GET['keyword'] ?? '';
$dm_filter = $_GET['dm_filter'] ?? '';

$conditions = [];
if ($keyword) {
    $keyword_safe = $conn->real_escape_string($keyword);
    $conditions[] = "sp.SP_TEN LIKE '%$keyword_safe%'";
}

if ($dm_filter) {
    $dm_filter = (int)$dm_filter;
    $conditions[] = "sp.DM_MA = $dm_filter";
}

$where = '';
if ($conditions) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}


// Truy vấn sản phẩm
$sql_sp = "SELECT 
    sp.SP_MA,
    sp.SP_TEN,
    sp.SP_CHATLIEU,
    sp.SP_MOTA,
    sp.SP_CONSUDUNG,
    COALESCE(CONCAT(dm_cha.DM_TEN, ' → ', dm.DM_TEN), dm.DM_TEN) AS DM_TEN,
    sp.SP_NGAYTHEM,
    g.DONGIA AS GIA_GOC,
    ROUND(g.DONGIA * (1 - IFNULL(km.CTKM_PHANTRAM_GIAM, 0) / 100), 0) AS GIA_MOI,
    km.CTKM_PHANTRAM_GIAM,
    a.ANH_DUONGDAN AS ANH_DAIDIEN,
    SUM(ct.CTSP_SOLUONGTON) AS SOLUONG_TON,
    COALESCE(d.SL_BAN, 0) AS DA_BAN

FROM SAN_PHAM sp
LEFT JOIN DANH_MUC dm ON sp.DM_MA = dm.DM_MA
LEFT JOIN DANH_MUC dm_cha ON dm.DM_CHA = dm_cha.DM_MA
LEFT JOIN (
    SELECT SP_MA, DONGIA
    FROM DON_GIA_BAN
    WHERE (SP_MA, TD_THOIDIEM) IN (
        SELECT SP_MA, MAX(TD_THOIDIEM)
        FROM DON_GIA_BAN
        GROUP BY SP_MA
    )
) g ON sp.SP_MA = g.SP_MA
LEFT JOIN (
    SELECT sp1.SP_MA, sp1.ANH_DUONGDAN
    FROM ANH_SAN_PHAM sp1
    JOIN (
        SELECT SP_MA, MIN(ANH_MA) AS min_anh
        FROM ANH_SAN_PHAM
        GROUP BY SP_MA
    ) sp2 ON sp1.SP_MA = sp2.SP_MA AND sp1.ANH_MA = sp2.min_anh
) a ON sp.SP_MA = a.SP_MA
LEFT JOIN (
    SELECT ctkm.SP_MA, ctkm.CTKM_PHANTRAM_GIAM
    FROM CHI_TIET_KHUYEN_MAI ctkm
    JOIN KHUYEN_MAI km ON ctkm.KM_MA = km.KM_MA
    WHERE NOW() BETWEEN km.KM_NGAYBATDAU AND km.KM_NGAYKETTHUC
      AND km.KM_CONSUDUNG = 1
) km ON sp.SP_MA = km.SP_MA
LEFT JOIN chi_tiet_san_pham ct ON sp.SP_MA = ct.SP_MA
LEFT JOIN (
    SELECT ctdh.SP_MA, SUM(ctdh.CTDH_SOLUONG) AS SL_BAN
    FROM CHI_TIET_DON_HANG ctdh
    JOIN DON_HANG dh ON ctdh.DH_MA = dh.DH_MA
    WHERE dh.DH_TRANGTHAI NOT IN ('Đã hủy', 'Hoàn hàng', 'Đã hoàn tiền')
    GROUP BY ctdh.SP_MA
) d ON sp.SP_MA = d.SP_MA

$where

GROUP BY 
    sp.SP_MA, sp.SP_TEN, sp.SP_CHATLIEU, sp.SP_MOTA, sp.SP_CONSUDUNG,
    dm.DM_TEN, dm_cha.DM_TEN, sp.SP_NGAYTHEM,
    g.DONGIA, km.CTKM_PHANTRAM_GIAM, a.ANH_DUONGDAN, d.SL_BAN

ORDER BY sp.SP_MA ASC
LIMIT $limit OFFSET $offset";

$result_sp = $conn->query($sql_sp);
if (!$result_sp) die("Lỗi truy vấn: " . $conn->error);

// Tổng số sản phẩm để phân trang
$total_result = $conn->query("SELECT COUNT(*) AS total FROM SAN_PHAM");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Lấy danh mục cho tab 2
$dm_result = $conn->query("SELECT DM_MA, DM_TEN, DM_CHA FROM DANH_MUC ORDER BY DM_CHA, DM_TEN ASC");
$dm_list = [];
while ($dm = $dm_result->fetch_assoc()) {
    $dm_list[] = $dm;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản Lý Sản Phẩm</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/picmo@latest/dist/picmo.css">
<link href="../assets/css/order_manager.css" rel="stylesheet">
<style>
#preview-anh img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border: 1px solid #ccc;
    border-radius: 4px;
}
#preview-anh { margin-top:10px; display:flex; gap:5px; flex-wrap:wrap; }
</style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h2>📦 QUẢN LÝ SẢN PHẨM</h2>
        <a href="nhanvien.php" class="btn-back">← Quay lại Menu</a>
    </div>

    <div class="tab-buttons">
        <button type="button" class="tab-btn active" onclick="openTab(event,'sanpham')">Danh Sách Sản Phẩm</button>
        <button type="button" class="tab-btn" onclick="openTab(event,'themsp')">Thêm Sản Phẩm Mới</button>
    </div>

    <!-- TAB 1 -->
    <div id="sanpham" class="tab-content active">
        <!-- Nút cập nhật FAISS index -->
        <div class="reload-wrapper">
            <button id="btnReloadIndex">
                🔄 Cập nhật tìm kiếm ảnh
            </button>
            <span id="reloadStatus"></span>
        </div>
        <!-- Form tìm kiếm & lọc -->
        <form method="get" class="search-filter-form" style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
            <input type="text" name="keyword" placeholder="Tìm theo tên sản phẩm..." value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" class="input-field">
            
            <select name="dm_filter" class="input-field">
                <option value="">-- Lọc theo danh mục --</option>
                <?php
                function hienThiDanhMucConOption($list, $cha_id = null, $cap = 0, $selected = '') {
                    $con = array_filter($list, fn($dm) => $dm['DM_CHA'] == $cha_id);
                    foreach ($con as $dm) {
                        $isSelected = ($dm['DM_MA'] == $selected) ? 'selected' : '';
                        // Kiểm tra có con hay không
                        $co_con = false;
                        foreach ($list as $dm2) {
                            if ($dm2['DM_CHA'] == $dm['DM_MA']) {
                                $co_con = true;
                                break;
                            }
                        }
                        if ($co_con) {
                            // Nhóm cha có con
                            echo "<optgroup label='" . str_repeat('&nbsp;&nbsp;&nbsp;', $cap) . htmlspecialchars($dm['DM_TEN']) . "'>";
                            hienThiDanhMucConOption($list, $dm['DM_MA'], $cap + 1, $selected);
                            echo "</optgroup>";
                        } else {
                            // Cấp con, chỉ thụt lề
                            echo "<option value='{$dm['DM_MA']}' $isSelected>" . str_repeat('&nbsp;&nbsp;&nbsp;', $cap) . htmlspecialchars($dm['DM_TEN']) . "</option>";
                        }
                    }
                }
                hienThiDanhMucConOption($dm_list, null, 0, $_GET['dm_filter'] ?? '');
                ?>
            </select>

            <button type="submit" class="btn-search">🔍 Tìm kiếm</button>
            <a href="?" class="btn-reset">♻️ Làm mới</a>
        </form>

        <table border="1" cellspacing="0" cellpadding="5">
            <thead>
                <tr>
                    <th class="col-masp">SP_MA</th>
                    <th>Ảnh đại diện</th>
                    <th>Tên</th>
                    <th>Chất liệu</th>
                    <th>Mô tả</th>
                    <th>Danh mục</th>
                    <th>Giá gốc</th>
                    <th>Giá khuyến mãi</th>
                    <th>% Giảm</th>
                    <th>Số lượng tồn</th>
                    <th>Đã bán</th>
                    <th>Đang sử dụng</th>
                    <th>Ngày thêm</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result_sp->fetch_assoc()):
                    $img = $row['ANH_DAIDIEN'] ? "<img src='{$row['ANH_DAIDIEN']}' width='50'>" : '';
                    $mo_ta = mb_strlen($row['SP_MOTA'])>30 ? mb_substr($row['SP_MOTA'],0,45).'...' : $row['SP_MOTA'];
                ?>
                <tr>
                    <td class="col-masp"><?= $row['SP_MA'] ?></td>
                    <td><?= $img ?></td>
                    <td><?= $row['SP_TEN'] ?></td>
                    <td><?= $row['SP_CHATLIEU'] ?></td>
                    <td><?= $mo_ta ?></td>
                    <td><?= $row['DM_TEN'] ?></td>
                    <td><?= number_format($row['GIA_GOC'],0,',','.') ?></td>
                    <td><?= number_format($row['GIA_MOI'],0,',','.') ?></td>
                    <td><?= $row['CTKM_PHANTRAM_GIAM'] ? round($row['CTKM_PHANTRAM_GIAM']).'%' : '-' ?></td>
                    <td><?= $row['SOLUONG_TON'] ?></td>
                    <td><?= $row['DA_BAN'] ?? 0 ?></td>
                    <td><?= $row['SP_CONSUDUNG'] ? 'Có':'Không' ?></td>
                    <td><?= date("d/m/Y H:i:s", strtotime($row['SP_NGAYTHEM'])) ?></td>
                    <td>
                        <a href="suasanpham.php?sp_ma=<?= $row['SP_MA'] ?>" class="btn-edit">✏️ Sửa</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php for($i=1;$i<=$total_pages;$i++): ?>
                <div class="page-item <?= ($i==$page)?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&tab=sanpham"><?= $i ?></a>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- TAB 2: Thêm sản phẩm -->
    <div id="themsp" class="tab-content">
    <form action="xuly_them_sanpham.php" method="post" enctype="multipart/form-data" class="add-product-form wp-style-form">
        
        <!-- KHỐI 1: THÔNG TIN CHUNG -->
        <div class="wp-card">
        <h3>📝 Thông tin sản phẩm</h3>
        <label>Danh mục:</label>
        <select name="DM_MA" required class="input-field">
            <option value="">-- Chọn danh mục con --</option>
            <?php
            function hienThiDanhMucCon($list, $cha_id = null, $cap = 0) {
                $con = array_filter($list, fn($dm) => $dm['DM_CHA'] == $cha_id);
                foreach ($con as $dm) {
                    $co_con = false;
                    foreach ($list as $dm2) {
                        if ($dm2['DM_CHA'] == $dm['DM_MA']) {
                            $co_con = true;
                            break;
                        }
                    }
                    if ($co_con) {
                        echo "<optgroup label='" . str_repeat('— ', $cap) . htmlspecialchars($dm['DM_TEN']) . "'>";
                        hienThiDanhMucCon($list, $dm['DM_MA'], $cap + 1);
                        echo "</optgroup>";
                    } else {
                        echo "<option value='{$dm['DM_MA']}'>" . str_repeat('&nbsp;&nbsp;&nbsp;', $cap) . "↳ " . htmlspecialchars($dm['DM_TEN']) . "</option>";
                    }
                }
            }
            hienThiDanhMucCon($dm_list);
            ?>
        </select>

        <label>Tên sản phẩm:</label>
        <input type="text" name="SP_TEN" required class="input-field emoji-field" placeholder="Ví dụ: Áo sơ mi trắng 😎">

        <label>Chất liệu:</label>
        <input type="text" name="SP_CHATLIEU" class="input-field" placeholder="Ví dụ: Cotton 100% 🧵">

        <label>Mô tả sản phẩm:</label>
        <textarea name="SP_MOTA" rows="4" class="input-field emoji-field" placeholder="Nhập mô tả có thể kèm emoji 💡✨..."></textarea>

        <label>Đang sử dụng:</label>
        <select name="SP_CONSUDUNG" class="input-field">
            <option value="1">Có</option>
            <option value="0">Không</option>
        </select>
        </div>

        <!-- KHỐI 2: ẢNH & GIÁ -->
        <div class="wp-card">
        <h3>🖼️ Ảnh & Giá bán</h3>

        <label>Ảnh sản phẩm:</label>
        <input type="file" name="ANH[]" multiple accept="image/*" class="input-field" id="input-anh">
        <small>📷 Bạn có thể chọn nhiều ảnh cùng lúc.</small>
        <div id="preview-anh"></div>

        <br><label>Đơn giá bán:</label>
        <input type="number" name="GIA_BAN" min="0" required class="input-field" placeholder="Nhập giá (VND)">
        </div>

        <!-- KHỐI 3: KÍCH THƯỚC -->
        <div class="wp-card">
        <h3>📏 Kích thước & Tồn kho</h3>
        <p>Chọn kích thước có sẵn và nhập số lượng tồn kho cho từng kích thước:</p>
        <div class="size-container">
            <?php
            $size_result = $conn->query("SELECT KT_MA, KT_TEN FROM KICH_THUOC ORDER BY KT_TEN ASC");
            while ($row = $size_result->fetch_assoc()) {
            echo '<div class="size-item">';
            echo '<label><input type="checkbox" name="kichthuoc['.$row['KT_MA'].'][chon]" value="1"> '.$row['KT_TEN'].'</label>';
            echo '<input type="number" name="kichthuoc['.$row['KT_MA'].'][soluong]" min="0" value="0" class="input-field" placeholder="Số lượng">';
            echo '</div>';
            }
            ?>
        </div>
        </div>

        <!-- NÚT THÊM -->
        <div class="wp-card" style="text-align:right;">
        <button type="submit" class="btn-send-sp">💾 Lưu sản phẩm</button>
        </div>

    </form>
    </div>


<script src="https://cdn.jsdelivr.net/npm/picmo@latest/dist/picmo.min.js"></script>

<script>
function openTab(event, tabId){
    event.preventDefault();
    document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
    history.replaceState(null,'','?tab='+tabId);
}

document.addEventListener("DOMContentLoaded", function(){
    const params = new URLSearchParams(window.location.search);
    const tab = params.get("tab") || "sanpham";
    document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
    const tabEl = document.getElementById(tab);
    if(tabEl) tabEl.classList.add('active');
    const btnEl = document.querySelector(`.tab-btn[onclick*="${tab}"]`);
    if(btnEl) btnEl.classList.add('active');

    // --- Preview ảnh sản phẩm ---
    const inputAnh = document.getElementById('input-anh');
    const previewAnh = document.getElementById('preview-anh');
    inputAnh.addEventListener('change', function() {
        previewAnh.innerHTML = '';
        const files = inputAnh.files;
        for(let i=0; i<files.length; i++){
            const file = files[i];
            if(!file.type.startsWith('image/')) continue;
            const reader = new FileReader();
            reader.onload = function(e){
                const img = document.createElement('img');
                img.src = e.target.result;
                previewAnh.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    });
});

// Emoji
document.querySelectorAll('.emoji-field').forEach(field => {
  field.addEventListener('focus', () => {
    const picker = picmo.createPopup({
      referenceElement: field,
      position: 'bottom-start'
    });
    picker.addEventListener('emoji:select', e => {
      field.value += e.emoji;
    });
    picker.toggle();
  });
});

// Kích thước
const form = document.querySelector('.add-product-form');
const sizeItems = form.querySelectorAll('.size-item');
sizeItems.forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    const qtyInput = item.querySelector('input[type="number"]');
    qtyInput.disabled = !checkbox.checked;
    checkbox.addEventListener('change', () => {
        qtyInput.disabled = !checkbox.checked;
        if (!checkbox.checked) qtyInput.value = 0;
    });
});

// Kiểm tra form
form.addEventListener('submit', function(e) {
    const category = form.querySelector('select[name="DM_MA"]');
    if (!category.value) { alert("Vui lòng chọn danh mục!"); category.focus(); e.preventDefault(); return; }
    const nameInput = form.querySelector('input[name="SP_TEN"]');
    if (!nameInput.value.trim()) { alert("Vui lòng nhập tên sản phẩm!"); nameInput.focus(); e.preventDefault(); return; }
    if (!/^[a-zA-Z\sÀ-ỹ]+$/.test(nameInput.value)) { alert("Tên sản phẩm chỉ được chứa chữ và khoảng trắng!"); nameInput.focus(); e.preventDefault(); return; }
    const priceInput = form.querySelector('input[name="GIA_BAN"]');
    if (!priceInput.value || parseFloat(priceInput.value) <= 0) { alert("Vui lòng nhập đơn giá lớn hơn 0!"); priceInput.focus(); e.preventDefault(); return; }
    const filesInput = form.querySelector('input[type="file"][name="ANH[]"]');
    if (filesInput.files.length === 0) { alert("Vui lòng chọn ít nhất 1 ảnh sản phẩm!"); filesInput.focus(); e.preventDefault(); return; }
    let hasChecked = false;
    for (let item of sizeItems) {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const qtyInput = item.querySelector('input[type="number"]');
        if (parseInt(qtyInput.value) < 0) { alert("Số lượng không được nhỏ hơn 0!"); qtyInput.focus(); e.preventDefault(); return; }
        if (checkbox.checked) { hasChecked = true; if (!qtyInput.value || parseInt(qtyInput.value) <= 0) { alert("Số lượng phải > 0 cho các kích thước đã chọn!"); qtyInput.focus(); e.preventDefault(); return; } }
    }
    if (!hasChecked) { alert("Vui lòng chọn ít nhất 1 kích thước!"); e.preventDefault(); return; }
});
</script>

<script>
document.getElementById("btnReloadIndex").addEventListener("click", function () {
    const status = document.getElementById("reloadStatus");
    status.innerHTML = "⏳ Đang cập nhật...";

    fetch("http://127.0.0.1:5000/reload_index", {
        method: "POST"
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "success") {
            status.innerHTML = "✅ Cập nhật thành công!";
        } else {
            status.innerHTML = "❌ Lỗi: " + data.message;
        }
    })
    .catch(err => {
        status.innerHTML = "❌ Không kết nối được Flask!";
    });
});
</script>

</body>
</html>
