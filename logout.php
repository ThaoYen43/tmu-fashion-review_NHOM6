<?php
// logout.php - Đăng xuất và redirect về trang chủ
session_start();

// Lưu thông báo thành công trước khi destroy session
$_SESSION['logout_message'] = 'Đã đăng xuất thành công!';

// Hủy tất cả session variables
session_unset();

// Hủy session
session_destroy();

// Khởi tạo session mới để lưu thông báo
session_start();
$_SESSION['success_message'] = 'Đã đăng xuất thành công!';

// Redirect về trang chủ
header("Location: index.php");
exit();
?>