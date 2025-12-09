<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);

// Lấy tất cả danh mục
$all_dm_result = $conn->query("SELECT * FROM DANH_MUC ORDER BY DM_CHA ASC, DM_TEN ASC");
$dm_list = [];
while($row = $all_dm_result->fetch_assoc()) $dm_list[] = $row;


// Hàm hiển thị select danh mục cha (chỉ cấp 0 hoặc cấp 1)
function hienThiDanhMuc($list, $cha_id = null, $cap = 0, $path = []) {
    // Giới hạn chỉ hiển thị cấp 0 và 1
    if ($cap > 1) return;

    $con = array_filter($list, fn($dm) => $dm['DM_CHA'] == $cha_id);
    foreach ($con as $dm) {
        $current_path = $path;
        $current_path[] = $dm['DM_TEN'];

        $has_child = false;
        foreach ($list as $dm2) if($dm2['DM_CHA'] == $dm['DM_MA']) $has_child = true;

        $label = implode(' → ', $current_path);

        // Chỉ hạn chế cấp 2 trở lên, cấp 0 và 1 vẫn có thể chọn
        echo "<option value='{$dm['DM_MA']}'>" . str_repeat('&nbsp;&nbsp;&nbsp;', $cap) . htmlspecialchars($label) . "</option>";

        // Nếu chưa vượt quá cấp 1, tiếp tục duyệt con
        if ($cap < 1 && $has_child) {
            hienThiDanhMuc($list, $dm['DM_MA'], $cap + 1, $current_path);
        }
    }
}

// Hàm hiển thị tree view danh mục
function hienThiCayDanhMuc($list, $cha_id = null, $cap = 0) {
    $children = array_filter($list, fn($dm) => $dm['DM_CHA'] == $cha_id);
    if(!$children) return;

    echo '<ul class="category-tree">';
    foreach($children as $dm) {
        echo '<li>';
        $img = '';
        if($cap == 1 && !empty($dm['DM_ANH'])) { // chỉ hiển thị ảnh cho con cấp 1
            $img = "<img src='{$dm['DM_ANH']}' alt='{$dm['DM_TEN']}'>";
        }
        echo "<span class='dm-name'>" . $img . htmlspecialchars($dm['DM_TEN']) . "</span>";
        hienThiCayDanhMuc($list, $dm['DM_MA'], $cap+1);
        echo '</li>';
    }
    echo '</ul>';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản Lý Danh Mục</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="../assets/css/ql_danhmuc.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h2>📁 QUẢN LÝ DANH MỤC</h2>
        <a href="nhanvien.php" class="btn-back">← Quay lại Menu</a>
    </div>

    <div class="tab-buttons">
        <button class="tab-btn active" onclick="openTab(event,'danhmuc')">Danh sách danh mục</button>
        <button class="tab-btn" onclick="openTab(event,'themdm')">Thêm danh mục mới</button>
    </div>

    <!-- TAB 1: DANH SÁCH TREE VIEW -->
    <div id="danhmuc" class="tab-content">
        <?php hienThiCayDanhMuc($dm_list); ?>
    </div>

    <!-- TAB 2: THÊM DANH MỤC -->
    <div id="themdm" class="tab-content">
        <form action="xuly_them_danhmuc.php" method="post" enctype="multipart/form-data">
            <label>Tên danh mục:</label>
            <input type="text" name="DM_TEN" required class="input-field" placeholder="Tên danh mục">

            <label>Danh mục cha:</label>
            <select name="DM_CHA" class="input-field">
                <option value="">-- Không có --</option>
                <?php hienThiDanhMuc($dm_list); ?>
            </select>

            <label>Ảnh danh mục:</label>
            <input type="file" name="DM_ANH" accept="image/*" class="input-field">

            <button type="submit" class="btn-send-sp">💾 Lưu danh mục</button>
        </form>
    </div>
</div>

<script>
// Tab
function openTab(event, tabId){
    event.preventDefault();
    document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
    history.replaceState(null,'','?tab='+tabId);
}

// Tự động mở tab nếu URL có ?tab=
document.addEventListener("DOMContentLoaded", function(){
    const params = new URLSearchParams(window.location.search);
    const tab = params.get("tab") || "danhmuc";

    // Xóa active cũ
    document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));

    // Set active cho tab cần hiển thị
    const tabEl = document.getElementById(tab);
    if(tabEl) tabEl.classList.add('active');

    const btnEl = document.querySelector(`.tab-btn[onclick*="${tab}"]`);
    if(btnEl) btnEl.classList.add('active');

    // Collapse/expand tree view
    document.querySelectorAll('.category-tree .dm-name').forEach(span=>{
        span.addEventListener('click', function(){
            const li = span.parentElement;
            li.classList.toggle('active');
        });
    });
});

</script>
</body>
</html>
