<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");

// Lấy thông tin từ GET
$vnp_TxnRef = $_GET['vnp_TxnRef'] ?? '';
$parts = explode('_', $vnp_TxnRef);
$dh_ma = $parts[0]; // Lấy lại mã đơn hàng gốc
$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';
$vnp_Amount = $_GET['vnp_Amount'] ?? 0;
$vnp_BankTranNo = $_GET['vnp_BankTranNo'] ?? '';
$vnp_QRCode = $_GET['vnp_QRCode'] ?? '';
$vnp_PayDate = $_GET['vnp_PayDate'] ?? ''; 


if ($vnp_ResponseCode == '00') {

    session_start();
    unset($_SESSION['donhang_vnpay']); // Xoá session chống tạo trùng

    // ===== CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG =====
    $conn->query("UPDATE DON_HANG SET DH_TRANGTHAI = 'Đã thanh toán' WHERE DH_MA = '$dh_ma'");

    // ===== LƯU LỊCH SỬ TRẠNG THÁI =====
    $trangthai = 'Đã thanh toán';
    $sql_tt = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
    $stmt_tt = $conn->prepare($sql_tt);
    $stmt_tt->bind_param("s", $trangthai);
    $stmt_tt->execute();
    $result_tt = $stmt_tt->get_result();
    $tt_ma = ($result_tt && $result_tt->num_rows > 0) ? $result_tt->fetch_assoc()['TT_MA'] : null;

    if ($tt_ma !== null) {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $ngay_cap_nhat = date('Y-m-d H:i:s');
        $stmt_ls = $conn->prepare("INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) VALUES (?, ?, ?)");
        $stmt_ls->bind_param("iis", $dh_ma, $tt_ma, $ngay_cap_nhat);
        $stmt_ls->execute();
    }

    // ===== LƯU THÔNG TIN THANH TOÁN =====
    $result_dh = $conn->query("SELECT DH_TONGTHANHTOAN FROM DON_HANG WHERE DH_MA = '$dh_ma'");
    $dh = $result_dh->fetch_assoc();
    $tongtien = $dh['DH_TONGTHANHTOAN'];
    $httt_ma = 2; // VNPay
    $trangthai_tt = 'Đã thanh toán';
    if (!empty($vnp_PayDate)) {
        $ngay_thanhtoan = date("Y-m-d H:i:s", strtotime($vnp_PayDate));
    } else {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $ngay_thanhtoan = date('Y-m-d H:i:s'); // fallback nếu VNPay không gửi về
    }

    $check_tt = $conn->query("SELECT * FROM THANH_TOAN_DON WHERE DH_MA = '$dh_ma' AND TTD_TRANGTHAI = 'Đã thanh toán'");
    if ($check_tt->num_rows == 0) {
        $stmt_tt_don = $conn->prepare("
            INSERT INTO THANH_TOAN_DON 
            (TTD_SOTIEN, TTD_NGAYTHANHTOAN, TTD_TRANGTHAI, TTD_MAGIAODICH, TTD_QRCODE, DH_MA, HTTT_MA, TTD_VNP_TXNREF) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_tt_don->bind_param("dsssiiss", 
            $tongtien,
            $ngay_thanhtoan,
            $trangthai_tt,
            $vnp_BankTranNo,
            $vnp_QRCode,
            $dh_ma,
            $httt_ma,
            $vnp_TxnRef
        );
        $stmt_tt_don->execute();
    }

    // ================================
    // XÉT DUYỆT TỰ ĐỘNG SAU KHI THANH TOÁN VNPAY (bỏ sleep)
    // ================================
    $check = $conn->query("SELECT DH_TRANGTHAI FROM DON_HANG WHERE DH_MA = '$dh_ma'");
    $current = $check->fetch_assoc();

    if ($current['DH_TRANGTHAI'] != 'Đang chuẩn bị hàng') {
        // Cập nhật sang trạng thái "Đang chuẩn bị hàng" ngay lập tức
        $trang_thai_moi = 'Đang chuẩn bị hàng';
        $sql_update_tt = "UPDATE DON_HANG SET DH_TRANGTHAI = ? WHERE DH_MA = ?";
        $stmt_update = $conn->prepare($sql_update_tt);
        $stmt_update->bind_param("si", $trang_thai_moi, $dh_ma);
        $stmt_update->execute();

        // Ghi lại vào lịch sử đơn hàng
        $sql_tt_new = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
        $stmt_tt_new = $conn->prepare($sql_tt_new);
        $stmt_tt_new->bind_param("s", $trang_thai_moi);
        $stmt_tt_new->execute();
        $result_tt_new = $stmt_tt_new->get_result();
        $tt_ma_moi = ($result_tt_new && $result_tt_new->num_rows > 0) ? $result_tt_new->fetch_assoc()['TT_MA'] : null;

        if ($tt_ma_moi !== null) {
            $thoigian = date('Y-m-d H:i:s');
            $stmt_ls_new = $conn->prepare("INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM) VALUES (?, ?, ?)");
            $stmt_ls_new->bind_param("iis", $dh_ma, $tt_ma_moi, $thoigian);
            $stmt_ls_new->execute();
        }
    }

    // ================================
    // THÔNG BÁO CHO KHÁCH HÀNG
    // ================================
    echo "
        <h2 style='color:green; text-align:center; margin-top:50px;'>
            Thanh toán thành công! MODÉ xin cảm ơn.<br>
            Đơn hàng của bạn đang được chuẩn bị.
        </h2>
        <script>
            setTimeout(function() {
                window.location = '../KhachHang/lichsu_donhang.php';
            }, 3000);
        </script>
    ";

} else {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Thanh toán thất bại hoặc bị hủy.</h2>
    <script>
        setTimeout(function() {
            window.location = '../KhachHang/lichsu_donhang.php';
        }, 3000);
    </script>";
}
?>
