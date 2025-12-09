<?php
$conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
mysqli_set_charset($conn,"utf8");

// Kiểm tra kiểu tìm kiếm
$is_search_text  = isset($_REQUEST['q']) && trim($_REQUEST['q']) != "";
$is_search_image = isset($_FILES['image']) && $_FILES['image']['size'] > 0;

$q = "";
$user_image_web = "";
$results_with_info = [];
$result_text = null;

/* ===========================
   TÌM KIẾM BẰNG ẢNH
=========================== */
if($is_search_image){

    $user_image_web = '/LV_QuanLy_BanTrangPhuc/LV_QuanLy_BanTrangPhuc/flask_api/uploads/' . basename($_FILES['image']['name']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:5000/search");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile(
            $_FILES["image"]["tmp_name"],
            $_FILES["image"]["type"],
            $_FILES["image"]["name"]
        )
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $results = json_decode($response, true);

    if (!empty($results['results'])) {
        $seen_products = [];

        foreach ($results['results'] as $item) {
            if (!isset($item['product_id'])) continue;
            $sp_ma = $item['product_id'];

            $query = $conn->prepare("
                SELECT s.SP_TEN, a.ANH_DUONGDAN, d.DONGIA
                FROM san_pham s
                JOIN anh_san_pham a ON s.SP_MA = a.SP_MA
                LEFT JOIN don_gia_ban d 
                    ON s.SP_MA = d.SP_MA
                    AND d.TD_THOIDIEM = (
                        SELECT MAX(TD_THOIDIEM) FROM don_gia_ban WHERE SP_MA = s.SP_MA
                    )
                WHERE s.SP_MA = ?
                LIMIT 1
            ");
            $query->bind_param("s", $sp_ma);
            $query->execute();
            $res = $query->get_result()->fetch_assoc();

            if ($res && !in_array($res['SP_TEN'], $seen_products)) {
                $web_path = str_replace(
                    ['C:\\xampp\\htdocs\\LV_QuanLy_BanTrangPhuc\\LV_QuanLy_BanTrangPhuc', '\\'], 
                    ['', '/'], 
                    $res['ANH_DUONGDAN']
                );

                $results_with_info[] = [
                    'sp_ma' => $sp_ma,
                    'ten' => $res['SP_TEN'],
                    'gia' => $res['DONGIA'] ?? 0,
                    'anh' => $web_path
                ];
                $seen_products[] = $res['SP_TEN'];
            }
        }
        $results_with_info = array_slice($results_with_info, 0, 5);
    }
}

/* ===========================
   TÌM KIẾM BẰNG TỪ KHÓA
=========================== */
if($is_search_text){

    $q = trim($_REQUEST['q']);

    $sql = $conn->prepare("WITH gia_cte AS (
        SELECT 
            sp.SP_MA,
            sp.SP_TEN,

            (
                SELECT g.DONGIA
                FROM don_gia_ban g
                JOIN thoi_diem td ON g.TD_THOIDIEM = td.TD_THOIDIEM
                WHERE g.SP_MA = sp.SP_MA
                ORDER BY td.TD_THOIDIEM DESC
                LIMIT 1
            ) AS GIA_GOC,

            (
                SELECT ctkm.CTKM_PHANTRAM_GIAM
                FROM chi_tiet_khuyen_mai ctkm
                JOIN khuyen_mai k ON ctkm.KM_MA = k.KM_MA
                WHERE ctkm.SP_MA = sp.SP_MA
                  AND k.KM_CONSUDUNG = 1
                  AND CURDATE() BETWEEN k.KM_NGAYBATDAU AND k.KM_NGAYKETTHUC
                ORDER BY k.KM_NGAYBATDAU DESC
                LIMIT 1
            ) AS PHAN_TRAM_GIAM
        FROM san_pham sp
        WHERE sp.SP_CONSUDUNG = 1
          AND sp.SP_TEN COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', ?, '%')
    ),

    gia_final AS (
        SELECT 
            g.*,
            ROUND(g.GIA_GOC * (100 - COALESCE(g.PHAN_TRAM_GIAM, 0)) / 100, 0) AS GIA_HIEN_THI
        FROM gia_cte g
    ),

    anh_cte AS (
        SELECT 
            a.SP_MA,
            MAX(CASE WHEN rn = 1 THEN a.ANH_DUONGDAN END) AS Anh1,
            MAX(CASE WHEN rn = 2 THEN a.ANH_DUONGDAN END) AS Anh2
        FROM (
            SELECT 
                ANH_DUONGDAN,
                SP_MA,
                ROW_NUMBER() OVER (PARTITION BY SP_MA ORDER BY ANH_MA ASC) AS rn
            FROM anh_san_pham
        ) a
        GROUP BY a.SP_MA
    )

    SELECT 
        g.SP_MA,
        g.SP_TEN,
        g.GIA_GOC,
        g.PHAN_TRAM_GIAM,
        g.GIA_HIEN_THI,
        a.Anh1,
        a.Anh2
    FROM gia_final g
    LEFT JOIN anh_cte a ON g.SP_MA = a.SP_MA
    ORDER BY g.SP_MA DESC;
    ");

    $sql->bind_param("s",$q);
    $sql->execute();
    $result_text = $sql->get_result();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Tìm kiếm sản phẩm</title>
<link href="../assets/css/home.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Ảnh người dùng */
.user-img {
    max-height: 300px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 3px solid #007bff;
}

/* Card sản phẩm */
.card {
    width: 218px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
}

.card img {
    height: 300px;
    object-fit: cover;
    transition: transform 0.3s;
}

.card img:hover {
    transform: scale(1.05);
}

.card-body {
    padding: 12px;
    text-align: center;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 6px;
}

.card-text {
    font-size: 15px;
    margin-bottom: 10px;
}

.btn-primary {
    background-color: #4595eaff !important;
    border: none !important;
    border-radius: 6px !important;
    font-size: 14px !important;
    padding: 6px 0 !important;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background-color: #0a417bff !important;
}

.flex-products {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: flex-start;
} 

.btn-secondary {
    background-color: #40219dff;
}

.btn-secondary:hover {
    background-color: #220a6bff;
}
</style>

</head>
<body>

<div class="container mt-4">

<?php if($is_search_image): ?>

    <h4>Kết quả tìm kiếm bằng ảnh</h4>

    <?php if($user_image_web): ?>
        <img src="<?= htmlspecialchars($user_image_web) ?>" class="user-img">
    <?php endif; ?>

    <?php if(!empty($results_with_info)): ?>
    <div class="flex-products mt-3">
        <?php foreach($results_with_info as $item): ?>
        <div class="card">
            <img src="<?= htmlspecialchars($item['anh']) ?>" class="card-img-top">
            <div class="card-body text-center">
                <h6 class="card-title text-truncate"><?= htmlspecialchars($item['ten']) ?></h6>
                <p class="text-danger fw-bold"><?= number_format($item['gia'],0,',','.') ?>₫</p>
                <a href="chitietsp.php?sp=<?= urlencode($item['sp_ma']) ?>" class="btn btn-primary btn-sm w-100">Xem sản phẩm</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p class="text-muted mt-3">Không tìm thấy kết quả</p>
    <?php endif; ?>

<?php elseif($is_search_text): ?>

    <h4 class="text-center mb-4">
        Kết quả tìm kiếm cho: <b><?= htmlspecialchars($q) ?></b>
    </h4>

    <?php if($result_text && $result_text->num_rows > 0): ?>
        <div class="featured-products">
            <?php while($row = $result_text->fetch_assoc()): 
                $img1 = $row['Anh1'] ?: "assets/images/logo.png";
                $img2 = $row['Anh2'] ?: $img1;
                $gia_goc = $row['GIA_GOC'];
                $gia_ht = $row['GIA_HIEN_THI'];
                $giam = $row['PHAN_TRAM_GIAM'];
            ?>
            <div class="product-card">
                <div class="product-img">
                    <a href="chitietsp.php?sp=<?= $row['SP_MA'] ?>">
                        <img src="<?= $img1 ?>" class="img-main">
                        <img src="<?= $img2 ?>" class="img-hover">
                    </a>
                    <?php if($giam > 0): ?>
                        <span class="sale-badge">-<?= $giam ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h4><?= htmlspecialchars($row['SP_TEN']) ?></h4>

                    <?php if($giam > 0): ?>
                        <span class="old-price"><?= number_format($gia_goc,0,',','.') ?> đ</span>
                        <span class="new-price"><?= number_format($gia_ht,0,',','.') ?> đ</span>
                    <?php else: ?>
                        <span class="new-price"><?= number_format($gia_goc,0,',','.') ?> đ</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-muted">Không tìm thấy sản phẩm phù hợp</p>
    <?php endif; ?>

<?php else: ?>

    <p class="text-center text-muted">Vui lòng nhập từ khóa hoặc chọn ảnh để tìm kiếm.</p>

<?php endif; ?>

<div class="text-center mt-4">
    <a href="../Mode/trangchu.php" class="btn btn-secondary">Quay lại trang chủ</a>
</div>

</div>
</body>
</html>
