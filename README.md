# Website đánh giá các sản phẩm thương hiệu thời trang

# Tổng quan
Fashion Review là nền tảng trực tuyến cho phép người dùng tra cứu thông tin sản phẩm, xem đánh giá thực tế từ cộng đồng, và chia sẻ trải nghiệm cá nhân sau khi sử dụng các sản phẩm thuộc nhiều thương hiệu thời trang khác nhau. Hệ thống hướng đến việc xây dựng môi trường minh bạch, giúp người mua có cơ sở tham khảo đáng tin cậy trước khi đưa ra quyết định mua sắm, đồng thời hỗ trợ các thương hiệu hiểu rõ hơn về phản hồi của khách hàng để cải thiện chất lượng sản phẩm.

# Yêu Cầu Hệ Thống

## Yêu Cầu Kỹ Thuật
- PHP 7.4 hoặc cao hơn
- MySQL 5.7 hoặc cao hơn
- Máy chủ web (Apache/Nginx)
- PDO PHP Extension
- Trình duyệt web hiện đại có hỗ trợ JavaScript ES6+
- Font Awesome 6.0.0

## Cấu Hình Cơ Sở Dữ Liệu
- **Host**: localhost
- **Tên Database**: fashion_review
- **Tên người dùng**: root
- **Mật khẩu**: trống

# Vai Trò Người Dùng và Quyền Truy Cập

## Quản Trị Viên (Admin)
- Đăng nhập tài khoản
- Quản lý thương hiệu (thêm, sửa, xóa thương hiệu)
- Quản lý sản phẩm trong thương hiệu (thêm, sửa, xóa sản phẩm)
- Xem bảng điều khiển (số người dùng, số bình luận, đánh giá)

## Người Dùng (User)
+ Đăng ký/Đăng nhập
+ Chức năng Xem danh sách thương hiệu
+ Chức năng tìm kiếm thương hiệu
+ Chức năng Xem danh sách sản phẩm trong thương hiệu
+ Chức năng Xem chi tiết đánh giá sản phẩm
+ Chức năng Bình luận & Đánh giá sản phẩm trong thương hiệu
+ Chức năng Quản lý tài khoản cá nhân
+ Chức năng Quản lý danh sách bình luận / Đánh giá của cá nhân

## Khách (Guest)
- Xem danh sách thương hiệu
- Xem danh sách sản phẩm trong thương hiệu
- Xem bình luận và đánh giá của người dùng trong thương hiệu

# Use Cases (Trường Hợp Sử Dụng)

## Use Cases Đăng nhập/ Đăng ký

#### 1. Đăng Nhập
**Tác nhân**: Quản trị viên, Người dùng  
**Mô tả**: Người dùng đăng nhập vào hệ thống bằng email/username và mật khẩu

**Luồng chính**:
1. Người dùng truy cập trang chủ
2. Click vào nút "Đăng nhập"
3. Điền email/username và mật khẩu
4. Hệ thống xác thực thông tin
5. Chuyển hướng đến trang chính (admin.php cho admin, index.php cho user)

**Luồng thay thế**:
- Nếu thông tin sai: Hiển thị thông báo lỗi "Email/tên đăng nhập hoặc mật khẩu không đúng"

#### 2. Đăng Ký
**Tác nhân**: Khách  
**Mô tả**: Khách tạo tài khoản mới trong hệ thống

**Luồng chính**:
1. Khách truy cập trang chủ
2. Click vào nút "Đăng ký"
3. Điền thông tin: Họ tên, Email, Username, Mật khẩu, avatar
4. Hệ thống xác thực và lưu thông tin
5. Chuyển hướng đến trang đăng nhập

**Luồng thay thế**:
- Nếu email/username đã tồn tại: Hiển thị thông báo lỗi
- Nếu mật khẩu không khớp: Hiển thị "Mật khẩu xác nhận không khớp"
- Nếu mật khẩu quá ngắn: Hiển thị "Mật khẩu phải có ít nhất 6 ký tự"
 
# Usecase Admin

## 1. Quản lý thương hiệu
**Tác nhân**: Admin
**Mô tả**: Quản trị viên thêm một thương hiệu thời trang mới vào hệ thống. Thông tin bao gồm tên, phạm vi giá, hình ảnh, và các liên kết bán hàng/mạng xã hội.

**Luồng chính**:
1. Tác nhân (Admin) truy cập vào màn hình "Quản lý thương hiệu".
2. Tác nhân nhấn vào nút "Tạo thương hiệu mới" (hoặc giao diện hiển thị sẵn form tạo).
3. Tác nhân nhập các thông tin bắt buộc: Tên thương hiệu, Giá từ (VND), và Giá đến (VND).
4. Tác nhân nhập các thông tin tùy chọn như: Ý kiến nhận xét của admin, Link Shopee, và Link Facebook.
5. Tác nhân tải lên Logo thương hiệu và Ảnh bìa (nếu có).
6. Tác nhân chọn (tích vào) ô "Thương hiệu nổi bật" nếu cần.
7. Tác nhân nhấn nút "Tạo mới".
8. Hệ thống xác thực dữ liệu và lưu thương hiệu mới vào cơ sở dữ liệu.
9. Hệ thống hiển thị thông báo tạo thành công và làm mới Danh sách thương hiệu.

## 2. Quản lý sản phẩm
**Tác nhân**: Admin
**Mô tả**: Quản trị viên thêm một sản phẩm mới vào hệ thống, liên kết sản phẩm đó với một thương hiệu đã có, nhập thông tin mô tả, giá, hình ảnh và quản lý chi tiết về kích cỡ (size) của sản phẩm.
**Luồng chính**:
1. Tác nhân (Admin) truy cập vào màn hình "Tạo sản phẩm mới".
2. Tác nhân chọn một Thương hiệu từ danh sách thả xuống.
3. Tác nhân nhập các thông tin bắt buộc: Tên sản phẩm, Mô tả ngắn, Mô tả chi tiết (nếu có), và Giá.
4. Tác nhân tải lên Ảnh sản phẩm (nếu có).
5. Tác nhân tiến hành quản lý các kích cỡ (size) của sản phẩm.
6. Đối với mỗi kích cỡ (S, M, L, XL, hoặc thêm mới): Tác nhân nhập các thông số chi tiết như Ngực min, Ngực max, Eo min, Eo max, Mông min, và Mông max.
7. Tác nhân nhấn nút "Tạo sản phẩm mới" (nút này không hiển thị trong ảnh nhưng được ngầm hiểu là bước cuối cùng của việc tạo).
8. Hệ thống xác thực dữ liệu và lưu sản phẩm mới (bao gồm cả các thông số size) vào cơ sở dữ liệu.
9. Hệ thống hiển thị thông báo tạo sản phẩm thành công và chuyển hướng (hoặc làm mới danh sách sản phẩm).

## 3. Quản lý Đánh giá và bình luận
**Tác nhân**:Admin
**Mô tả**: Quản trị viên theo dõi danh sách các bình luận gần đây trên hệ thống và thực hiện hành động kiểm duyệt cơ bản là Ẩn bình luận đó khỏi người dùng cuối.
**Luồng chính**:
1. Tác nhân (Admin) truy cập vào màn hình "Quản lý bình luận".

2. Hệ thống hiển thị danh sách các bình luận gần đây (bao gồm tên người bình luận, nội dung bình luận, thương hiệu/sản phẩm được bình luận, và thời gian).

3. Tác nhân xem xét nội dung của một bình luận cụ thể.

4. Tác nhân nhấn vào nút "Ẩn" tương ứng với bình luận muốn kiểm duyệt.

5. Hệ thống xử lý yêu cầu và đánh dấu bình luận đó là đã bị ẩn trong cơ sở dữ liệu.

6. Hệ thống cập nhật giao diện

7. Bình luận đó không còn hiển thị cho người dùng thông thường trên giao diện công khai nữa.

# Usecase Người dùng

## 1. Xem danh sách thương hiệu

**Tác nhân**: Người dùng (User)/Khách truy cập (Guest)/Admin
**Mô tả**: Người dùng truy cập vào trang chính của hệ thống để xem danh sách tất cả các thương hiệu thời trang đã được thêm vào. Người dùng có thể lọc danh sách này theo các tiêu chí khác nhau (Tất cả, Nổi bật, Phổ biến, Giá rẻ) và tìm kiếm theo tên.

**Luồng chính**:
1. Tác nhân truy cập vào trang chính (`index.php`) của hệ thống.
2. Hệ thống mặc định hiển thị danh sách tất cả các thương hiệu 
3. Mỗi thương hiệu trong danh sách hiển thị các thông tin cơ bản: Logo, Tên, Số lượng đánh giá, Khoảng giá, Mô tả ngắn và Ngày thêm.
4. Tác nhân có thể thực hiện thao tác **Lọc**:
    a. Tác nhân chọn tab **"Nổi bật"**; hệ thống tải lại và chỉ hiển thị các thương hiệu được Admin đánh dấu là nổi bật.
    b. Tác nhân chọn tab **"Phổ biến"**; hệ thống tải lại và hiển thị các thương hiệu theo tiêu chí phổ biến (ví dụ: nhiều đánh giá nhất).
    c. Tác nhân chọn tab **"Giá rẻ"**; hệ thống tải lại và chỉ hiển thị các thương hiệu có mức giá tối đa dưới ngưỡng quy định.

## 2. Tìm kiếm thương hiệu

**Tác nhân**: Người dùng (User)/Khách truy cập (Guest)/Admin
**Mô tả**: Người dùng tìm kiếm một thương hiệu cụ thể bằng từ khóa và hệ thống trả về kết quả tìm kiếm hoặc chuyển hướng đến trang chi tiết.

**Luồng chính**:
1. Tác nhân nhập từ khóa vào ô tìm kiếm "Tìm kiếm thương hiệu".
2. Hệ thống hiển thị danh sách gợi ý các thương hiệu khớp.
    a. Hệ thống chuyển hướng đến trang chi tiết (nếu chỉ có 1 kết quả).
    b. Hệ thống hiển thị danh sách kết quả (nếu có nhiều).
    c. Hệ thống hiển thị thông báo lỗi (nếu không tìm thấy).

## 3. Xem danh sách sản phẩm trong thương hiệu

**Tác nhân**: Người dùng(User)/Khách truy cập(Guest)/Admin
**Mô tả**: Người dùng xem thông tin chi tiết của một thương hiệu, các đánh giá liên quan, và danh sách các sản phẩm đang được bày bán dưới thương hiệu đó.

**Luồng chính**:
1. Tác nhân truy cập vào trang chi tiết của một thương hiệu.
2. Hệ thống hiển thị thông tin chi tiết của thương hiệu và tổng hợp đánh giá (phân bố sao).
3. Hệ thống hiển thị danh sách các sản phẩm thuộc thương hiệu đó.
4. Mỗi sản phẩm hiển thị Tên, Hình ảnh, Giá và Đánh giá sản phẩm.
5. Tác nhân có thể **xem chi tiết sản phẩm** hoặc **thực hiện đánh giá thương hiệu**.

## 4. Xem chi tiết đánh giá sản phẩm

**Tác nhân**: Người dùng (User)/Khách truy cập (Guest)/Admin
**Mô tả**: Người dùng xem thông tin chi tiết của một sản phẩm, các đánh giá/bình luận đã có

**Luồng chính**:
1. Tác nhân truy cập vào trang chi tiết sản phẩm.
2. Hệ thống hiển thị thông tin sản phẩm (mô tả, giá, đánh giá trung bình) và danh sách bình luận.
3. Tác nhân xem xét các bình luận/ rating của người đã trải nghiệm sản phẩm và thương hiệu.

## 5. Đánh giá và bình luận sản phẩm

**Tác nhân**: Người dùng (User)/Admin đã đăng nhập
**Mô tả**: Người dùng đăng nhập thực hiện việc đánh giá (chọn số sao) và viết bình luận cho một sản phẩm cụ thể.

**Luồng chính**:
1. Tác nhân truy cập trang chi tiết sản phẩm.
2. Tác nhân chọn số sao để đánh giá sản phẩm.
3. Tác nhân nhập nội dung bình luận vào ô.
5. Tác nhân nhấn nút **"Đăng bình luận"**.
6. Hệ thống lưu và hiển thị bình luận/đánh giá mới (sau kiểm duyệt nếu có).

# 6. Quản lý tài khoản cá nhân

**Tác nhân**: Người dùng (User) đã đăng nhập/Admin
**Mô tả**: Người dùng truy cập trang cá nhân để xem thông tin tổng quan, xem thống kê hoạt động, và thực hiện việc cập nhật thông tin cá nhân hoặc đổi mật khẩu.

**Luồng chính**:
1. Tác nhân truy cập vào trang **"Cài đặt tài khoản"** (hoặc Trang cá nhân).
2. Hệ thống hiển thị thông tin hồ sơ (Tên, Ngày tham gia) và thống kê hoạt động (Đánh giá, Bình luận).
3. Tác nhân có thể thực hiện **Cập nhật thông tin**:
    a. Tác nhân sửa **Họ và tên** hoặc **Email**.
    b. Tác nhân chọn **Ảnh đại diện mới**.
    c. Tác nhân nhấn **"Cập nhật"**.
4. Tác nhân có thể thực hiện **Đổi mật khẩu**:
    a. Tác nhân nhập **Mật khẩu hiện tại**.
    b. Tác nhân nhập **Mật khẩu mới** và **Xác nhận mật khẩu mới**.
    c. Tác nhân nhấn **"Đổi mật khẩu"**.
5. Hệ thống xác thực và cập nhật thông tin/mật khẩu nếu hợp lệ.

# 7. Quản lý Đánh giá/ Bình luận của mình

**Tác nhân**: Người dùng (User) đã đăng nhập/Admin
**Mô tả**: Người dùng truy cập trang cá nhân để xem lại, chỉnh sửa hoặc xóa các đánh giá (sao) và bình luận đã đăng trên các thương hiệu và sản phẩm.

**Luồng chính**:
1. Tác nhân truy cập vào trang cá nhân và chọn tab **"Đánh giá của tôi"**.
2. Hệ thống hiển thị danh sách các đánh giá (số sao) mà Tác nhân đã thực hiện, kèm theo tên thương hiệu/sản phẩm và ngày đánh giá.
3. Tác nhân chọn tab **"Bình luận của tôi"**.
4. Hệ thống hiển thị danh sách các bình luận mà Tác nhân đã đăng, kèm theo nội dung, thương hiệu liên quan và thời gian.
5. Đối với mỗi bình luận, Tác nhân có thể:
    a. Nhấn vào biểu tượng **Chỉnh sửa** (bút chì) để sửa nội dung bình luận.
    b. Nhấn vào biểu tượng **Xóa** (thùng rác) để xóa bình luận đó khỏi hệ thống.
6. Hệ thống thực hiện thao tác Chỉnh sửa hoặc Xóa theo yêu cầu của Tác nhân.

# Mô hình dữ liệu
## Bảng: brands
| Cột | Kiểu dữ liệu | Mô tả |
|-----|-------------|-------|
| id | INT(11) (PK, AI) | ID thương hiệu |
| name | VARCHAR(100) NOT NULL | Tên thương hiệu |
| description | TEXT NULL | Mô tả thương hiệu |
| logo | VARCHAR(255) NULL | Đường dẫn logo |
| cover_image | VARCHAR(255) NULL | Đường dẫn ảnh bìa |
| price_range_min | DECIMAL(10,0) DEFAULT 0 | Giá thấp nhất |
| price_range_max | DECIMAL(10,0) DEFAULT 0 | Giá cao nhất |
| shopee_link | VARCHAR(255) NULL | Link Shopee |
| facebook_link | VARCHAR(255) NULL | Link Facebook |
| total_ratings | INT(11) DEFAULT 0 | Tổng đánh giá (cache) |
| average_rating | DECIMAL(3,2) DEFAULT 0.00 | Điểm TB đánh giá (cache) |
| is_featured | TINYINT(1) DEFAULT 0 | Cờ nổi bật |
| created_at | TIMESTAMP NOT NULL | Ngày tạo |
| updated_at | TIMESTAMP NOT NULL | Ngày cập nhật |

### Bảng: comments
| Cột | Kiểu dữ liệu | Mô tả |
|-----|-------------|-------|
| id | INT(11) (PK, AI) | ID bình luận |
| user_id | INT(11) (FK) | ID người dùng |
| brand_id | INT(11) (FK) | ID thương hiệu |
| product_id | INT(11) (FK) NULL | ID sản phẩm (nếu có) |
| parent_id | INT(11) (FK) NULL | ID bình luận cha |
| content | TEXT NOT NULL | Nội dung bình luận |
| image | VARCHAR(255) NULL | Ảnh đính kèm |
| is_hidden | TINYINT(1) DEFAULT 0 | Cờ ẩn/hiện |
| created_at | TIMESTAMP NOT NULL | Thời gian tạo |
| updated_at | TIMESTAMP NOT NULL | Thời gian cập nhật |
| rating | TINYINT(4) NULL | Điểm đánh giá (1-5) |

### Bảng: products
| Cột | Kiểu dữ liệu | Mô tả |
|-----|-------------|-------|
| id | INT(11) (PK, AI) | ID sản phẩm |
| brand_id | INT(11) (FK) NOT NULL | ID thương hiệu sở hữu |
| name | VARCHAR(255) NOT NULL | Tên sản phẩm |
| image | VARCHAR(255) NULL | Đường dẫn ảnh sản phẩm |
| short_description | TEXT NULL | Mô tả ngắn |
| description | TEXT NULL | Mô tả chi tiết |
| price | DECIMAL(12,2) DEFAULT 0.00 | Giá sản phẩm |
| average_rating | DECIMAL(3,2) DEFAULT 0.00 | Điểm TB đánh giá (cache) |
| total_ratings | INT(11) DEFAULT 0 | Tổng đánh giá (cache) |
| created_at | DATETIME | Ngày tạo |

### Bảng: product_sizes
| Cột | Kiểu dữ liệu | Mô tả |
|-----|-------------|-------|
| id | INT(11) (PK, AI) | ID kích cỡ |
| product_id | INT(11) (FK) NOT NULL | ID sản phẩm |
| size_label | VARCHAR(16) NOT NULL | Nhãn kích cỡ (S, M, L, XL) |
| bust_min | INT(11) DEFAULT 0 | Số đo ngực nhỏ nhất |
| bust_max | INT(11) DEFAULT 0 | Số đo ngực lớn nhất |
| waist_min | INT(11) DEFAULT 0 | Số đo eo nhỏ nhất |
| waist_max | INT(11) DEFAULT 0 | Số đo eo lớn nhất |
| hip_min | INT(11) DEFAULT 0 | Số đo mông nhỏ nhất |
| hip_max | INT(11) DEFAULT 0 | Số đo mông lớn nhất |

### Bảng: users
| Cột | Kiểu dữ liệu | Mô tả |
|-----|-------------|-------|
| id | INT(11) (PK, AI) | ID người dùng |
| username | VARCHAR(50) NOT NULL | Tên đăng nhập |
| email | VARCHAR(100) NOT NULL | Địa chỉ email |
| password | VARCHAR(255) NOT NULL | Mật khẩu đã mã hóa |
| full_name | VARCHAR(100) NULL | Họ tên đầy đủ |
| avatar | VARCHAR(255) | Đường dẫn ảnh đại diện |
| role | ENUM | Vai trò (user/admin) |
| created_at | TIMESTAMP NOT NULL | Ngày tạo tài khoản |
| updated_at | TIMESTAMP NOT NULL | Ngày cập nhật |

### Bảng: user_measurements
| Cột | Kiểu dữ liệu | Mô tả |
|-----|-------------|-------|
| id | INT(11) (PK, AI) | ID số đo |
| user_id | INT(11) (FK) NULL | ID người dùng |
| brand_id | INT(11) (FK) NULL | ID thương hiệu liên quan |
| preset | VARCHAR(64) NULL | Tên cài đặt sẵn (Preset name) |
| height | INT(11) NULL | Chiều cao |
| bust | INT(11) NULL | Vòng ngực |
| waist | INT(11) NULL | Vòng eo |
| hip | INT(11) NULL | Vòng mông |
| created_at | DATETIME | Ngày tạo |


# Yêu Cầu Giao Diện Người Dùng

## Thiết Kế Chung
- ✅ Thiết kế đáp ứng (responsive) tương thích với máy tính 
- ✅ Điều hướng trực quan với menu sticky
- ✅ Giao diện thân thiện, hiện đại với gradient colors
- ✅ Font Awesome icons cho tất cả actions
- ✅ Hỗ trợ đầy đủ tiếng Việt

## Màu Sắc Chủ Đạo

**Primary**: `#667eea` 
**Success**: `#28a745`
**Warning**: `#ffc107`
**Danger**: `#dc3545`
**Info**: `#17a2b8`

# Tính năng chính
Ôi, tôi xin lỗi! Tôi đã nhầm lẫn và sao chép tính năng của một dự án Mua Bán Công Nghệ thay vì dự án **Đánh giá Sản phẩm và Thương hiệu Thời trang** của bạn. Tôi thành thật xin lỗi vì sự bất tiện này.

Dưới đây là danh sách **Tính Năng Chính** đã được điều chỉnh hoàn toàn để phản ánh đúng cấu trúc và các tính năng cốt lõi của hệ thống **Fashion Review** của bạn, dựa trên các hình ảnh và cấu trúc CSDL chúng ta đã xem:

# Tính Năng Chính

## 1. Hệ Thống Xác Thực & Hồ Sơ Cá Nhân
- ✅ Đăng nhập và Đăng ký tài khoản người dùng
- ✅ Quản lý phiên làm việc an toàn
- ✅ Cập nhật thông tin cá nhân (Họ tên, Email, Avatar)
- ✅ Đổi mật khẩu
- ✅ Xem thống kê hoạt động cá nhân (Tổng số Đánh giá, Bình luận đã đăng)
- ✅ Quản lý và chỉnh sửa/xóa các Đánh giá và Bình luận đã đăng

## 2. Quản Lý Dữ Liệu & Thương Hiệu (Admin)
- ✅ **Admin Dashboard** với Thống kê tổng quan (Thương hiệu/Người dùng/Bình luận/Đánh giá)
- ✅ Tạo mới, chỉnh sửa, và xóa **Thương hiệu** (bao gồm Tên, Mô tả, Logo, Ảnh bìa, Khoảng giá, Link Shopee/Facebook)
- ✅ Thiết lập cờ **"Thương hiệu nổi bật"**
- ✅ Quản lý (Tạo/Sửa/Xóa) **Sản phẩm** thuộc từng thương hiệu
- ✅ Quản lý chi tiết **Kích cỡ sản phẩm** (size S->XL, Ngực/Eo/Mông min-max)

## 3. Đánh Giá & Bình Luận (Core Function)
- ✅ **Đánh giá** thương hiệu (chọn số sao)
- ✅ **Đánh giá** sản phẩm (chọn số sao)
- ✅ **Bình luận** chi tiết cho thương hiệu và sản phẩm
- ✅ Hỗ trợ đính kèm **hình ảnh** trong bình luận
- ✅ **Trả lời** (Reply) bình luận của người dùng khác
- ✅ Tính toán và hiển thị **Điểm đánh giá trung bình** (cho cả Thương hiệu và Sản phẩm)
- ✅ Hiển thị **Phân bố đánh giá** (biểu đồ thanh 1 sao đến 5 sao)

## 4. Duyệt & Lọc Nội Dung
- ✅ Hiển thị danh sách thương hiệu với thông tin tóm tắt
- ✅ **Lọc Thương hiệu** theo các tiêu chí: **Tất cả**, **Nổi bật**, **Phổ biến**, **Giá rẻ**
- ✅ **Tìm kiếm** thương hiệu theo từ khóa và gợi ý kết quả
- ✅ **Kiểm duyệt Bình luận** (Admin có thể Ẩn/Hiện bình luận)
- ✅ Hiển thị sản phẩm mới/đang thịnh hành trong từng thương hiệu

## 5. Thông tin & Tương tác Sản phẩm
- ✅ Xem **trang chi tiết Thương hiệu** (mô tả, logo, liên kết)
- ✅ Xem **trang chi tiết Sản phẩm** (mô tả ngắn/dài, giá, ảnh)
- ✅ **Link trực tiếp** đến các kênh bán hàng (Shopee, Facebook)
- ✅ Hiển thị thông số kích cỡ chi tiết cho sản phẩm
- ✅ Cho phép người dùng thử dáng với các **số đo cá nhân** (Chiều cao, Ngực, Eo, Mông) biết dáng người.

# Cấu trúc dự án
FASHION_REVIEW/
├── anh_website/                     # Folder 'anh_website'
├── assets/
│   └── 3d/                          # Folder '3d'
│
├── config/
│   └── database.php                 # File cấu hình database
│
├── includes/
│   └── product_sizes.php            # File bao gồm (includes) kích cỡ sản phẩm
│
├── uploads/                         # Thư mục chứa các file upload
│
├── admin.php                        # Trang/file quản trị (Admin)
├── brand.php                        # Trang/file quản lý thương hiệu
├── forgot_password.php              # Trang/file quên mật khẩu
├── index.php                        # Trang chủ
├── login.php                        # Trang/file đăng nhập
├── logout.php                       # Trang/file đăng xuất
├── password_reset_log.txt           # Log đặt lại mật khẩu
├── product.php                      # Trang/file quản lý sản phẩm
├── profile.php                      # Trang/file hồ sơ người dùng
├── README.md                        # Tài liệu hướng dẫn sử dụng
├── register.php                     # Trang/file đăng ký
├── reset_passwords.php              # Trang/file xử lý đặt lại mật khẩu
└── stylist.php                      # Trang/file quản lý stylist/người mẫu

## Hướng Dẫn Cài Đặt

### Bước 1: Yêu Cầu Hệ Thống
Đảm bảo máy chủ đáp ứng các yêu cầu:
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx
- PDO Extension

### Bước 2: Clone/Download Project
```bash
# Clone từ repository (nếu có)
git clone https://github.com/ThaoYen43/tmu-fashion-review

# Hoặc download và giải nén file ZIP
```

### Bước 3: Cấu Hình Database

1. Tạo database mới:
```sql
CREATE DATABASE fashion_review CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Sử dụng phpMyAdmin:
- Mở phpMyAdmin
- Chọn database `fashion_review`
- Click tab "Import"
- Chọn file `fashion_review.sql`
- Click "Go"

### Bước 4: Cấu Hình Kết Nối Database

Mở các file PHP và kiểm tra cấu hình database:

```php

$host = 'localhost';
$dbname = 'fashion_review';
$username = 'root';
$password = ''; 
```

### Bước 5: Truy Cập Hệ Thống

Mở trình duyệt và truy cập:
```
http://localhost/fashion_review/
```

## Tài Khoản Mặc Định

### Admin Account
- **Username**: admin
- **Email**: admin@example.com
- **Password**: Admin123
### Test User Accounts
- **Username**: thaoyen
- **Email**: thaoyen0001@gmail.com
- **Password**: thaoyen123

