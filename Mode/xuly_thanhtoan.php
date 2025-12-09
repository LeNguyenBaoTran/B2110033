<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// ===== KIỂM TRA ĐĂNG NHẬP =====
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để thanh toán!'); window.location='dangnhap.php';</script>";
    exit;
}

$nd_ma = $_SESSION['nd_ma'];

// ===== CHỐNG TẠO TRÙNG ĐƠN HÀNG VNPAY =====
if (isset($_SESSION['donhang_vnpay'])) {
    $dh_ma = $_SESSION['donhang_vnpay'];
    echo "<script>
        alert('Đơn hàng VNPay của bạn đã được tạo. Đang chuyển hướng đến cổng thanh toán...');
        window.location='thanhtoan_vnpay.php?dh_ma=$dh_ma';
    </script>";
    exit;
}


// ===== LẤY DỮ LIỆU TỪ FORM =====
$hoten = $_POST['hoten'] ?? '';
$email = $_POST['email'] ?? '';
$sdt = $_POST['sdt'] ?? '';
$diachi = $_POST['diachi'] ?? '';
$tinh = $_POST['tinh'] ?? '';
$quan = $_POST['quan'] ?? '';
$phuong = $_POST['phuong'] ?? '';

$dvvc_ma = $_POST['dvvc_ma'] ?? null;
$thanhtoan = $_POST['thanhtoan'] ?? null;

// Các giá trị hidden input
$phiGiao = (float)($_POST['phi_giao'] ?? 0);
$tongCong_post = (float)($_POST['tong_cong'] ?? 0);
$voucher_discount = (float)($_POST['voucher_giam'] ?? 0);
$tongTienHang = (float)($_POST['tam_tinh'] ?? 0);
$voucher_ma = $_POST['voucher_ma'] ?? null; // Lấy mã voucher

$dia_chi_nhan = $_POST['dia_chi_nhan'] ?? ''; // địa chỉ ghép từ JS

// ===== KIỂM TRA DỮ LIỆU =====
if (
    empty($hoten) || empty($sdt) ||
    empty($tinh) || empty($quan) || empty($phuong) ||
    empty($dvvc_ma) || empty($thanhtoan)
) {
    echo "<script>alert('Vui lòng nhập đầy đủ thông tin giao hàng và thanh toán!'); history.back();</script>";
    exit;
}

// Nếu chưa có địa chỉ chi tiết
if (empty($dia_chi_nhan)) {
    $dia_chi_nhan = trim("$diachi, $phuong, $quan, $tinh, Việt Nam");
}

// ===== LẤY GIỎ HÀNG =====
$sql_giohang = "SELECT GH_MA FROM GIO_HANG WHERE ND_MA = '$nd_ma'";
$result_gh = $conn->query($sql_giohang);
if (!$result_gh || $result_gh->num_rows == 0) {
    echo "<script>alert('Không tìm thấy giỏ hàng hợp lệ của bạn!'); window.location='cart.php';</script>";
    exit;
}
$gh_ma = $result_gh->fetch_assoc()['GH_MA'];

// ===== TÍNH LẠI TỔNG TIỀN HÀNG =====
if ($tongTienHang <= 0) {
    $tongTienHang = 0;
    $result_ctgh = $conn->query("SELECT CTGH_SOLUONG, CTGH_DONGIA FROM CHI_TIET_GIO_HANG WHERE GH_MA = '$gh_ma'");
    while ($row = $result_ctgh->fetch_assoc()) {
        $tongTienHang += $row['CTGH_SOLUONG'] * $row['CTGH_DONGIA'];
    }
}

// ===== TÍNH LẠI TỔNG TRÊN SERVER =====
$tongThanhToan = $tongTienHang - $voucher_discount + $phiGiao;

// So sánh với giá từ client
$epsilon = 1000; // sai số cho phép (1000đ)
if (abs($tongCong_post - $tongThanhToan) > $epsilon) {
    echo "<script>alert('Phát hiện sai lệch tổng thanh toán. Vui lòng tải lại trang và thử lại!'); history.back();</script>";
    exit;
}

// ===== LƯU ĐƠN HÀNG =====
// Kiểm tra xem hình thức thanh toán có phải vnpay không
$sql_httt = "SELECT HTTT_TEN FROM hinh_thuc_thanh_toan WHERE HTTT_MA = '$thanhtoan'";
$result_httt = $conn->query($sql_httt);
$ten_httt = ($result_httt && $result_httt->num_rows > 0) ? $result_httt -> fetch_assoc()['HTTT_TEN'] : '';


$ngaydat = date('Y-m-d');
$trangthai = (stripos($ten_httt, 'vnpay') !== false) ? 'Chờ thanh toán' : 'Chờ xác nhận';


if (!empty($voucher_ma)) {
    // Nếu có dùng voucher
    $stmt = $conn->prepare("
        INSERT INTO DON_HANG 
        (DH_NGAYDAT, DH_TRANGTHAI, DH_TONGTIENHANG, DH_GIAMGIA, DH_TONGTHANHTOAN, DH_DIACHINHAN, ND_MA, GH_MA, DVVC_MA, VC_MA)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssdddsiiii",
        $ngaydat,
        $trangthai,
        $tongTienHang,
        $voucher_discount,
        $tongThanhToan,
        $dia_chi_nhan,
        $nd_ma,
        $gh_ma,
        $dvvc_ma,
        $voucher_ma
    );
} else {
    // Không có voucher
    $stmt = $conn->prepare("
        INSERT INTO DON_HANG 
        (DH_NGAYDAT, DH_TRANGTHAI, DH_TONGTIENHANG, DH_GIAMGIA, DH_TONGTHANHTOAN, DH_DIACHINHAN, ND_MA, GH_MA, DVVC_MA)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssdddsiii",
        $ngaydat,
        $trangthai,
        $tongTienHang,
        $voucher_discount,
        $tongThanhToan,
        $dia_chi_nhan,
        $nd_ma,
        $gh_ma,
        $dvvc_ma
    );
}

if ($stmt->execute()) {
    $dh_ma = $stmt->insert_id;

        // Nếu là VNPay thì lưu session để tránh tạo lại đơn hàng khi reload
        if (stripos($ten_httt, 'vnpay') !== false) {
            $_SESSION['donhang_vnpay'] = $dh_ma;
        }
            
        // LƯU LỊCH SỬ TRẠNG THÁI 
        // Lấy TT_MA từ TRANG_THAI dựa vào tên trạng thái $trangthai
        $sql_tt = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
        $stmt_tt = $conn->prepare($sql_tt);
        $stmt_tt->bind_param("s", $trangthai);
        $stmt_tt->execute();
        $result_tt = $stmt_tt->get_result();
        $tt_ma = ($result_tt && $result_tt->num_rows > 0) ? $result_tt->fetch_assoc()['TT_MA'] : null;
    
        if ($tt_ma !== null) {
            date_default_timezone_set('Asia/Ho_Chi_Minh'); // đặt timezone Việt Nam
            $ngay_cap_nhat = date('Y-m-d H:i:s');
            $stmt_ls = $conn->prepare("INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) VALUES (?, ?, ?)");
            $stmt_ls->bind_param("iis", $dh_ma, $tt_ma, $ngay_cap_nhat);
            $stmt_ls->execute();
        }

    // CHUYỂN DỮ LIỆU GIỎ HÀNG SANG CHI TIẾT ĐƠN HÀNG
    $sql_ctgh = "SELECT SP_MA, KT_MA, CTGH_SOLUONG, CTGH_DONGIA 
                 FROM CHI_TIET_GIO_HANG WHERE GH_MA = '$gh_ma'";
    $result_ctgh = $conn->query($sql_ctgh);

    if ($result_ctgh && $result_ctgh->num_rows > 0) {
        while ($row_ctgh = $result_ctgh->fetch_assoc()) {
        $sp_ma = $row_ctgh['SP_MA'];
        $kt_ma = $row_ctgh['KT_MA'];
        $so_luong = (int)$row_ctgh['CTGH_SOLUONG'];
        $don_gia = (float)$row_ctgh['CTGH_DONGIA'];

        // Lưu vào bảng chi tiết đơn hàng
        $conn->query("INSERT INTO CHI_TIET_DON_HANG (DH_MA, SP_MA, KT_MA, CTDH_SOLUONG, CTDH_DONGIA)
                    VALUES ('$dh_ma', '$sp_ma', '$kt_ma', '$so_luong', '$don_gia')");

        // Giảm tồn kho
        $conn->query("UPDATE CHI_TIET_SAN_PHAM 
                    SET CTSP_SOLUONGTON = GREATEST(CTSP_SOLUONGTON - $so_luong, 0)
                    WHERE SP_MA = '$sp_ma' AND KT_MA = '$kt_ma'");
        }

        // Xóa sản phẩm trong giỏ hàng sau khi đặt
        $conn->query("DELETE FROM CHI_TIET_GIO_HANG WHERE GH_MA = '$gh_ma'");
    }


    // XÉT DUYỆT TỰ ĐỘNG 
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Đặt múi giờ VN

if (stripos($ten_httt, 'vnpay') === false) {
    // TRƯỜNG HỢP THANH TOÁN KHI NHẬN HÀNG (COD)
    if (!empty($hoten) && !empty($sdt) && !empty($diachi) && !empty($tinh) && !empty($quan) && !empty($phuong)) {

        // ===== KIỂM TRA TỒN KHO TRƯỚC KHI DUYỆT =====
        $sql_check_tonkho = "
            SELECT ctdh.SP_MA, ctdh.KT_MA, ctdh.CTDH_SOLUONG, ctsp.CTSP_SOLUONGTON 
            FROM CHI_TIET_DON_HANG ctdh
            JOIN CHI_TIET_SAN_PHAM ctsp 
                ON ctdh.SP_MA = ctsp.SP_MA AND ctdh.KT_MA = ctsp.KT_MA
            WHERE ctdh.DH_MA = $dh_ma
        ";
        $result_check = $conn->query($sql_check_tonkho);
        $du_hang = true;

        if ($result_check && $result_check->num_rows > 0) {
            while ($row = $result_check->fetch_assoc()) {
                if ($row['CTSP_SOLUONGTON'] < $row['CTDH_SOLUONG']) {
                    $du_hang = false;
                    break;
                }
            }
        } else {
            $du_hang = false; // nếu không truy vấn được thì không duyệt
        }

        // ===== XÉT DUYỆT TỰ ĐỘNG =====
        if ($du_hang) {

            // Cập nhật trạng thái sang "Đang chuẩn bị hàng"
            $trang_thai_moi = 'Đang chuẩn bị hàng';
            $sql_update_tt = "UPDATE DON_HANG SET DH_TRANGTHAI = ? WHERE DH_MA = ?";
            $stmt_update = $conn->prepare($sql_update_tt);
            $stmt_update->bind_param("si", $trang_thai_moi, $dh_ma);
            $stmt_update->execute();

            // Lưu vào lịch sử đơn hàng
            $sql_tt_new = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
            $stmt_tt_new = $conn->prepare($sql_tt_new);
            $stmt_tt_new->bind_param("s", $trang_thai_moi);
            $stmt_tt_new->execute();
            $result_tt_new = $stmt_tt_new->get_result();
            $tt_ma_moi = ($result_tt_new && $result_tt_new->num_rows > 0)
                ? $result_tt_new->fetch_assoc()['TT_MA'] : null;

            if ($tt_ma_moi !== null) {
                $thoigian = date('Y-m-d H:i:s');
                $stmt_ls_new = $conn->prepare("
                    INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) 
                    VALUES (?, ?, ?)
                ");
                $stmt_ls_new->bind_param("iis", $dh_ma, $tt_ma_moi, $thoigian);
                $stmt_ls_new->execute();
            }
        } else {
            // Không đủ hàng → giữ ở trạng thái "Chờ xác nhận"
            $trang_thai_moi = 'Chờ xác nhận';
            $sql_update_tt = "UPDATE DON_HANG SET DH_TRANGTHAI = ? WHERE DH_MA = ?";
            $stmt_update = $conn->prepare($sql_update_tt);
            $stmt_update->bind_param("si", $trang_thai_moi, $dh_ma);
            $stmt_update->execute();

            // Lưu lịch sử trạng thái không đủ hàng
            $sql_tt_new = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
            $stmt_tt_new = $conn->prepare($sql_tt_new);
            $stmt_tt_new->bind_param("s", $trang_thai_moi);
            $stmt_tt_new->execute();
            $result_tt_new = $stmt_tt_new->get_result();
            $tt_ma_moi = ($result_tt_new && $result_tt_new->num_rows > 0)
                ? $result_tt_new->fetch_assoc()['TT_MA'] : null;

            if ($tt_ma_moi !== null) {
                $thoigian = date('Y-m-d H:i:s');
                $stmt_ls_new = $conn->prepare("
                    INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) 
                    VALUES (?, ?, ?)
                ");
                $stmt_ls_new->bind_param("iis", $dh_ma, $tt_ma_moi, $thoigian);
                $stmt_ls_new->execute();
            }
        }
    }
} else {
    // --- TRƯỜNG HỢP THANH TOÁN QUA VNPAY ---
    // Không duyệt ở đây vì còn phải chờ callback VNPAY xác nhận thành công
}

    
    if(stripos($ten_httt, 'vnpay') !== false){
        echo "<script>
        alert('Đơn hàng của bạn đã được tạo. Vui lòng thanh toán qua VNPAY!');
        window.location='thanhtoan_vnpay.php?dh_ma=$dh_ma';
        </script>";
    } else {
        //Các hình thức khác
        echo "<script>
            alert('Đặt hàng thành công! MODÉ xin cảm ơn');
            window.location.href='../KhachHang/lichsu_donhang.php';
        </script>";
    }
}

?>

