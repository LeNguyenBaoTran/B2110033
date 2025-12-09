<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);

/*
 * Cấp 0 = không có cha
 * Cấp 1 = có cha
 */
function getCategoryLevel($conn, $parent_id) {
    if ($parent_id === "" || $parent_id === null) return 0;
    return 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $DM_TEN = trim($_POST['DM_TEN'] ?? '');
    $DM_CHA = $_POST['DM_CHA'] ?? '';

    if ($DM_TEN === '') die("Tên danh mục không được để trống.");

    // Lấy cấp thật của danh mục
    $new_level = getCategoryLevel($conn, $DM_CHA);

    $DM_ANH = null;

    /* =============================
       CẤP 1 → Phải có ảnh
       ============================= */
    if ($new_level == 1) {

        if (!empty($_FILES['DM_ANH']['name'])) {

            if ($_FILES['DM_ANH']['error'] == 0) {
                
                $allowed_ext = ['jpg','jpeg','png','gif','webp'];
                $file_name = $_FILES['DM_ANH']['name']; 
                $file_tmp = $_FILES['DM_ANH']['tmp_name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_ext)) {
                    die("Chỉ cho phép ảnh: " . implode(', ', $allowed_ext));
                }

                // Thư mục upload ảnh
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/LV_QuanLy_BanTrangPhuc/assets/images/";

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $destination = $uploadDir . $file_name;

                if (file_exists($destination)) unlink($destination);

                if (!move_uploaded_file($file_tmp, $destination)) {
                    die("Upload ảnh thất bại!");
                }

                // Đường dẫn lưu DB
                $DM_ANH = "../assets/images/" . $file_name;
            }

        } else {
            die("Danh mục cấp 1 bắt buộc phải có ảnh!");
        }
    }

    // INSERT DB
    $stmt = $conn->prepare("INSERT INTO DANH_MUC (DM_TEN, DM_CHA, DM_ANH) VALUES (?, ?, ?)");
    $DM_CHA_DB = ($DM_CHA === '') ? NULL : $DM_CHA;
    $stmt->bind_param("sis", $DM_TEN, $DM_CHA_DB, $DM_ANH);

    if ($stmt->execute()) {
        header("Location: quanly_danhmuc.php?tab=danhmuc");
        exit;
    } else {
        die("Lỗi khi thêm danh mục: " . $stmt->error);
    }
}
?>
