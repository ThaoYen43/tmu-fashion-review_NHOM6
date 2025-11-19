<?php
/*
 * SCRIPT RESET MẬT KHẨU CHO FASHION REVIEW DATABASE
 * 
 * CẢNH BÁO: Xóa file này ngay sau khi sử dụng!
 */

// Kết nối đến cơ sở dữ liệu
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'fashion_review');

$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Kiểm tra kết nối
if ($conn === false) {
    die("ERROR: Could not connect to database. " . mysqli_connect_error());
}

// Thiết lập charset UTF-8
mysqli_set_charset($conn, "utf8mb4");

echo "<h2>🔧 Fashion Review - Reset Password Tool</h2>";
echo "<hr>";

// Mật khẩu mới cho từng loại tài khoản
$admin_password = 'Admin123';
$user_password = 'User123';

// Mã hóa các mật khẩu
$hashed_admin_password = password_hash($admin_password, PASSWORD_DEFAULT);
$hashed_user_password = password_hash($user_password, PASSWORD_DEFAULT);

echo "<h3>📊 Thống kê trước khi reset:</h3>";

// Kiểm tra số lượng user hiện tại
$check_admin = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
$result_admin = mysqli_query($conn, $check_admin);
$admin_count = mysqli_fetch_assoc($result_admin)['count'];

$check_user = "SELECT COUNT(*) as count FROM users WHERE role = 'user'";
$result_user = mysqli_query($conn, $check_user);
$user_count = mysqli_fetch_assoc($result_user)['count'];

echo "👤 Admin accounts: <strong>$admin_count</strong><br>";
echo "👥 User accounts: <strong>$user_count</strong><br>";
echo "<hr>";

// Hiển thị danh sách admin hiện tại
echo "<h3>👨‍💼 Danh sách Admin sẽ được reset:</h3>";
$list_admin = "SELECT id, username, email, full_name FROM users WHERE role = 'admin'";
$result_list = mysqli_query($conn, $list_admin);

if (mysqli_num_rows($result_list) > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th></tr>";
    while($row = mysqli_fetch_assoc($result_list)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['username']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Không tìm thấy tài khoản admin nào!</p>";
}

echo "<hr>";
echo "<h3>🔄 Bắt đầu reset mật khẩu...</h3>";

// Cập nhật mật khẩu cho admin
$sql_admin = "UPDATE users SET password = ? WHERE role = 'admin'";
$stmt_admin = mysqli_prepare($conn, $sql_admin);
mysqli_stmt_bind_param($stmt_admin, "s", $hashed_admin_password);

if (mysqli_stmt_execute($stmt_admin)) {
    $affected_admin = mysqli_stmt_affected_rows($stmt_admin);
    echo "✅ <strong>Mật khẩu Admin đã được cập nhật thành công!</strong><br>";
    echo "📋 Số tài khoản admin được cập nhật: <strong>$affected_admin</strong><br>";
    echo "🔑 Mật khẩu mới cho admin: <strong style='color: red;'>$admin_password</strong><br><br>";
} else {
    echo "❌ Lỗi cập nhật admin: " . mysqli_error($conn) . "<br>";
}

// Cập nhật mật khẩu cho user (tùy chọn)
$sql_user = "UPDATE users SET password = ? WHERE role = 'user'";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $hashed_user_password);

if (mysqli_stmt_execute($stmt_user)) {
    $affected_user = mysqli_stmt_affected_rows($stmt_user);
    echo "✅ <strong>Mật khẩu User đã được cập nhật thành công!</strong><br>";
    echo "📋 Số tài khoản user được cập nhật: <strong>$affected_user</strong><br>";
    echo "🔑 Mật khẩu mới cho user: <strong style='color: red;'>$user_password</strong><br><br>";
} else {
    echo "❌ Lỗi cập nhật user: " . mysqli_error($conn) . "<br>";
}

echo "<hr>";
echo "<h3>📊 Thống kê sau khi reset:</h3>";

// Kiểm tra lại sau khi update
$final_check = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$final_result = mysqli_query($conn, $final_check);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Role</th><th>Count</th><th>New Password</th></tr>";

while($row = mysqli_fetch_assoc($final_result)) {
    $password_display = '';
    if ($row['role'] == 'admin') {
        $password_display = $admin_password;
    } elseif ($row['role'] == 'user') {
        $password_display = $user_password;
    }
    
    echo "<tr>";
    echo "<td><strong>" . ucfirst($row['role']) . "</strong></td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "<td style='color: red; font-weight: bold;'>" . $password_display . "</td>";
    echo "</tr>";
}
echo "</table>";

// Tạo log
$log_entry = date('Y-m-d H:i:s') . " - Password reset completed\n";
$log_entry .= "Admin password: $admin_password\n";
$log_entry .= "User password: $user_password\n";
$log_entry .= "Admin accounts affected: $affected_admin\n";
$log_entry .= "User accounts affected: $affected_user\n";
$log_entry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
$log_entry .= "---\n";

file_put_contents('password_reset_log.txt', $log_entry, FILE_APPEND);

// Đóng kết nối
mysqli_stmt_close($stmt_admin);
mysqli_stmt_close($stmt_user);
mysqli_close($conn);

echo "<hr>";
echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>⚠️ QUAN TRỌNG:</h3>";
echo "<ul>";
echo "<li>✅ Đã hoàn tất cập nhật mật khẩu</li>";
echo "<li>🗂️ Log đã được lưu vào file <strong>password_reset_log.txt</strong></li>";
echo "<li>🔐 Hãy ghi nhớ mật khẩu mới và đăng nhập ngay</li>";
echo "<li>🗑️ <strong style='color: red;'>XÓA FILE NÀY NGAY SAU KHI SỬ DỤNG!</strong></li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔑 Đăng nhập Admin</a>";
echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏠 Trang chủ</a>";
echo "<a href='http://localhost/phpmyadmin' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;' target='_blank'>🗄️ phpMyAdmin</a>";
echo "</div>";

echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24; animation: blink 2s infinite;'>";
echo "<strong>🚨 NHẮC NHỞ: XÓA FILE reset_password.php NGAY BÂY GIỜ!</strong>";
echo "</div>";

echo "<style>";
echo "@keyframes blink { 0%, 50% { opacity: 1; } 51%, 100% { opacity: 0.7; } }";
echo "</style>";
?>

<!-- Tự động chuyển hướng sau 60 giây -->
<script>
setTimeout(function() {
    if (confirm('Script đã chạy xong 60 giây. Bạn có muốn chuyển đến trang đăng nhập không?')) {
        window.location.href = 'login.php';
    }
}, 60000);

// Cảnh báo khi rời trang
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'Bạn đã nhớ xóa file reset_password.php chưa?';
});
</script>