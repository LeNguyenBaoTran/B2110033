<!-- Mega menu danh mục -->
<div id="menu-danhmuc" class="menu-danhmuc py-5 bg-light">
  <div class="container">
    <h4 class="fw-bold mb-4 text-center text-uppercase" style="letter-spacing:1px;">
      Bộ sưu tập
    </h4>

    <?php
    // Kết nối CSDL
    $conn = new mysqli("localhost", "root", "", "ql_ban_trang_phuc");
    mysqli_set_charset($conn, "utf8");
    if ($conn->connect_error) {
      die("Kết nối thất bại: " . $conn->connect_error);
    }

    // Lấy danh mục con của Thời Trang Nam / Nữ
    $sql = "SELECT * FROM DANH_MUC WHERE DM_CHA IN (1, 2)";
    $result = $conn->query($sql);

    echo '<div class="row justify-content-center" id="menu-category">';

    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $dm_id = $row['DM_MA'];
        $dm_ten = $row['DM_TEN'];
        $anh = !empty($row['DM_ANH']) ? $row['DM_ANH'] : '../assets/images/logo.PNG';

        // Kiểm tra xem có danh mục con không
        $sql_sub_check = "SELECT COUNT(*) AS so_con FROM DANH_MUC WHERE DM_CHA = $dm_id";
        $check = $conn->query($sql_sub_check)->fetch_assoc();
        $co_con = $check['so_con'] > 0;

        echo "
        <div class='col-6 col-md-3 col-lg-2 mb-4 text-center'>
          <div class='category-card position-relative'>
            <a href=\"sanpham.php?dm=$dm_id\" class='text-dark text-decoration-none d-block'>
              <div class='img-wrapper'>
                <img src='$anh' class='img-fluid rounded' alt='$dm_ten'>
              </div>
              <h6 class='fw-semibold mt-2'>$dm_ten</h6>
            </a>";
        
        if ($co_con) {
          echo "
            <button class='btn btn-sm toggle-btn' onclick='toggleSubmenu($dm_id, event)' title='Xem thêm'>
              <i class='bi bi-chevron-down'></i>
            </button>
          ";
        }

        echo "<div id='submenu-$dm_id' class='submenu collapse mt-2'>";
        if ($co_con) {
          $sql_sub = "SELECT * FROM DANH_MUC WHERE DM_CHA = $dm_id";
          $result_sub = $conn->query($sql_sub);
          while ($sub = $result_sub->fetch_assoc()) {
            echo "<p class='mb-1'><a href='sanpham.php?dm={$sub['DM_MA']}' class='text-dark text-decoration-none submenu-link'>{$sub['DM_TEN']}</a></p>";
          }
        }
        echo "</div></div></div>";
      }
    }

    echo '</div>';
    ?>
  </div>
</div>

<!-- Script xổ -->
<script>
function toggleSubmenu(id, e) {
  e.stopPropagation();
  const el = document.getElementById('submenu-' + id);
  el.classList.toggle('show');
  const icon = e.currentTarget.querySelector('i');
  icon.classList.toggle('bi-chevron-down');
  icon.classList.toggle('bi-chevron-up');
}
</script>

<!-- CSS -->
<style>
  #menu-category {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    max-height: 550px;      
    overflow-y: auto;       
    scrollbar-width: thin;  
    scrollbar-color: #eef2f7; 
  }

  /* Chrome, Edge, Safari */
  #menu-category::-webkit-scrollbar {
    width: 8px;
  }

  #menu-category::-webkit-scrollbar-track {
    background: #e3f2fd; /* nền trắng nhạt */
  }

  #menu-category::-webkit-scrollbar-thumb {
    background-color: rgb(213, 231, 247); 
    border-radius: 4px;
  }


  /* Thẻ danh mục */
  .category-card {
    border: none;
    background: transparent;
    transition: transform 0.3s ease;
  }

  .category-card:hover {
    transform: translateY(-5px);
  }

  /* Ảnh chính */
  .img-wrapper {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
  }

  .category-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 18px;
    transition: transform 0.35s ease, box-shadow 0.3s ease;
  }

  .category-card:hover img {
    transform: scale(1.07);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
  }

  /* Tên danh mục */
  .category-card h6 {
    font-size: 15px;
    color: #222;
    margin: 8px 0 0;
  }

  /* Nút xổ xuống */
  .toggle-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255,255,255,0.85);
    border: none;
    border-radius: 50%;
    padding: 4px;
    font-size: 14px;
    transition: all 0.2s;
  }

  .toggle-btn:hover {
    background: rgba(255,255,255,1);
  }

  /* Submenu */
  .submenu {
    border-top: 1px dashed #eee;
    margin-top: 10px;
    padding-top: 5px;
  }

  .submenu-link {
    font-size: 14px;
    display: block;
    padding: 3px 0;
  }

  .submenu-link:hover {
    color: #007bff;
    text-decoration: underline;
  }
</style>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
