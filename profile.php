<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirectTo('login.php');
}

$database = new Database();
$db = $database->getConnection();
$userId = getCurrentUserId();

// Xử lý cập nhật thông tin cá nhân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    
    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    // Check email exists for other users
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'Email đã được sử dụng bởi tài khoản khác';
    }
    
    // Handle avatar upload
    $avatarName = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $avatarName = uploadImage($_FILES['avatar'], 'uploads/avatars/');
        if (!$avatarName) {
            $errors[] = 'Lỗi khi tải lên ảnh đại diện';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($avatarName) {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, avatar = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $avatarName, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $userId]);
            }
            
            $_SESSION['success'] = 'Cập nhật thông tin thành công!';
            redirectTo('profile.php');
        } catch (Exception $e) {
            $errors[] = 'Có lỗi xảy ra khi cập nhật!';
        }
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $passwordErrors = [];
    
    // Verify current password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($currentPassword, $user['password'])) {
        $passwordErrors[] = 'Mật khẩu hiện tại không đúng';
    }
    
    if (strlen($newPassword) < 6) {
        $passwordErrors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    }
    
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'Mật khẩu xác nhận không khớp';
    }
    
    if (empty($passwordErrors)) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            $_SESSION['success'] = 'Đổi mật khẩu thành công!';
            redirectTo('profile.php');
        } catch (Exception $e) {
            $passwordErrors[] = 'Có lỗi xảy ra khi đổi mật khẩu!';
        }
    }
}

// Xử lý xóa bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $commentId = (int)$_POST['comment_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
        $stmt->execute([$commentId, $userId]);
        $_SESSION['success'] = 'Xóa bình luận thành công!';
        redirectTo('profile.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'Có lỗi xảy ra khi xóa bình luận!';
    }
}

// Lấy thông tin người dùng
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Lấy các brand đã đánh giá
$stmt = $db->prepare("
    SELECT b.*, r.rating, r.created_at as rating_date 
    FROM ratings r 
    JOIN brands b ON r.brand_id = b.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$userId]);
$ratedBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy bình luận của người dùng
$stmt = $db->prepare("
    SELECT c.*, b.name as brand_name 
    FROM comments c 
    JOIN brands b ON c.brand_id = b.id 
    WHERE c.user_id = ? AND c.parent_id IS NULL
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$userComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản cá nhân - Fashion Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-avatar {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }
        .rating-stars {
            color: #ffc107;
        }
        .nav-pills .nav-link.active {
            background-color: #e91e63;
        }
        .brand-card {
            transition: transform 0.2s;
        }
        .brand-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heart me-2"></i>Fashion Review
            </a>
            <div class="navbar-nav ms-auto">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Trang chủ
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

    <?php if (!empty($passwordErrors)): ?>
        <div class="container mt-3">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($passwordErrors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="container my-5">
        <div class="row">
            <!-- Profile Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="uploads/avatars/<?= $user['avatar'] ?: 'default-avatar.jpg' ?>" 
                             class="profile-avatar rounded-circle mb-3" alt="Avatar">
                        <h5><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h5>
                        <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
                        <p class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            Tham gia: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </p>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title">Thống kê</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Đã đánh giá:</span>
                            <span class="badge bg-primary"><?= count($ratedBrands) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Bình luận:</span>
                            <span class="badge bg-success"><?= count($userComments) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <!-- Tabs Navigation -->
                <ul class="nav nav-pills mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#ratings" type="button">
                            <i class="fas fa-star me-2"></i>Đánh giá của tôi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#comments" type="button">
                            <i class="fas fa-comments me-2"></i>Bình luận của tôi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#profile-settings" type="button">
                            <i class="fas fa-cog me-2"></i>Cài đặt tài khoản
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Ratings Tab -->
                    <div class="tab-pane fade show active" id="ratings">
                        <h4 class="mb-4">Các thương hiệu đã đánh giá</h4>
                        
                        <?php if (!empty($ratedBrands)): ?>
                            <div class="row">
                                <?php foreach ($ratedBrands as $brand): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card brand-card">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <img src="uploads/brands/<?= $brand['cover_image'] ?: 'default-brand.jpg' ?>" 
                                                         class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;" 
                                                         alt="<?= htmlspecialchars($brand['name']) ?>">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <a href="brand.php?id=<?= $brand['id'] ?>" class="text-decoration-none">
                                                                <?= htmlspecialchars($brand['name']) ?>
                                                            </a>
                                                        </h6>
                                                        <div class="rating-stars">
                                                            <?php
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                echo $i <= $brand['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                            }
                                                            ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            Đánh giá: <?= date('d/m/Y', strtotime($brand['rating_date'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Bạn chưa đánh giá thương hiệu nào.</p>
                                <a href="index.php" class="btn btn-primary">Khám phá thương hiệu</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Comments Tab -->
                    <div class="tab-pane fade" id="comments">
                        <h4 class="mb-4">Bình luận của tôi</h4>
                        
                        <?php if (!empty($userComments)): ?>
                            <?php foreach ($userComments as $comment): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6>
                                                <a href="brand.php?id=<?= $comment['brand_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($comment['brand_name']) ?>
                                                </a>
                                            </h6>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary me-2" 
                                                        data-bs-toggle="modal" data-bs-target="#editComment<?= $comment['id'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                    <button type="submit" name="delete_comment" class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Bạn có chắc muốn xóa bình luận này?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <p class="mb-2"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                        
                                        <?php if ($comment['image']): ?>
                                            <img src="uploads/comments/<?= $comment['image'] ?>" 
                                                 class="img-fluid rounded mb-2" style="max-height: 200px;" alt="Comment image">
                                        <?php endif; ?>
                                        
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Edit Comment Modal -->
                                <div class="modal fade" id="editComment<?= $comment['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Chỉnh sửa bình luận</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="edit_comment" value="1">
                                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nội dung bình luận</label>
                                                        <textarea name="content" class="form-control" rows="4" required><?= htmlspecialchars($comment['content']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Bạn chưa có bình luận nào.</p>
                                <a href="index.php" class="btn btn-primary">Bắt đầu bình luận</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Profile Settings Tab -->
                    <div class="tab-pane fade" id="profile-settings">
                        <h4 class="mb-4">Cài đặt tài khoản</h4>
                        
                        <div class="row">
                            <!-- Update Profile -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-user me-2"></i>Cập nhật thông tin</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="update_profile" value="1">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Họ và tên</label>
                                                <input type="text" name="full_name" class="form-control" 
                                                       value="<?= htmlspecialchars($user['full_name']) ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" 
                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Ảnh đại diện mới</label>
                                                <input type="file" name="avatar" class="form-control" accept="image/*">
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Cập nhật
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-lock me-2"></i>Đổi mật khẩu</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="change_password" value="1">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Mật khẩu hiện tại</label>
                                                <input type="password" name="current_password" class="form-control" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Mật khẩu mới</label>
                                                <input type="password" name="new_password" class="form-control" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Xác nhận mật khẩu mới</label>
                                                <input type="password" name="confirm_password" class="form-control" required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-key me-2"></i>Đổi mật khẩu
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>