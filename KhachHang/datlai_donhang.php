<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['nd_ma'])) {
    echo "<script>alert('Vui lòng đăng nhập để tiếp tục!'); window.location='../Mode/dangnhap.php';</script>";
    exit;
}

$nd_ma = $_SESSION['nd_ma'];
$dh_ma = $_POST['dh_ma'] ?? '';

if ($dh_ma != '') {

    // --- Kiểm tra xem đơn hàng này có thanh toán online (HTTT_MA = 2) không ---
    $sql_check = "
        SELECT TTD.HTTT_MA 
        FROM THANH_TOAN_DON TTD
        INNER JOIN DON_HANG DH ON DH.DH_MA = TTD.DH_MA
        WHERE DH.DH_MA = ? AND DH.ND_MA = ? AND TTD.HTTT_MA = 2
    ";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $dh_ma, $nd_ma);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check && $result_check->num_rows > 0) {
        echo "<script>alert('Đơn hàng thanh toán online (VNPay) không thể đặt lại!'); history.back();</script>";
        exit;
    }

    // --- Nếu không có trong bảng THANH_TOAN_DON => HTTT = 1, cho đặt lại ---
    $new_status = "Chờ xác nhận";

    // Lấy chi tiết đơn hàng để kiểm tra tồn kho trước khi trừ
    $sql_ct = "SELECT SP_MA, KT_MA, CTDH_SOLUONG FROM CHI_TIET_DON_HANG WHERE DH_MA = ?";
    $stmt_ct = $conn->prepare($sql_ct);
    $stmt_ct->bind_param("i", $dh_ma);
    $stmt_ct->execute();
    $result_ct = $stmt_ct->get_result();

    $khong_du = false;
    $ds_thieu = [];

    while ($row = $result_ct->fetch_assoc()) {
        $sp_ma = $row['SP_MA'];
        $kt_ma = $row['KT_MA'];
        $soluong = $row['CTDH_SOLUONG'];

        // Lấy số lượng tồn hiện tại
        $sql_ton = "SELECT CTSP_SOLUONGTON FROM CHI_TIET_SAN_PHAM WHERE SP_MA = ? AND KT_MA = ?";
        $stmt_ton = $conn->prepare($sql_ton);
        $stmt_ton->bind_param("ii", $sp_ma, $kt_ma);
        $stmt_ton->execute();
        $result_ton = $stmt_ton->get_result();

        if ($result_ton && $result_ton->num_rows > 0) {
            $ton = $result_ton->fetch_assoc()['CTSP_SOLUONGTON'];

            if ($ton < $soluong) {
                $khong_du = true;
                $ds_thieu[] = "Sản phẩm mã $sp_ma (KT: $kt_ma) chỉ còn $ton, cần $soluong";
            }
        }
    }

    // Nếu có sản phẩm không đủ tồn kho => không cho đặt lại
    if ($khong_du) {
        $thongbao = implode("\\n", $ds_thieu);
        echo "<script>alert('Không thể đặt lại vì một số sản phẩm không đủ hàng:\\n$thongbao'); history.back();</script>";
        exit;
    }

    // --- Nếu đủ hàng, cập nhật trạng thái và trừ tồn ---
    $sql_update = "UPDATE DON_HANG 
                   SET DH_TRANGTHAI = ?, DH_NGAYDAT = NOW()
                   WHERE DH_MA = ? AND ND_MA = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sii", $new_status, $dh_ma, $nd_ma);

    if ($stmt_update->execute()) {

        // ✅ --- Trừ tồn kho ---
        $stmt_ct->execute();
        $result_ct = $stmt_ct->get_result();
        while ($row = $result_ct->fetch_assoc()) {
            $sp_ma = $row['SP_MA'];
            $kt_ma = $row['KT_MA'];
            $soluong = $row['CTDH_SOLUONG'];

            $sql_update_stock = "UPDATE CHI_TIET_SAN_PHAM 
                                 SET CTSP_SOLUONGTON = CTSP_SOLUONGTON - ? 
                                 WHERE SP_MA = ? AND KT_MA = ?";
            $stmt_stock = $conn->prepare($sql_update_stock);
            $stmt_stock->bind_param("iii", $soluong, $sp_ma, $kt_ma);
            $stmt_stock->execute();
        }

        // --- Lưu lịch sử đơn hàng ---
        $sql_tt = "SELECT TT_MA FROM TRANG_THAI WHERE TT_TEN = ?";
        $stmt_tt = $conn->prepare($sql_tt);
        $stmt_tt->bind_param("s", $new_status);
        $stmt_tt->execute();
        $result_tt = $stmt_tt->get_result();

        if ($result_tt && $result_tt->num_rows > 0) {
            $tt_ma = $result_tt->fetch_assoc()['TT_MA'];
            $thoidiem = date('Y-m-d H:i:s');

            $check_sql = "SELECT * FROM LICH_SU_DON_HANG WHERE DH_MA = ? AND TT_MA = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("ii", $dh_ma, $tt_ma);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check && $result_check->num_rows > 0) {
                $update_sql = "UPDATE LICH_SU_DON_HANG 
                               SET LSDH_THOIDIEM = ? 
                               WHERE DH_MA = ? AND TT_MA = ?";
                $stmt_update = $conn->prepare($update_sql);
                $stmt_update->bind_param("sii", $thoidiem, $dh_ma, $tt_ma);
                $stmt_update->execute();
            } else {
                $insert_sql = "INSERT INTO LICH_SU_DON_HANG (DH_MA, TT_MA, LSDH_THOIDIEM)
                               VALUES (?, ?, ?)";
                $stmt_insert = $conn->prepare($insert_sql);
                $stmt_insert->bind_param("iis", $dh_ma, $tt_ma, $thoidiem);
                $stmt_insert->execute();
            }
        }

        echo "<script>
            alert('Đơn hàng đã được đặt lại thành công và đang chờ xác nhận!');
            window.location='lichsu_donhang.php';
        </script>";
    } else {
        echo "<script>alert('Có lỗi xảy ra khi đặt lại đơn hàng!'); history.back();</script>";
    }
} else {
    echo "<script>alert('Không tìm thấy mã đơn hàng!'); history.back();</script>";
}
?>
