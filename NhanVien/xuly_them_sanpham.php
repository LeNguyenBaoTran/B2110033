<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);

// --- Lấy dữ liệu POST ---
$DM_MA        = $_POST['DM_MA'] ?? '';
$SP_TEN       = trim($_POST['SP_TEN'] ?? '');
$SP_CHATLIEU  = $_POST['SP_CHATLIEU'] ?? '';
$SP_MOTA      = $_POST['SP_MOTA'] ?? '';
$SP_CONSUDUNG = $_POST['SP_CONSUDUNG'] ?? 1;
$GIA_BAN      = $_POST['GIA_BAN'] ?? 0;
$kichthuoc    = $_POST['kichthuoc'] ?? [];

// ---------- KIỂM TRA TÊN SẢN PHẨM ----------
$stmt = $conn->prepare("SELECT SP_MA FROM SAN_PHAM WHERE SP_TEN = ?");
$stmt->bind_param("s", $SP_TEN);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
    die("❌ Sản phẩm đã tồn tại với tên này.");
}
$stmt->close();

// ---------- THÊM SẢN PHẨM ----------
$ngaythem = date("Y-m-d H:i:s");
$stmt = $conn->prepare("
    INSERT INTO SAN_PHAM (DM_MA, SP_TEN, SP_CHATLIEU, SP_MOTA, SP_CONSUDUNG, SP_NGAYTHEM) 
    VALUES (?,?,?,?,?,?)
");
$stmt->bind_param("isssis", $DM_MA, $SP_TEN, $SP_CHATLIEU, $SP_MOTA, $SP_CONSUDUNG, $ngaythem);
if(!$stmt->execute()) die("Lỗi thêm sản phẩm: " . $stmt->error);
$SP_MA = $stmt->insert_id;
$stmt->close();

// ---------- LẤY THỜI ĐIỂM ----------
$result = $conn->query("SELECT MAX(TD_THOIDIEM) AS td FROM THOI_DIEM");
$row = $result->fetch_assoc();
$TD_THOIDIEM = $row['td'] ?? date("Y-m-d H:i:s");

// ---------- THÊM GIÁ ----------
$stmt = $conn->prepare("INSERT INTO DON_GIA_BAN (SP_MA, DONGIA, TD_THOIDIEM) VALUES (?,?,?)");
$stmt->bind_param("ids", $SP_MA, $GIA_BAN, $TD_THOIDIEM);
$stmt->execute();
$stmt->close();

// ---------- LƯU ẢNH (KHÔNG COPY FILE) ----------
$targetDir = "../assets/images/";
$realDir   = $_SERVER['DOCUMENT_ROOT'] . "/LV_QuanLy_BanTrangPhuc/LV_QuanLy_BanTrangPhuc/assets/images/";

if(!empty($_FILES['ANH']['name'][0])){
    foreach($_FILES['ANH']['name'] as $key => $fileName){

        $fileName = basename($fileName);

        // kiểm tra file có tồn tại sẵn trong ổ C chưa
        $checkFile = $realDir . $fileName;

        if(file_exists($checkFile)){
            // lưu đường dẫn vào DB
            $duongdan_csd = $targetDir . $fileName;

            $stmt = $conn->prepare("INSERT INTO ANH_SAN_PHAM (SP_MA, ANH_DUONGDAN) VALUES (?, ?)");
            $stmt->bind_param("is", $SP_MA, $duongdan_csd);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ---------- THÊM KÍCH THƯỚC ----------
foreach($kichthuoc as $KT_MA => $info){
    if(isset($info['chon']) && $info['chon'] == 1){
        $soluong = intval($info['soluong']);
        if($soluong > 0){
            $stmt = $conn->prepare("INSERT INTO CHI_TIET_SAN_PHAM (SP_MA, KT_MA, CTSP_SOLUONGTON) VALUES (?,?,?)");
            $stmt->bind_param("iii", $SP_MA, $KT_MA, $soluong);
            $stmt->execute();
            $stmt->close();
        }
    }
}

echo "<script>alert('✅ Thêm sản phẩm thành công!'); window.location='quanly_sanpham.php?tab=sanpham';</script>";
?>
