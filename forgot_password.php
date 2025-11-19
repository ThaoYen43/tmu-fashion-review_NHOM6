<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $newPassword = $_POST['new_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<div class="alert alert-danger">Email không hợp lệ.</div>';
        exit;
    }

    if (strlen($newPassword) < 6) {
        echo '<div class="alert alert-danger">Mật khẩu phải từ 6 ký tự trở lên.</div>';
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->execute([$hashed, $email]);
        echo '<div class="alert alert-success">✅ Đổi mật khẩu thành công!</div>';
    } else {
        echo '<div class="alert alert-danger">❌ Email không tồn tại trong hệ thống.</div>';
    }
}
?>


<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Đặt lại mật khẩu</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="reset_password.php">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email tài khoản</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Cập nhật mật khẩu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
