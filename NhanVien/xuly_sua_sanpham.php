<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) die("Lỗi: " . $conn->connect_error);

// Lấy dữ liệu từ form
$sp_ma = $_POST["SP_MA"];
$ten = trim($_POST["SP_TEN"]);
$chatlieu = trim($_POST["SP_CHATLIEU"]);
$mota = trim($_POST["SP_MOTA"]);
$consudung = $_POST["SP_CONSUDUNG"];

// ===========================
// KIỂM TRA HỢP LỆ TRƯỚC KHI SỬA
// ===========================

// Tên
if ($ten == "") {
    echo "<script>alert('Tên sản phẩm không được để trống!'); history.back();</script>";
    exit;
}

// Chất liệu
if ($chatlieu == "") {
    echo "<script>alert('Chất liệu không được để trống!'); history.back();</script>";
    exit;
}

// Mô tả
if ($mota == "") {
    echo "<script>alert('Mô tả không được để trống!'); history.back();</script>";
    exit;
}

// Giá mới nếu có
if (!empty($_POST["DONGIA_MOI"])) {
    if (!is_numeric($_POST["DONGIA_MOI"]) || $_POST["DONGIA_MOI"] <= 0) {
        echo "<script>alert('Giá mới phải là số lớn hơn 0!'); history.back();</script>";
        exit;
    }
}

// Kiểm tra tồn kho
if (isset($_POST["size"])) {
    foreach ($_POST["size"] as $kt_ma => $soluong) {
        if (!is_numeric($soluong) || $soluong < 0) {
            echo "<script>alert('Tồn kho phải là số không âm!'); history.back();</script>";
            exit;
        }
    }
}

// Kiểm tra file upload (nếu có)
$valid_extensions = ["jpg", "jpeg", "png", "webp"];

if (!empty($_FILES["ANH"]["name"][0])) {
    foreach ($_FILES["ANH"]["name"] as $i => $filename) {
        if ($_FILES["ANH"]["error"][$i] == 0) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $valid_extensions)) {
                echo "<script>alert('File ảnh \"$filename\" không hợp lệ! Chỉ chấp nhận JPG, PNG, WEBP.'); history.back();</script>";
                exit;
            }
        }
    }
}

// =======================================
// NẾU KHÔNG LỖI → BẮT ĐẦU CẬP NHẬT
// =======================================

// Cập nhật sản phẩm
$update_sp = "
    UPDATE SAN_PHAM 
    SET SP_TEN='$ten', SP_CHATLIEU='$chatlieu', SP_MOTA='$mota', SP_CONSUDUNG='$consudung'
    WHERE SP_MA='$sp_ma'
";
$conn->query($update_sp);


// Cập nhật giá
if (!empty($_POST["DONGIA_MOI"]) && $_POST["DONGIA_MOI"] > 0) {

    $gia_moi = $_POST["DONGIA_MOI"];
    $sp_ma = intval($_POST["SP_MA"]);

    // Thời điểm hiện tại
    $td = date("Y-m-d H:i:s");

    // 1. Thêm thời điểm mới vào bảng thoi_diem (nếu chưa tồn tại)
    $sql_td = "INSERT INTO thoi_diem (TD_THOIDIEM) VALUES ('$td')";
    $conn->query($sql_td);

    // 2. Thêm giá mới vào bảng don_gia_ban
    $sql_gia = "
        INSERT INTO don_gia_ban (SP_MA, TD_THOIDIEM, DONGIA)
        VALUES ('$sp_ma', '$td', '$gia_moi')
    ";
    $conn->query($sql_gia);
}


// Cập nhật tồn kho
if (isset($_POST["size"])) {
    foreach ($_POST["size"] as $kt_ma => $soluong) {
        $soluong = intval($soluong);

        $sql_update_size = "
            UPDATE CHI_TIET_SAN_PHAM 
            SET CTSP_SOLUONGTON='$soluong'
            WHERE SP_MA='$sp_ma' AND KT_MA='$kt_ma'
        ";
        $conn->query($sql_update_size);
    }
}


// Upload ảnh nếu có
if (!empty($_FILES["ANH"]["name"][0])) {

    $folder = "../uploads/sanpham/";
    if (!file_exists($folder)) mkdir($folder, 0777, true);

    foreach ($_FILES["ANH"]["name"] as $i => $filename) {
        if ($_FILES["ANH"]["error"][$i] == 0) {

            $tmp = $_FILES["ANH"]["tmp_name"][$i];
            $newname = time() . "_" . $filename;
            $path = $folder . $newname;

            move_uploaded_file($tmp, $path);

            $sql_img = "
                INSERT INTO ANH_SAN_PHAM (SP_MA, ANH_DUONGDAN)
                VALUES ('$sp_ma', '$path')
            ";
            $conn->query($sql_img);
        }
    }
}


// Xong → thông báo + chuyển hướng
echo "
<script>
    alert('Cập nhật sản phẩm thành công!');
    window.location.href = 'suasanpham.php?sp_ma=$sp_ma';
</script>
";

?>
