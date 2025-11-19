<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Lấy product id từ URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$productId) redirectTo('index.php');

// Lấy product + brand
$stmt = $db->prepare("
    SELECT p.*, b.id AS brand_id, b.name AS brand_name, b.cover_image AS brand_cover
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) redirectTo('index.php');

// --- thêm: load helper sizes và lấy dữ liệu sizes cho product
require_once __DIR__ . '/includes/product_sizes.php';
$productSizes = [];
if (function_exists('get_product_sizes')) {
    $productSizes = get_product_sizes($db, $productId);
}

// Xử lý POST: đăng comment cho product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && isLoggedIn()) {
    $content = sanitizeInput($_POST['comment']);
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $userId = getCurrentUserId();
    $commentRating = isset($_POST['comment_rating']) && is_numeric($_POST['comment_rating']) ? (int)$_POST['comment_rating'] : null;

    if (!empty($content)) {
        $imageName = null;
        if (!empty($_FILES['comment_image']['name']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK) {
            $imageName = uploadImage($_FILES['comment_image'], 'uploads/comments/');
        }

        try {
            $ins = $db->prepare("INSERT INTO comments (user_id, brand_id, product_id, parent_id, content, image, is_hidden, created_at, rating) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), $commentRating)");
            $ins->execute([$userId, $product['brand_id'], $productId, $parentId, $content, $imageName]);

            // // Nếu comment gốc có rating -> lưu/update product_ratings và cập nhật summary products (nếu có các cột này)
            // if (is_null($parentId) && !is_null($commentRating) && $commentRating >=1 && $commentRating <=5) {
            //     $chk = $db->prepare("SELECT id FROM product_ratings WHERE user_id = ? AND product_id = ?");
            //     $chk->execute([$userId, $productId]);
            //     if ($chk->rowCount() > 0) {
            //         $up = $db->prepare("UPDATE product_ratings SET rating = ? WHERE user_id = ? AND product_id = ?");
            //         $up->execute([$commentRating, $userId, $productId]);
            //     } else {
            //         $insr = $db->prepare("INSERT INTO product_ratings (user_id, product_id, rating) VALUES (?, ?, ?)");
            //         $insr->execute([$userId, $productId, $commentRating]);
            //     }
            //     // Cập nhật fields tổng quan trên products (nếu có các cột này)
            //     $avg = $db->prepare("
            //         UPDATE products
            //         SET average_rating = (SELECT IFNULL(AVG(rating),0) FROM product_ratings WHERE product_id = ?),
            //             total_ratings = (SELECT COUNT(*) FROM product_ratings WHERE product_id = ?)
            //         WHERE id = ?
            //     ");
            //     $avg->execute([$productId, $productId, $productId]);
            // }

            $_SESSION['success'] = 'Bình luận đã được đăng.';
        } catch (Exception $e) {
            error_log($e->getMessage());
            $_SESSION['error'] = 'Lỗi khi đăng bình luận.';
        }
    } else {
        $_SESSION['error'] = 'Vui lòng nhập nội dung bình luận.';
    }
    redirectTo("product.php?id={$productId}");
    exit;
}

// Lấy thống kê rating cho product
$stmt = $db->prepare("SELECT IFNULL(AVG(rating),0) AS avg_rating, COUNT(*) AS total_ratings FROM comments WHERE product_id = ?");
$stmt->execute([$productId]);
$ratingStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Lấy comments gốc của product
$stmt = $db->prepare("
    SELECT c.*, u.username, u.avatar,
           (SELECT rating FROM product_ratings pr WHERE pr.user_id = c.user_id AND pr.product_id = c.product_id LIMIT 1) AS user_rating
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.product_id = ? AND (c.parent_id IS NULL OR c.parent_id = 0) AND c.is_hidden = 0
    ORDER BY c.created_at DESC
");
$stmt->execute([$productId]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy replies cho từng comment
foreach ($comments as $i => $c) {
    $r = $db->prepare("
        SELECT c.*, u.username, u.avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.parent_id = ? AND c.is_hidden = 0
        ORDER BY c.created_at ASC
    ");
    $r->execute([$c['id']]);
    $comments[$i]['replies'] = $r->fetchAll(PDO::FETCH_ASSOC);
}

// ở đầu file (sau kết nối DB và xác định $productId)
require_once __DIR__ . '/includes/product_sizes.php';
$existingSizes = [];
if (!empty($productId)) {
    $existingSizes = get_product_sizes($db, (int)$productId);
}
$defaultLabels = ['S','M','L','XL'];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($product['name']) ?> — <?= htmlspecialchars($product['brand_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary-color: #e91e63;
    --secondary-color: #f06292;
    --accent-color: #ffc107;
    --text-muted: #6c757d;
}
.header-hero {
    background: linear-gradient(135deg, rgba(233,30,99,0.95), rgba(240,98,146,0.9));
    color: white;
    padding: 40px 0;
}
.product-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
}
.avatar{width:48px;height:48px;object-fit:cover}
.reply-avatar{width:36px;height:36px;object-fit:cover}
.comment-card{background:#fff;border-radius:12px;padding:15px;margin-bottom:12px;box-shadow:0 6px 18px rgba(0,0,0,0.04)}
.reply-card{background:#f8f9fa;border-radius:10px;padding:10px;margin:8px 0 8px 56px}
.comment-rating .star{cursor:pointer;color:#ddd}
.comment-rating .star.active{color:var(--accent-color)}
.btn-primary { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none; border-radius:25px; padding:8px 20px; }
</style>
</head>
<body>
<nav class="navbar navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php"><i class="fas fa-heart me-2"></i>Fashion Review</a>
    <div class="d-flex gap-2">
      <a href="brand.php?id=<?= $product['brand_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Quay lại thương hiệu</a>
      <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <a href="admin.php?tab=products&edit_product=<?= $productId ?>" class="btn btn-outline-secondary btn-sm">Sửa sản phẩm</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="header-hero text-center mb-4">
  <div class="container">
    <h1 class="display-6 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
    <div class="small text-white-50"><?= htmlspecialchars($product['brand_name']) ?> — Giá: <strong><?= number_format($product['price'] ?? 0) ?>đ</strong></div>
    <div class="mt-3">
      <i class="fas fa-star text-warning me-1"></i>
      <strong><?= number_format($ratingStats['avg_rating'] ?? 0, 1) ?>/5</strong>
      <span class="ms-2 small">(<?= $ratingStats['total_ratings'] ?? 0 ?> đánh giá)</span>
    </div>
  </div>
</header>

<div class="container my-4">
  <div class="row">
    <div class="col-lg-8">
      <div class="product-card mb-4">
        <div class="row g-0">
          <div class="col-md-5">
            <?php if (!empty($product['image']) && file_exists('uploads/products/'.$product['image'])): ?>
              <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" class="img-fluid rounded-start" alt="">
            <?php else: ?>
              <div class="bg-light d-flex align-items-center justify-content-center" style="height:100%;min-height:280px">
                <i class="fas fa-box-open fa-3x text-muted"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-7">
            <div class="card-body">
              <h4 class="card-title"><?= htmlspecialchars($product['name']) ?></h4>
              <p class="text-muted small mb-2">Thương hiệu: <a href="brand.php?id=<?= $product['brand_id'] ?>"><?= htmlspecialchars($product['brand_name']) ?></a></p>
              <p><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>

            </div>
          </div>
        </div>
      </div>

      <!-- Comment form -->
      <?php if (isLoggedIn()): ?>
        <div class="product-card mb-3 p-3">
          <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">Đánh giá</label>
              <div class="comment-rating" id="productCommentRating">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <i class="fas fa-star star" data-rating="<?= $i ?>"></i>
                <?php endfor; ?>
                <input type="hidden" name="comment_rating" id="productCommentRatingInput" value="">
              </div>
            </div>
            <div class="mb-2">
              <textarea name="comment" class="form-control" rows="3" placeholder="Viết bình luận cho sản phẩm..." required></textarea>
            </div>
            <div class="mb-2">
              <input type="file" name="comment_image" accept="image/*" class="form-control form-control-sm">
            </div>
            <button class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>Đăng bình luận</button>
          </form>
        </div>
      <?php else: ?>
        <div class="alert alert-info"> <a href="login.php">Đăng nhập</a> để bình luận cho sản phẩm này.</div>
      <?php endif; ?>

      <!-- Comments list -->
      <h5 class="mb-3">Bình luận (<?= count($comments) ?>)</h5>
      <?php if (empty($comments)): ?>
        <div class="text-muted">Chưa có bình luận nào.</div>
      <?php endif; ?>

      <?php foreach ($comments as $cIndex => $c): ?>
        <div class="comment-card">
          <div class="d-flex">
            <img src="uploads/avatars/<?= $c['avatar'] ?: 'default-avatar.jpg' ?>" class="avatar rounded-circle me-3" alt="">
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between">
                <div><strong><?= htmlspecialchars($c['username']) ?></strong>
                  <?php if (!empty($c['rating'])): ?><span class="ms-2 text-warning small"><i class="fas fa-star"></i> <?= $c['rating'] ?>/5</span><?php endif; ?>
                </div>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></small>
              </div>
              <div class="mt-2"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
              <?php if (!empty($c['image'])): ?>
                <div class="mt-2"><img src="uploads/comments/<?= htmlspecialchars($c['image']) ?>" style="max-height:140px;cursor:pointer" onclick="openImageModal(this.src)"></div>
              <?php endif; ?>

              <div class="mt-2">
                <?php if (isLoggedIn()): ?>
                  <button class="btn btn-sm btn-outline-primary btn-reply" data-id="<?= $c['id'] ?>">Trả lời</button>
                <?php endif; ?>
              </div>

              <?php if (!empty($c['replies'])): ?>
                <?php foreach ($c['replies'] as $reply): ?>
                  <div class="reply-card">
                    <div class="d-flex">
                      <img src="uploads/avatars/<?= $reply['avatar'] ?: 'default-avatar.jpg' ?>" class="reply-avatar rounded-circle me-3" alt="">
                      <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                          <strong><?= htmlspecialchars($reply['username']) ?></strong>
                          <small class="text-muted"><?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></small>
                        </div>
                        <div class="mt-1"><?= nl2br(htmlspecialchars($reply['content'])) ?></div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

              <!-- Hidden reply form -->
              <div class="mt-2 reply-form" id="reply-form-<?= $c['id'] ?>" style="display:none">
                <form method="POST">
                  <input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
                  <div class="mb-2">
                    <textarea name="comment" class="form-control" rows="2" placeholder="Viết trả lời..." required></textarea>
                  </div>
                  <div>
                    <button class="btn btn-sm btn-primary">Gửi trả lời</button>
                    <button type="button" class="btn btn-sm btn-secondary cancel-reply">Hủy</button>
                  </div>
                </form>
              </div>

            </div>
          </div>
        </div>
      <?php endforeach; ?>

    </div>

    <div class="col-lg-4">
      <div class="card p-3 product-card">
        <h6>Thông tin nhanh</h6>
        <p class="mb-1"><strong>Giá:</strong> <?= number_format($product['price'] ?? 0) ?>đ</p>
        <p class="mb-1"><strong>Thương hiệu:</strong> <a href="brand.php?id=<?= $product['brand_id'] ?>"><?= htmlspecialchars($product['brand_name']) ?></a></p>
        <p class="mb-1"><strong>Đánh giá trung bình:</strong> <?= number_format($ratingStats['avg_rating'],1) ?>/5 (<?= $ratingStats['total_ratings'] ?>)</p>
      </div>
    </div>
  </div>
</div>

<!-- Image modal -->
<div class="modal fade" id="imageModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-body p-0"><img id="modalImage" src="" class="img-fluid w-100"></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openImageModal(src){
  document.getElementById('modalImage').src = src;
  new bootstrap.Modal(document.getElementById('imageModal')).show();
}

/* rating stars for product comment */
document.querySelectorAll('#productCommentRating .star').forEach(st => {
  st.addEventListener('click', function(){
    const r = this.dataset.rating;
    document.getElementById('productCommentRatingInput').value = r;
    document.querySelectorAll('#productCommentRating .star').forEach((s,i)=> s.classList.toggle('active', i < r));
  });
});

/* reply toggle */
document.querySelectorAll('.btn-reply').forEach(btn=>{
  btn.addEventListener('click', ()=> {
    const id = btn.dataset.id;
    const form = document.getElementById('reply-form-'+id);
    document.querySelectorAll('.reply-form').forEach(f=>{ if(f!==form) f.style.display='none'; });
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
  });
});
document.querySelectorAll('.cancel-reply').forEach(b=>b.addEventListener('click', e=>{
  e.target.closest('.reply-form').style.display='none';
}));
</script>
</body>
</html>

<?php
// sau phần xử lý lưu product (nơi có $productId), chèn lưu sizes:
$sizes = $_POST['sizes'] ?? [];
if (!empty($productId)) {
    save_product_sizes($db, (int)$productId, $sizes);
}