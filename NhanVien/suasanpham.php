<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) die("Lỗi: " . $conn->connect_error);

$sp_ma = $_GET["sp_ma"] ?? 0;
if (!$sp_ma) die("Thiếu mã sản phẩm!");

// Lấy thông tin sản phẩm
$sql = "SELECT * FROM SAN_PHAM WHERE SP_MA = '$sp_ma'";
$sp = $conn->query($sql)->fetch_assoc();
if (!$sp) die("Không tìm thấy sản phẩm!");

// Lấy ảnh
$anh = $conn->query("SELECT * FROM ANH_SAN_PHAM WHERE SP_MA='$sp_ma'")->fetch_all(MYSQLI_ASSOC);

// Lấy kích thước + tồn kho
$size_sql = "
    SELECT kt.KT_MA, kt.KT_TEN, ct.CTSP_SOLUONGTON
    FROM CHI_TIET_SAN_PHAM ct
    JOIN KICH_THUOC kt ON ct.KT_MA = kt.KT_MA
    WHERE ct.SP_MA = '$sp_ma'
    ORDER BY kt.KT_TEN
";
$sizes = $conn->query($size_sql)->fetch_all(MYSQLI_ASSOC);

// Lấy giá mới nhất theo thời điểm gần nhất
$gia_sql = "
    SELECT DONGIA 
    FROM don_gia_ban 
    WHERE SP_MA = '$sp_ma'
    ORDER BY TD_THOIDIEM DESC 
    LIMIT 1
";
$gia = $conn->query($gia_sql)->fetch_assoc();
$gia_hientai = $gia['DONGIA'] ?? 0;

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Sửa sản phẩm</title>
<link href="../assets/css/sua_sp.css" rel="stylesheet">
</head>
<body>
<div class="container-main">
    <div class="back-wrapper">
        <a href="quanly_sanpham.php" class="btn-back">⬅ Quay lại</a>
    </div>

    <h2>Sửa sản phẩm: <?= $sp["SP_TEN"] ?></h2>

    <form action="xuly_sua_sanpham.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="SP_MA" value="<?= $sp_ma ?>">

        <label>Tên sản phẩm:</label>
        <input type="text" name="SP_TEN" class="input" value="<?= $sp['SP_TEN'] ?>">

        <label>Chất liệu:</label>
        <input type="text" name="SP_CHATLIEU" class="input" value="<?= $sp['SP_CHATLIEU'] ?>">

        <label>Mô tả:</label>
        <textarea name="SP_MOTA" class="input" rows="8"><?= $sp['SP_MOTA'] ?></textarea>

        <label>Giá gốc:</label>
        <input type="text" class="input" value="<?= number_format($gia_hientai) ?> VNĐ" disabled>

        <label>Giá mới (nếu thay đổi):</label>
        <input type="number" name="DONGIA_MOI" class="input" min="0" step="1000" placeholder="Nhập giá mới">


        <label>Đang sử dụng:</label>
        <select name="SP_CONSUDUNG" class="input">
            <option value="1" <?= $sp['SP_CONSUDUNG']?'selected':'' ?>>Có</option>
            <option value="0" <?= !$sp['SP_CONSUDUNG']?'selected':'' ?>>Không</option>
        </select>

        <hr>
        <label>📸 Ảnh hiện tại</label>
        <?php foreach($anh as $a): ?>
            <img src="<?= $a['ANH_DUONGDAN'] ?>" class="product-img">
        <?php endforeach; ?>

        <br><br>
        <label>Thêm ảnh mới (tuỳ chọn):</label>
        <input type="file" name="ANH[]" multiple class="input">

        <hr>
        <label>📏 Kích thước & Tồn kho</label>

        <div class="sizes-grid">
        <?php foreach($sizes as $s): ?>
            <div class="size-box">
                <label><?= $s["KT_TEN"] ?>:</label>
                <input type="number" name="size[<?= $s['KT_MA'] ?>]" 
                    class="input" min="0"
                    value="<?= $s['CTSP_SOLUONGTON'] ?? 0 ?>">
            </div>
        <?php endforeach; ?>
        </div>

        <br>
        <button type="submit" class="btn-save">
            💾 Lưu thay đổi
        </button>
    </form>
</div>

</body>
</html>
