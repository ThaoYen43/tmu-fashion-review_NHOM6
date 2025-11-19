<?php
require_once 'config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirectTo('index.php');
}

$database = new Database();
$db = $database->getConnection();

// Xử lý tạo/cập nhật brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand'])) {
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $priceMin = (int)$_POST['price_min'];
    $priceMax = (int)$_POST['price_max'];
    $shopeeLink = sanitizeInput($_POST['shopee_link']);
    $facebookLink = sanitizeInput($_POST['facebook_link']);
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    
    $errors = [];
    if (empty($name)) $errors[] = 'Tên thương hiệu không được để trống';
    if ($priceMin >= $priceMax) $errors[] = 'Giá tối thiểu phải nhỏ hơn giá tối đa';
    
    // Handle image uploads
    $logoName = null;
    $coverName = null;
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoName = uploadImage($_FILES['logo'], 'uploads/brands/');
    }
    
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $coverName = uploadImage($_FILES['cover_image'], 'uploads/brands/');
    }
    
    if (empty($errors)) {
        try {
            if ($brandId) {
                // Update existing brand
                $updateFields = "name = ?, description = ?, price_range_min = ?, price_range_max = ?, shopee_link = ?, facebook_link = ?, is_featured = ?";
                $params = [$name, $description, $priceMin, $priceMax, $shopeeLink, $facebookLink, $isFeatured];
                
                if ($logoName) {
                    $updateFields .= ", logo = ?";
                    $params[] = $logoName;
                }
                
                if ($coverName) {
                    $updateFields .= ", cover_image = ?";
                    $params[] = $coverName;
                }
                
                $params[] = $brandId;
                $stmt = $db->prepare("UPDATE brands SET $updateFields WHERE id = ?");
                $stmt->execute($params);
                
                $_SESSION['success'] = 'Cập nhật thương hiệu thành công!';
            } else {
                // Create new brand
                $stmt = $db->prepare("INSERT INTO brands (name, description, price_range_min, price_range_max, shopee_link, facebook_link, logo, cover_image, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $priceMin, $priceMax, $shopeeLink, $facebookLink, $logoName, $coverName, $isFeatured]);
                
                $_SESSION['success'] = 'Tạo thương hiệu thành công!';
            }
            redirectTo('admin.php');
        } catch (Exception $e) {
            $errors[] = 'Có lỗi xảy ra khi lưu thương hiệu!';
        }
    }
}

// Xử lý xóa brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_brand'])) {
    $brandId = (int)$_POST['brand_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->execute([$brandId]);
        $_SESSION['success'] = 'Xóa thương hiệu thành công!';
        redirectTo('admin.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'Có lỗi xảy ra khi xóa thương hiệu!';
    }
}

// Xử lý ẩn/hiện bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_comment'])) {
    $commentId = (int)$_POST['comment_id'];
    $isHidden = (int)$_POST['is_hidden'];
    
    try {
        $stmt = $db->prepare("UPDATE comments SET is_hidden = ? WHERE id = ?");
        $stmt->execute([$isHidden, $commentId]);
        $_SESSION['success'] = 'Cập nhật bình luận thành công!';
        redirectTo('admin.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'Có lỗi xảy ra khi cập nhật bình luận!';
    }
}

// Lấy danh sách brands
$stmt = $db->query("
    SELECT 
        b.*,
        AVG(c.rating) AS average_rating,
        COUNT(c.id) AS total_comments
    FROM brands b
    LEFT JOIN comments c ON c.brand_id = b.id
    GROUP BY b.id
    ORDER BY b.created_at DESC;
");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy brand cần edit
$editBrand = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$editId]);
    $editBrand = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Lấy thống kê
$stats = [];
$stats['total_brands'] = $db->query("SELECT COUNT(*) FROM brands")->fetchColumn();
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$stats['total_comments'] = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
// $stats['total_ratings'] = $db->query("SELECT COUNT(*) FROM ratings")->fetchColumn();

// Lấy bình luận cần quản lý
$stmt = $db->query("
    SELECT c.*, u.username, b.name as brand_name 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    JOIN brands b ON c.brand_id = b.id 
    WHERE c.parent_id IS NULL 
    ORDER BY c.created_at DESC 
    LIMIT 10
");
$recentComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- product management backend (thêm) ---
$editProduct = null;
if (isset($_GET['edit_product'])) {
    $pid = (int)$_GET['edit_product'];
    $pst = $db->prepare("SELECT * FROM products WHERE id = ?");
    $pst->execute([$pid]);
    $editProduct = $pst->fetch(PDO::FETCH_ASSOC);
}

// Xử lý tạo/cập nhật product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $p_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $p_name = sanitizeInput($_POST['p_name'] ?? '');
    $p_brand = isset($_POST['p_brand_id']) ? (int)$_POST['p_brand_id'] : 0;
    $p_price = isset($_POST['p_price']) ? floatval(str_replace(',', '', $_POST['p_price'])) : 0;
    $p_short = sanitizeInput($_POST['p_short_description'] ?? '');
    $p_desc = sanitizeInput($_POST['p_description'] ?? '');

    $pErrors = [];
    if (empty($p_name)) $pErrors[] = 'Tên sản phẩm không được để trống';
    if ($p_brand <= 0) $pErrors[] = 'Vui lòng chọn thương hiệu';

    $pImage = null;
    if (isset($_FILES['p_image']) && $_FILES['p_image']['error'] === UPLOAD_ERR_OK) {
        $pImage = uploadImage($_FILES['p_image'], 'uploads/products/');
        if ($pImage === false) $pErrors[] = 'Lỗi upload ảnh sản phẩm.';
    }

    if (empty($pErrors)) {
        try {
            if ($p_id) {
                // update
                $fields = "brand_id = ?, name = ?, short_description = ?, description = ?, price = ?";
                $params = [$p_brand, $p_name, $p_short, $p_desc, $p_price];
                if ($pImage) {
                    $fields .= ", image = ?";
                    $params[] = $pImage;
                }
                $params[] = $p_id;
                $u = $db->prepare("UPDATE products SET $fields WHERE id = ?");
                $u->execute($params);
                $_SESSION['success'] = 'Cập nhật sản phẩm thành công!';
            } else {
                // insert
                $ins = $db->prepare("INSERT INTO products (brand_id, name, image, short_description, description, price, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$p_brand, $p_name, $pImage, $p_short, $p_desc, $p_price]);
                $_SESSION['success'] = 'Tạo sản phẩm thành công!';
            }
            redirectTo('admin.php?tab=products');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Lỗi khi lưu sản phẩm.';
            error_log($e->getMessage());
        }
    } else {
        // gộp lỗi để hiển thị
        $errors = array_merge($errors ?? [], $pErrors);
    }
}

// Xử lý xóa product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $delId = (int)$_POST['product_id'];
    try {
        $s = $db->prepare("SELECT image FROM products WHERE id = ?");
        $s->execute([$delId]);
        $info = $s->fetch(PDO::FETCH_ASSOC);
        $d = $db->prepare("DELETE FROM products WHERE id = ?");
        $d->execute([$delId]);
        if (!empty($info['image']) && file_exists(__DIR__.'/uploads/products/'.$info['image'])) {
            @unlink(__DIR__.'/uploads/products/'.$info['image']);
        }
        $_SESSION['success'] = 'Xóa sản phẩm thành công!';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Lỗi khi xóa sản phẩm.';
    }
    redirectTo('admin.php?tab=products');
    exit;
}

// Lấy danh sách products để quản lý
try {
    $pstmt = $db->prepare("SELECT p.*, b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.created_at DESC");
    $pstmt->execute();
    $products = $pstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
    error_log($e->getMessage());
}
// --- end product backend ---

require_once __DIR__ . '/includes/product_sizes.php';

// load sizes hiện có khi edit product
$productSizes = [];
if (!empty($productId) && function_exists('get_product_sizes')) {
    $productSizes = get_product_sizes($db, (int)$productId);
}
$defaultLabels = ['S','M','L','XL'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị - Fashion Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .nav-pills .nav-link.active {
            background-color: #e91e63;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .brand-logo {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-cogs me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home me-2"></i>Trang chủ
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="container mt-3">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="container my-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-store fa-2x mb-2"></i>
                        <h3><?= $stats['total_brands'] ?></h3>
                        <p class="mb-0">Thương hiệu</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3><?= $stats['total_users'] ?></h3>
                        <p class="mb-0">Người dùng</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <h3><?= $stats['total_comments'] ?></h3>
                        <p class="mb-0">Bình luận</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-star fa-2x mb-2"></i>
                        <h3><?= $stats['total_comments'] ?></h3>
                        <p class="mb-0">Đánh giá</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Tabs -->
        <ul class="nav nav-pills mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#brands" type="button">
                    <i class="fas fa-store me-2"></i>Quản lý thương hiệu
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#products" type="button">
                    <i class="fas fa-box-open me-2"></i>Quản lý sản phẩm
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#comments" type="button">
                    <i class="fas fa-comments me-2"></i>Quản lý bình luận
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Brands Management Tab -->
            <div class="tab-pane fade show active" id="brands">
                <!-- Brand Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-plus-circle me-2"></i>
                            <?= $editBrand ? 'Chỉnh sửa thương hiệu' : 'Tạo thương hiệu mới' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="save_brand" value="1">
                            <?php if ($editBrand): ?>
                                <input type="hidden" name="brand_id" value="<?= $editBrand['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tên thương hiệu *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($editBrand['name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Giá từ (VNĐ) *</label>
                                    <input type="number" name="price_min" class="form-control" 
                                           value="<?= $editBrand['price_range_min'] ?? 0 ?>" required>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Giá đến (VNĐ) *</label>
                                    <input type="number" name="price_max" class="form-control" 
                                           value="<?= $editBrand['price_range_max'] ?? 0 ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Ý kiến nhận xét của admin</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editBrand['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Link Shopee</label>
                                    <input type="url" name="shopee_link" class="form-control" 
                                           value="<?= htmlspecialchars($editBrand['shopee_link'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Link Facebook</label>
                                    <input type="url" name="facebook_link" class="form-control" 
                                           value="<?= htmlspecialchars($editBrand['facebook_link'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Logo thương hiệu</label>
                                    <input type="file" name="logo" class="form-control" accept="image/*">
                                    <?php if ($editBrand && $editBrand['logo']): ?>
                                        <small class="text-muted">Logo hiện tại: <?= $editBrand['logo'] ?></small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ảnh bìa</label>
                                    <input type="file" name="cover_image" class="form-control" accept="image/*">
                                    <?php if ($editBrand && $editBrand['cover_image']): ?>
                                        <small class="text-muted">Ảnh bìa hiện tại: <?= $editBrand['cover_image'] ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_featured" class="form-check-input" 
                                           <?= ($editBrand && $editBrand['is_featured']) ? 'checked' : '' ?>>
                                    <label class="form-check-label">Thương hiệu nổi bật</label>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?= $editBrand ? 'Cập nhật' : 'Tạo mới' ?>
                                </button>
                                
                                <?php if ($editBrand): ?>
                                    <a href="admin.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Hủy
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Brands List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Danh sách thương hiệu</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Logo</th>
                                        <th>Tên</th>
                                        <th>Mức giá</th>
                                        <th>Đánh giá</th>
                                        <th>Nổi bật</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($brands as $brand): ?>
                                        <tr>
                                            <td>
                                                <img src="uploads/brands/<?= $brand['logo'] ?: 'default-brand.jpg' ?>" 
                                                     class="brand-logo rounded" alt="Logo">
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($brand['name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= substr($brand['description'], 0, 50) ?>...</small>
                                            </td>
                                            <td>
                                                <?= number_format($brand['price_range_min']) ?>đ<br>
                                                <small class="text-muted">đến <?= number_format($brand['price_range_max']) ?>đ</small>
                                            </td>
                                            <td>
                                                <div class="text-warning">
                                                    <?php
                                                    $rating = round($brand['average_rating']);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                    }
                                                    ?>
                                                </div>
                                                <small class="text-muted"><?= $brand['total_comments'] ?> đánh giá</small>
                                            </td>
                                            <td>
                                                <?php if ($brand['is_featured']): ?>
                                                    <span class="badge bg-success">Nổi bật</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Thường</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <a href="brand.php?id=<?= $brand['id'] ?>" class="btn btn-info btn-sm" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="admin.php?edit=<?= $brand['id'] ?>" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="brand_id" value="<?= $brand['id'] ?>">
                                                        <button type="submit" name="delete_brand" class="btn btn-danger btn-sm"
                                                                onclick="return confirm('Bạn có chắc muốn xóa thương hiệu này?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments Management Tab -->
            <div class="tab-pane fade" id="comments">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-comments me-2"></i>Bình luận gần đây</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentComments as $comment): ?>
                            <div class="card mb-3 <?= $comment['is_hidden'] ? 'border-danger' : '' ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6>
                                                <strong><?= htmlspecialchars($comment['username']) ?></strong> 
                                                bình luận về 
                                                <a href="brand.php?id=<?= $comment['brand_id'] ?>" target="_blank">
                                                    <?= htmlspecialchars($comment['brand_name']) ?>
                                                </a>
                                            </h6>
                                            <p class="mb-2"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                            
                                            <?php if ($comment['image']): ?>
                                                <img src="uploads/comments/<?= $comment['image'] ?>" 
                                                     class="img-fluid rounded mb-2" style="max-height: 150px;" alt="Comment image">
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                            </small>
                                            
                                            <?php if ($comment['is_hidden']): ?>
                                                <span class="badge bg-danger ms-2">Đã ẩn</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="ms-3">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                <input type="hidden" name="is_hidden" value="<?= $comment['is_hidden'] ? 0 : 1 ?>">
                                                <button type="submit" name="toggle_comment" 
                                                        class="btn btn-sm <?= $comment['is_hidden'] ? 'btn-success' : 'btn-warning' ?>">
                                                    <i class="fas <?= $comment['is_hidden'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                                    <?= $comment['is_hidden'] ? 'Hiện' : 'Ẩn' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentComments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Chưa có bình luận nào.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Products Management Tab (thêm) -->
            <div class="tab-pane fade" id="products">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle me-2"></i><?= $editProduct ? 'Chỉnh sửa sản phẩm' : 'Tạo sản phẩm mới' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="save_product" value="1">
                            <?php if ($editProduct): ?>
                                <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Thương hiệu *</label>
                                    <select name="p_brand_id" class="form-select" required>
                                        <option value="">-- Chọn thương hiệu --</option>
                                        <?php foreach ($brands as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= ($editProduct && $editProduct['brand_id']==$b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tên sản phẩm *</label>
                                    <input type="text" name="p_name" class="form-control" required value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mô tả ngắn</label>
                                <input type="text" name="p_short_description" class="form-control" value="<?= htmlspecialchars($editProduct['short_description'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mô tả chi tiết</label>
                                <textarea name="p_description" class="form-control" rows="4"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Giá</label>
                                    <input type="text" name="p_price" class="form-control" value="<?= htmlspecialchars($editProduct['price'] ?? '') ?>">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Ảnh sản phẩm</label>
                                    <input type="file" name="p_image" class="form-control" accept="image/*">
                                    <?php if ($editProduct && $editProduct['image']): ?>
                                        <small class="text-muted">Ảnh hiện tại: <?= htmlspecialchars($editProduct['image']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- CHỖ CHÈN: Quản lý size -->
                            <div class="card mt-3 p-3">
                              <h6>Quản lý size (S → XL)</h6>
                              <div id="sizesWrap">
                                <?php if (!empty($productSizes)): ?>
                                  <?php foreach ($productSizes as $i => $s): ?>
                                  <div class="size-row d-flex gap-2 mb-2 align-items-center">
                                    <input name="sizes[<?= $i?>][size_label]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['size_label']) ?>" readonly style="max-width:80px"/>
                                    <input name="sizes[<?= $i?>][bust_min]" class="form-control form-control-sm" placeholder="Ngực min" type="number" value="<?= (int)$s['bust_min'] ?>" style="width:110px"/>
                                    <input name="sizes[<?= $i?>][bust_max]" class="form-control form-control-sm" placeholder="Ngực max" type="number" value="<?= (int)$s['bust_max'] ?>" style="width:110px"/>
                                    <input name="sizes[<?= $i?>][waist_min]" class="form-control form-control-sm" placeholder="Eo min" type="number" value="<?= (int)$s['waist_min'] ?>" style="width:110px"/>
                                    <input name="sizes[<?= $i?>][waist_max]" class="form-control form-control-sm" placeholder="Eo max" type="number" value="<?= (int)$s['waist_max'] ?>" style="width:110px"/>
                                    <input name="sizes[<?= $i?>][hip_min]" class="form-control form-control-sm" placeholder="Mông min" type="number" value="<?= (int)$s['hip_min'] ?>" style="width:110px"/>
                                    <input name="sizes[<?= $i?>][hip_max]" class="form-control form-control-sm" placeholder="Mông max" type="number" value="<?= (int)$s['hip_max'] ?>" style="width:110px"/>
                                    <button type="button" class="btn btn-sm btn-danger remove-size">X</button>
                                  </div>
                                  <?php endforeach; ?>
                                <?php else: ?>
                                  <?php foreach ($defaultLabels as $idx => $lab): ?>
                                  <div class="size-row d-flex gap-2 mb-2 align-items-center">
                                    <input name="sizes[<?= $idx?>][size_label]" class="form-control form-control-sm" value="<?= $lab ?>" readonly style="max-width:80px"/>
                                    <input name="sizes[<?= $idx?>][bust_min]" class="form-control form-control-sm" placeholder="Ngực min" type="number" value="" style="width:110px"/>
                                    <input name="sizes[<?= $idx?>][bust_max]" class="form-control form-control-sm" placeholder="Ngực max" type="number" value="" style="width:110px"/>
                                    <input name="sizes[<?= $idx?>][waist_min]" class="form-control form-control-sm" placeholder="Eo min" type="number" value="" style="width:110px"/>
                                    <input name="sizes[<?= $idx?>][waist_max]" class="form-control form-control-sm" placeholder="Eo max" type="number" value="" style="width:110px"/>
                                    <input name="sizes[<?= $idx?>][hip_min]" class="form-control form-control-sm" placeholder="Mông min" type="number" value="" style="width:110px"/>
                                    <input name="sizes[<?= $idx?>][hip_max]" class="form-control form-control-sm" placeholder="Mông max" type="number" value="" style="width:110px"/>
                                    <button type="button" class="btn btn-sm btn-danger remove-size">X</button>
                                  </div>
                                  <?php endforeach; ?>
                                <?php endif; ?>
                              </div>
                              <div class="mt-2">
                                <button type="button" id="addSizeBtn" class="btn btn-sm btn-outline-primary">Thêm size</button>
                              </div>
                            </div>
                            <!-- KẾT THÚC: Quản lý size -->

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?= $editProduct ? 'Cập nhật' : 'Tạo mới' ?>
                                </button>
                                <?php if ($editProduct): ?>
                                    <a href="admin.php?tab=products" class="btn btn-secondary">Hủy</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Danh sách sản phẩm</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="text-muted">Chưa có sản phẩm nào.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ảnh</th>
                                            <th>Tên</th>
                                            <th>Thương hiệu</th>
                                            <th>Giá</th>
                                            <th>Ngày tạo</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $p): ?>
                                            <tr>
                                                <td><img src="uploads/products/<?= $p['image'] ?: 'default-product.jpg' ?>" style="width:64px;height:64px;object-fit:cover;border-radius:6px" alt=""></td>
                                                <td><?= htmlspecialchars($p['name']) ?></td>
                                                <td><?= htmlspecialchars($p['brand_name']) ?></td>
                                                <td class="fw-bold text-primary"><?= number_format($p['price'] ?? 0) ?>đ</td>
                                                <td><?= htmlspecialchars($p['created_at'] ?? '') ?></td>
                                                <td>
                                                    <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Xem</a>
                                                    <a href="admin.php?edit_product=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xác nhận xóa sản phẩm này?')">
                                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="delete_product" class="btn btn-sm btn-outline-danger">Xóa</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- End Products Management Tab -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
      let idx = document.querySelectorAll('#sizesWrap .size-row').length;
      document.getElementById('addSizeBtn').addEventListener('click', ()=>{
        const wrap = document.getElementById('sizesWrap');
        const template = wrap.querySelector('.size-row');
        const row = template.cloneNode(true);
        row.querySelectorAll('input').forEach(inp=>{
          const name = inp.getAttribute('name');
          const newName = name.replace(/\[\d+\]/, '['+idx+']');
          inp.setAttribute('name', newName);
          if (!inp.hasAttribute('readonly')) inp.value = '';
        });
        wrap.appendChild(row);
        idx++;
      });
      document.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('remove-size')) {
          const rows = document.querySelectorAll('#sizesWrap .size-row');
          if (rows.length > 1) e.target.closest('.size-row').remove();
        }
      });
    })();
    </script>
</body>
</html>