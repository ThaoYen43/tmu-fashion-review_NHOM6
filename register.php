<?php
if (basename($_SERVER['PHP_SELF']) == 'register.php') {
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $fullName = sanitizeInput($_POST['full_name']);
        
        $errors = [];
        
        // Validation
        if (empty($username)) $errors[] = 'Tên đăng nhập không được để trống';
        if (empty($email)) $errors[] = 'Email không được để trống';
        if (empty($password)) $errors[] = 'Mật khẩu không được để trống';
        if ($password !== $confirmPassword) $errors[] = 'Mật khẩu xác nhận không khớp';
        if (strlen($password) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ';
        
        // Check existing username/email
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Tên đăng nhập hoặc email đã tồn tại';
            }
        }
        
        // Process avatar upload
        $avatarName = 'default-avatar.jpg';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadedAvatar = uploadImage($_FILES['avatar'], 'uploads/avatar/');
            if ($uploadedAvatar) {
                $avatarName = $uploadedAvatar;
            }
        }
        
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, avatar) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $fullName, $avatarName]);
                
                $_SESSION['success'] = 'Đăng ký thành công! Vui lòng đăng nhập.';
                redirectTo('login.php');
            } catch (Exception $e) {
                $errors[] = 'Có lỗi xảy ra khi đăng ký!';
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Fashion Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .auth-card {
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
        }
        .auth-header {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            border-radius: 15px 15px 0 0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card auth-card">
                        <div class="card-header auth-header text-center py-4">
                            <h3><i class="fas fa-heart me-2"></i>Fashion Review</h3>
                            <p class="mb-0">Tạo tài khoản mới</p>
                        </div>
                        
                        <div class="card-body p-4">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tên đăng nhập *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" name="username" class="form-control" 
                                                   value="<?= $_POST['username'] ?? '' ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" name="email" class="form-control" 
                                                   value="<?= $_POST['email'] ?? '' ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Họ và tên</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="full_name" class="form-control" 
                                               value="<?= $_POST['full_name'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Mật khẩu *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Xác nhận mật khẩu *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Ảnh đại diện</label>
                                    <input type="file" name="avatar" class="form-control" accept="image/*">
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100 mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Đăng ký
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
                                <p><a href="index.php">Về trang chủ</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php } ?>