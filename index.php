<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Xử lý tìm kiếm
$searchMessage = '';
$successMessage = '';

// Lấy danh sách thương hiệu theo lượt đánh giá + bình luận nhiều nhất
// $stmt = $db->query("
//     SELECT b.id, b.name,
//            (SELECT COUNT(*) FROM ratings r WHERE r.brand_id = b.id) AS total_ratings,
//            (SELECT COUNT(*) FROM comments c WHERE c.brand_id = b.id) AS total_comments,
//            (
//                (SELECT COUNT(*) FROM ratings r WHERE r.brand_id = b.id) +
//                (SELECT COUNT(*) FROM comments c WHERE c.brand_id = b.id)
//            ) AS total_interactions
//     FROM brands b
//     ORDER BY total_interactions DESC
// ");
$stmt = $db->query("
    SELECT 
        b.id,
        b.name,
        AVG(c.rating) AS average_rating,
        COUNT(c.id) AS total_comments
    FROM brands b
    LEFT JOIN comments c ON c.brand_id = b.id
    GROUP BY b.id, b.name
    ORDER BY average_rating DESC;

");
$interactiveBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    if (isset($_GET['found']) && $_GET['found'] == '1') {
        // Tìm tên thương hiệu từ danh sách
        foreach ($interactiveBrands as $brand) {
            if ($brand['id'] == $_GET['brand_id']) {
                $_SESSION['success_message'] = 'Tìm thấy thương hiệu: "' . $brand['name'] . '".';
                break;
            }
        }
    } else {
        $_SESSION['success_message'] = 'Đã chuyển đến thương hiệu bạn chọn.';
    }

    redirectTo('brand.php?id=' . intval($_GET['brand_id']));
    exit;
}

// Nếu tìm kiếm theo từ khóa
if (!empty($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
    $stmt = $db->prepare("SELECT * FROM brands WHERE name LIKE ? LIMIT 1");
    $stmt->execute(['%' . $searchTerm . '%']);
    $searchResult = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($searchResult) {
        $_SESSION['success_message'] = 'Tìm thấy thương hiệu: "' . $searchResult['name'] . '".';
        redirectTo('brand.php?id=' . $searchResult['id']);
        exit;
    } else {
        $searchMessage = 'Không có thông tin bạn cần.';
    }
}



// Lấy brands theo filter - HIỂN THỊ TẤT CẢ BRANDS
$filterCondition = '
SELECT 
        b.*,
        AVG(c.rating) AS average_rating,
        COUNT(c.id) AS total_comments
    FROM brands b
    LEFT JOIN comments c ON c.brand_id = b.id
'; 
$filterParams = [];
$currentFilter = $_GET['filter'] ?? 'all';
switch ($currentFilter) { //kiểm tra giá trị $currentFilter và gán điều kiện SQL tương ứng.
    case 'popular':
        $filterCondition = $filterCondition . ' GROUP BY b.id ORDER BY average_rating DESC;';
        break;
    case 'cheap':
        $filterCondition = $filterCondition . ' WHERE b.price_range_max < 500000 GROUP BY b.id ORDER BY b.price_range_min ASC;';
        break;
    case 'featured':
        $filterCondition = $filterCondition . ' WHERE b.is_featured = 1 GROUP BY b.id ORDER BY average_rating DESC;';
        break;
    case 'all':
    default:
        // QUAN TRỌNG: Hiển thị tất cả brands theo thứ tự mới nhất
        $filterCondition = $filterCondition . ' GROUP BY b.id ORDER BY b.created_at DESC;';
        $currentFilter = 'all';
}


$stmt = $db->prepare($filterCondition);
$stmt->execute($filterParams);
$featuredBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy thông báo success nếu có
$successMessage = '';
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!--hiển thị tốt trên điện thoại-->
    <title>Diễn đàn review Thương hiệu thời trang nữ tại Việt Nam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> <!--chèn icon như ngôi sao, tìm kiếm, biểu tượng "trống", v.v.

-->
    <style>
        .navbar-brand {
            font-weight: bold;
            color: #e91e63 !important;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        .brand-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); /*đổ bóng*/
            border-radius: 15px; /*Bo tròn các góc*/
            overflow: hidden; /*ẩn ảnh tránh tràn khỏi viền*/
        }
        .brand-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .brand-card .card-img-top {
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .brand-card:hover .card-img-top {
            transform: scale(1.05);
        }
        .rating-stars {
            color: #ffc107;
        }
        .price-range {
            color: #e91e63;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .btn-view-review {
            background: linear-gradient(135deg, #e91e63, #f06292);
            border: none;
            color: white;
            border-radius: 25px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .btn-view-review:hover {
            background: linear-gradient(135deg, #c2185b, #e91e63);
            color: white;
            transform: scale(1.05);
        }
        .filter-tabs {
            background: white;
            border-radius: 50px;
            padding: 5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 40px;
            display: inline-flex;
        }
        .filter-tab {
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s ease;
            border: none;
            background: none;
            margin: 0 5px;
        }
        .filter-tab.active {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }
        .filter-tab:hover {
            color: #e91e63;
            background: #f8f9fa;
        }
        .filter-tab.active:hover {
            color: white;
        }
        .brand-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(233, 30, 99, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .brand-meta {
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
        }
        .new-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        footer {
            background: linear-gradient(135deg, #343a40, #495057);
            color: white;
            margin-top: 80px;
        }
        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: none;
            border-radius: 15px;
            animation: slideDown 0.5s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 4rem;
            color: #e91e63;
            margin-bottom: 20px;
        }
    </style>
</head>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container-fluid">
    <!-- Logo -->
    <a class="navbar-brand" href="index.php">
      <i class="fas fa-heart me-2"></i>Fashion Review
    </a>

    <!-- Toggle button (mobile) -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Nội dung navbar -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="d-flex w-100 justify-content-between align-items-center">
        <!-- Giữa: Tìm kiếm -->
        <form class="d-flex align-items-center gap-2 position-relative mx-auto" method="GET" autocomplete="off">
          <input 
            class="form-control form-control-sm" 
            type="text" 
            name="search" 
            id="brandInput" 
            placeholder="Tìm kiếm thương hiệu..." 
            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
            style="width: 250px;"
          >
          <button class="btn btn-outline-secondary btn-sm" type="submit">
            <i class="fas fa-search"></i>
          </button>

          <!-- Dropdown gợi ý -->
          <div id="brandDropdown" 
               class="position-absolute bg-white border rounded w-100 shadow" 
               style="top: 100%; left: 0; max-height: 200px; overflow-y: auto; display: none; z-index: 1050;">
            <?php foreach ($interactiveBrands as $brand): ?>
              <div class="dropdown-item px-3 py-2 border-bottom text-dark"
                   onmousedown="selectBrand('<?= htmlspecialchars($brand['name']) ?>', <?= (int)$brand['id'] ?>)"
                   style="cursor: pointer;">
                <?= htmlspecialchars($brand['name']) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </form>

        <!-- Phải: Tài khoản -->
        <div class="d-flex align-items-center gap-2">
          <?php if (isLoggedIn()): ?>
            <div class="dropdown">
              <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['username']) ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end"> 
                <!-- ✅ thêm dropdown-menu-end -->
                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Tài khoản cá nhân</a></li>
                <?php if (isAdmin()): ?>
                  <li><a class="dropdown-item" href="admin.php"><i class="fas fa-cog me-2"></i>Quản trị</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
              </ul>
            </div>
          <?php else: ?>
            <a href="login.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-sign-in-alt me-1"></i>Đăng nhập</a>
            <a href="register.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus me-1"></i>Đăng ký</a>
            <span class="text-muted ms-2">| 
              <a href="?anonymous=1" class="text-decoration-none">
                <i class="fas fa-eye me-1"></i>Xem ẩn danh
              </a>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</nav>
<!--hiển thị thông báo-->
    <?php if (!empty($searchMessage)): ?>
  <div class="container mt-2">
    <div class="alert alert-warning alert-dismissible fade show px-3 py-2 mb-0 d-flex justify-content-between align-items-center" 
         role="alert" style="font-size: 0.85rem;">
      
      <!-- Biểu tượng + nội dung -->
      <span class="d-flex align-items-center">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= htmlspecialchars($searchMessage) ?>
      </span>

      <!-- Nút đóng -->
      <button type="button" class="btn-close btn-sm ms-2" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  </div>
<?php endif; ?>




    <!-- Filter Section -->
    <section class="container my-5">
        <div class="text-center mb-4">
            <h2 class="mb-4">
                <?php
                $titles = [
                    'all' => 'Thương hiệu thời trang',
                    'popular' => 'Brand được yêu thích nhất',
                    'cheap' => 'Brand giá rẻ (dưới 500.000đ)',
                    'featured' => 'Brand nổi bật'
                ];
                echo $titles[$currentFilter];
                ?>
                <span class="badge bg-primary ms-2"><?= count($featuredBrands) ?></span>
            </h2>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?= $currentFilter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-clock me-2"></i>Tất cả
                </a>
                <a href="?filter=featured" class="filter-tab <?= $currentFilter === 'featured' ? 'active' : '' ?>">
                    <i class="fas fa-star me-2"></i>Nổi bật
                </a>
                <a href="?filter=popular" class="filter-tab <?= $currentFilter === 'popular' ? 'active' : '' ?>">
                    <i class="fas fa-heart me-2"></i>Phổ biến
                </a>
                <a href="?filter=cheap" class="filter-tab <?= $currentFilter === 'cheap' ? 'active' : '' ?>">
                    <i class="fas fa-tag me-2"></i>Giá rẻ
                </a>
            </div>
        </div>
        
        <div class="row">
            <?php if (!empty($featuredBrands)): ?>
                <?php foreach ($featuredBrands as $index => $brand): ?>
                    <?php 
                    $isNew = (time() - strtotime($brand['created_at'])) < (7 * 24 * 60 * 60); // 7 days
                    $imageExists = $brand['cover_image'] && file_exists("uploads/brands/" . $brand['cover_image']);
                    ?>
                    <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                        <div class="card brand-card h-100">
                            <div class="position-relative">
                                <?php if ($imageExists): ?>
                                    <img src="uploads/brands/<?= htmlspecialchars($brand['cover_image']) ?>" 
                                         class="card-img-top" alt="<?= htmlspecialchars($brand['name']) ?>">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-store fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Badges -->
                                <?php if ($isNew): ?>
                                    <span class="brand-badge new-badge">
                                        <i class="fas fa-sparkles me-1"></i>MỚI
                                    </span>
                                <?php elseif ($brand['is_featured']): ?>
                                    <span class="brand-badge">
                                        <i class="fas fa-star me-1"></i>NỔI BẬT
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">
                                    <?= htmlspecialchars($brand['name']) ?>
                                    <?php if ($isNew): ?>
                                        <i class="fas fa-certificate text-success ms-1" title="Thương hiệu mới"></i>
                                    <?php endif; ?>
                                </h5>
                                
                                <div class="rating-stars mb-2">
                                    <?php
                                    $rating = round($brand['average_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                    <span class="text-muted ms-2">(<?= $brand['total_comments'] ?> đánh giá)</span>
                                </div>
                                
                                <p class="price-range mb-3">
                                    <i class="fas fa-tags me-2"></i>
                                    <?= number_format($brand['price_range_min']) ?>đ - <?= number_format($brand['price_range_max']) ?>đ
                                </p>
                                
                                <p class="card-text text-muted flex-grow-1">
                                    <?= htmlspecialchars(substr($brand['description'] ?: 'Thương hiệu thời trang nữ chất lượng cao', 0, 100)) ?>...
                                </p>
                                
                                <div class="brand-meta mb-3">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Thêm ngày: <?= date('d/m/Y', strtotime($brand['created_at'])) ?>
                                    <?php if ($brand['shopee_link'] || $brand['facebook_link']): ?>
                                        <br>
                                        <i class="fas fa-external-link-alt me-1"></i>
                                        <?php if ($brand['shopee_link']): ?>
                                            <a href="<?= htmlspecialchars($brand['shopee_link']) ?>" target="_blank" class="text-warning me-2">
                                                <i class="fas fa-shopping-cart"></i> Shopee
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($brand['facebook_link']): ?>
                                            <a href="<?= htmlspecialchars($brand['facebook_link']) ?>" target="_blank" class="text-primary">
                                                <i class="fab fa-facebook"></i> Facebook
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="hover-actions mt-auto">
                                    <a href="brand.php?id=<?= $brand['id'] ?>" class="btn btn-view-review w-100">
                                        <i class="fas fa-eye me-2"></i>Xem review
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-store-slash"></i>
                        <h3>Chưa có thương hiệu nào</h3>
                        <p class="text-muted">
                            <?php if ($currentFilter === 'featured'): ?>
                                Chưa có thương hiệu nào được đánh dấu nổi bật.
                            <?php elseif ($currentFilter === 'cheap'): ?>
                                Chưa có thương hiệu nào có giá dưới 500.000đ.
                            <?php else: ?>
                                Hệ thống đang được cập nhật. Hãy quay lại sau!
                            <?php endif; ?>
                        </p>
                        
                        <?php if (isAdmin()): ?>
                            <a href="admin.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Thêm thương hiệu
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
    <!-- Stats Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center">
                <?php
                // Lấy thống kê
                $stats = [];
                try {
                    $stats['brands'] = $db->query("SELECT COUNT(*) FROM brands")->fetchColumn();
                    $stats['reviews'] = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
                    $stats['ratings'] = $db->query("SELECT COUNT(*) FROM ratings")->fetchColumn();
                    $stats['users'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
                } catch (Exception $e) {
                    $stats = ['brands' => 0, 'reviews' => 0, 'ratings' => 0, 'users' => 0];
                }
                ?>
                <div class="col-md-3 mb-3">
                    <div class="h2 text-primary"><?= number_format($stats['brands']) ?></div>
                    <p class="text-muted">Thương hiệu</p>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="h2 text-success"><?= number_format($stats['reviews']) ?></div>
                    <p class="text-muted">Bình luận</p>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="h2 text-warning"><?= number_format($stats['ratings']) ?></div>
                    <p class="text-muted">Đánh giá</p>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="h2 text-info"><?= number_format($stats['users']) ?></div>
                    <p class="text-muted">Thành viên</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-heart me-2"></i>Giới thiệu</h5>
                    <p>Diễn đàn review các thương hiệu thời trang nữ tại Việt Nam. Nơi chia sẻ trải nghiệm và đánh giá chân thực từ cộng đồng.</p>
                </div>
                <div class="col-md-4">
                    <h5><i class="fas fa-shield-alt me-2"></i>Chính sách</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">
                            <i class="fas fa-file-alt me-1"></i>Chính sách sử dụng
                        </a></li>
                        <li><a href="#" class="text-light text-decoration-none">
                            <i class="fas fa-users me-1"></i>Quy định cộng đồng
                        </a></li>
                        <li><a href="#" class="text-light text-decoration-none">
                            <i class="fas fa-lock me-1"></i>Bảo mật thông tin
                        </a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5><i class="fas fa-phone me-2"></i>Liên hệ</h5>
                    <p>
                        <i class="fas fa-envelope me-2"></i>Adminfashionreview@gmail.com<br>
                        <i class="fas fa-phone me-2"></i>0123 456 789
                    </p>
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook-f fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-tiktok fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">
                    <i class="fas fa-copyright me-1"></i>
                    <?= date('Y') ?> Fashion Review Vietnam. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto hide success alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success-custom');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
   <script>
const input = document.getElementById('brandInput');
const dropdown = document.getElementById('brandDropdown');

input.addEventListener('focus', () => {
    dropdown.style.display = 'block';
});

input.addEventListener('input', () => {
    const keyword = input.value.toLowerCase();
    const items = dropdown.querySelectorAll('.dropdown-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(keyword) ? '' : 'none';
    });
});

input.addEventListener('blur', () => {
    setTimeout(() => {
        dropdown.style.display = 'none';
    }, 200);
});

// Hàm chuyển hướng khi chọn brand
function selectBrand(name, id) {
    const input = document.getElementById('brandInput');
    input.value = name;
    dropdown.style.display = 'none';
    window.location.href = 'brand.php?id=' + id;
}
</script>

</body>
</html>