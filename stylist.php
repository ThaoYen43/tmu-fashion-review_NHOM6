<?php
// Debug helpers
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();

session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// lấy brand nếu có
$brandId = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;
$brand = null;
if ($brandId) {
    $pst = $db->prepare("SELECT id, name FROM brands WHERE id = ?");
    $pst->execute([$brandId]);
    $brand = $pst->fetch(PDO::FETCH_ASSOC);
}

// lấy danh sách sản phẩm của brand (nếu có) để chọn thử dáng
$products = [];
if ($brandId) {
    $pst = $db->prepare("SELECT id, name, image FROM products WHERE brand_id = ? ORDER BY id DESC LIMIT 50");
    $pst->execute([$brandId]);
    $products = $pst->fetchAll(PDO::FETCH_ASSOC);

    // kiểm tra bảng product_sizes tồn tại
    $tableCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $tableCheck->execute(['product_sizes']);
    $hasProductSizes = $tableCheck->fetchColumn() > 0;

    if ($hasProductSizes) {
        // lấy sizes cho từng product
        foreach ($products as &$p) {
            $q = $db->prepare("SELECT size_label, bust_min, bust_max, waist_min, waist_max, hip_min, hip_max FROM product_sizes WHERE product_id = ? ORDER BY FIELD(size_label,'S','M','L','XL') ASC");
            $q->execute([(int)$p['id']]);
            $sizes = $q->fetchAll(PDO::FETCH_ASSOC);
            $p['sizes'] = $sizes ?: [];
        }
        unset($p);
    } else {
        // fallback: nếu products có cột size_* (không chắc chắn) -> thử đọc kích thước chung
        foreach ($products as &$p) {
            // load full product row
            $q = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            $q->execute([(int)$p['id']]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            $p['sizes'] = [];
            if ($row) {
                // nếu tồn tại các cột ví dụ size_s_bust,size_s_waist,... thì gộp lại (tùy DB)
                // không bắt buộc: admin có thể thêm bảng product_sizes để hoạt động chính xác
                $possibleSizes = ['S','M','L','XL'];
                foreach ($possibleSizes as $label) {
                    $bmin = $row["{$label}_bust_min"] ?? null;
                    $bmax = $row["{$label}_bust_max"] ?? null;
                    $wmin = $row["{$label}_waist_min"] ?? null;
                    $wmax = $row["{$label}_waist_max"] ?? null;
                    $hmin = $row["{$label}_hip_min"] ?? null;
                    $hmax = $row["{$label}_hip_max"] ?? null;
                    if ($bmin !== null || $bmax !== null || $wmin !== null || $wmax !== null || $hmin !== null || $hmax !== null) {
                        $p['sizes'][] = [
                            'size_label' => $label,
                            'bust_min' => (int)($bmin ?: 0),
                            'bust_max' => (int)($bmax ?: 0),
                            'waist_min' => (int)($wmin ?: 0),
                            'waist_max' => (int)($wmax ?: 0),
                            'hip_min' => (int)($hmin ?: 0),
                            'hip_max' => (int)($hmax ?: 0),
                        ];
                    }
                }
            }
        }
        unset($p);
    }
}

// Định nghĩa các mã dáng (cập nhật: có khoảng cân nặng để so khớp chính xác)
$bodyPresets = [
    'A' => ['code'=>'A','name'=>'Petite (Nhỏ nhắn)','height'=>[145,155],'weight'=>[38,48],'bust'=>[78,84],'waist'=>[58,64],'hip'=>[82,88],'desc'=>'Dáng thấp, vai nhỏ, form gọn.'],
    'B' => ['code'=>'B','name'=>'Slim (Thon gọn)','height'=>[155,165],'weight'=>[43,54],'bust'=>[80,86],'waist'=>[58,66],'hip'=>[85,90],'desc'=>'Thân hình mảnh, eo nhỏ.'],
    'C' => ['code'=>'C','name'=>'Fit (Cân đối)','height'=>[160,170],'weight'=>[50,63],'bust'=>[84,90],'waist'=>[62,70],'hip'=>[88,94],'desc'=>'Dáng chuẩn, cân đối.'],
    'D' => ['code'=>'D','name'=>'Tall (Cao)','height'=>[168,185],'weight'=>[52,70],'bust'=>[86,94],'waist'=>[64,72],'hip'=>[90,98],'desc'=>'Cao, mảnh, đôi khi vai rộng.'],
    'E' => ['code'=>'E','name'=>'Curvy (Gợi cảm)','height'=>[155,172],'weight'=>[60,80],'bust'=>[90,100],'waist'=>[68,78],'hip'=>[96,106],'desc'=>'Vòng 3 lớn, eo rõ.'],
    'F' => ['code'=>'F','name'=>'Plus-size (Tròn đầy)','height'=>[160,185],'weight'=>[75,110],'bust'=>[100,140],'waist'=>[82,120],'hip'=>[108,140],'desc'=>'Toàn thân tròn đầy, tỉ lệ đa dạng.'],
];

// Hàm chọn dáng phù hợp nhất dựa trên số đo — tăng trọng số cho cân nặng để ưu tiên match theo cân nặng
function match_body_preset($presets, $h, $w, $b, $wa, $hip) {
    $best = null;
    foreach ($presets as $code => $p) {
        $score = 0;
        // cân nặng cho trọng số lớn hơn (2 điểm nếu khớp)
        if ($w >= $p['weight'][0] && $w <= $p['weight'][1]) $score += 2;
        if ($h >= $p['height'][0] && $h <= $p['height'][1]) $score++;
        if ($b >= $p['bust'][0] && $b <= $p['bust'][1]) $score++;
        if ($wa >= $p['waist'][0] && $wa <= $p['waist'][1]) $score++;
        if ($hip >= $p['hip'][0] && $hip <= $p['hip'][1]) $score++;

        if ($best === null || $score > $best['score']) {
            $best = ['code'=>$code,'preset'=>$p,'score'=>$score];
        } elseif ($score === $best['score']) {
            // tie-breaker: lấy preset có tổng khoảng cách nhỏ hơn (height + bust)
            $distCurr = abs($h - ($p['height'][0]+$p['height'][1])/2) + abs($b - ($p['bust'][0]+$p['bust'][1])/2);
            $distBest = abs($h - (($best['preset']['height'][0]+$best['preset']['height'][1])/2)) + abs($b - (($best['preset']['bust'][0]+$best['preset']['bust'][1])/2));
            if ($distCurr < $distBest) {
                $best = ['code'=>$code,'preset'=>$p,'score'=>$score];
            }
        }
    }
    return $best;
}

// Xử lý POST AJAX kiểm tra số đo, kiểm tra size sản phẩm nếu có
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check') {
    header('Content-Type: application/json');

    $height = (int)($_POST['height'] ?? 0);
    $weight = (int)($_POST['weight'] ?? 0);
    $bust   = (int)($_POST['bust'] ?? 0);
    $waist  = (int)($_POST['waist'] ?? 0);
    $hip    = (int)($_POST['hip'] ?? 0);
    $preset = $_POST['preset'] ?? '';
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;

    if (!$height || !$bust || !$waist || !$hip) {
        ob_end_clean();
        echo json_encode(['success'=>false,'message'=>'Vui lòng nhập đầy đủ số đo (chiều cao, ngực, eo, mông).']);
        exit;
    }

    if (!$weight) $weight = round(($height - 100) * 0.9);

    try {
        $matchedSizes = [];
        // nếu chọn product và có product_sizes hoặc sizes data đã nạp server-side -> kiểm tra
        if ($productId) {
            // tìm product trong $products
            $found = null;
            foreach ($products as $p) if ((int)$p['id'] === $productId) { $found = $p; break; }

            if ($found) {
                $sizes = $found['sizes'] ?? [];
                if (!empty($sizes)) {
                    foreach ($sizes as $s) {
                        $bmin = (int)($s['bust_min'] ?? $s['bust_min'] ?? 0);
                        $bmax = (int)($s['bust_max'] ?? $s['bust_max'] ?? 9999);
                        $wmin = (int)($s['waist_min'] ?? $s['waist_min'] ?? 0);
                        $wmax = (int)($s['waist_max'] ?? $s['waist_max'] ?? 9999);
                        $hmin = (int)($s['hip_min'] ?? $s['hip_min'] ?? 0);
                        $hmax = (int)($s['hip_max'] ?? $s['hip_max'] ?? 9999);

                        if ($bust >= $bmin && $bust <= $bmax && $waist >= $wmin && $waist <= $wmax && $hip >= $hmin && $hip <= $hmax) {
                            $matchedSizes[] = $s['size_label'] ?? ($s['size'] ?? 'Unknown');
                        }
                    }
                } else {
                    // nếu không có bảng sizes, thử lấy trường size_bust/size_waist/size_hip tồn tại (single sample)
                    $q = $db->prepare("SELECT size_bust_min AS bust_min, size_bust_max AS bust_max, size_waist_min AS waist_min, size_waist_max AS waist_max, size_hip_min AS hip_min, size_hip_max AS hip_max FROM products WHERE id = ? LIMIT 1");
                    $q->execute([$productId]);
                    $row = $q->fetch(PDO::FETCH_ASSOC);
                    if ($row && ($row['bust_min'] || $row['bust_max'] || $row['waist_min'] || $row['waist_max'] || $row['hip_min'] || $row['hip_max'])) {
                        $bmin = (int)($row['bust_min'] ?: 0);
                        $bmax = (int)($row['bust_max'] ?: 9999);
                        $wmin = (int)($row['waist_min'] ?: 0);
                        $wmax = (int)($row['waist_max'] ?: 9999);
                        $hmin = (int)($row['hip_min'] ?: 0);
                        $hmax = (int)($row['hip_max'] ?: 9999);
                        if ($bust >= $bmin && $bust <= $bmax && $waist >= $wmin && $waist <= $wmax && $hip >= $hmin && $hip <= $hmax) {
                            $matchedSizes[] = 'One-size';
                        }
                    }
                }
            }
        }

        // fallback: nếu không kiểm tra product hoặc không có sizes -> match body preset
        $match = match_body_preset($bodyPresets, $height, $weight, $bust, $waist, $hip);
        $confidence = $match['score'] / 5;
        $message = "Dáng phù hợp: {$match['preset']['name']} (mã {$match['code']})";
        if ($confidence < 0.4) $message = "Không có dáng chuẩn khớp cao, có thể tham khảo kết quả dưới.";

        // Lưu measurements nếu đăng nhập
        try {
            if (function_exists('isLoggedIn') && isLoggedIn()) {
                $uid = getCurrentUserId();
                $db->prepare("CREATE TABLE IF NOT EXISTS user_measurements (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, brand_id INT NULL, product_id INT NULL, preset_code VARCHAR(8), height INT, weight INT, bust INT, waist INT, hip INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)")->execute();
                $db->prepare("INSERT INTO user_measurements (user_id, brand_id, product_id, preset_code, height, weight, bust, waist, hip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                   ->execute([$uid, $brandId, $productId, $match['code'], $height, $weight, $bust, $waist, $hip]);
            }
        } catch (Exception $e) {
            // ignore save errors
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'body_code' => $match['code'],
            'body_name' => $match['preset']['name'],
            'body_desc' => $match['preset']['desc'],
            'score' => $match['score'],
            'confidence' => $confidence,
            'message' => $message,
            'product_id' => $productId,
            'matched_sizes' => $matchedSizes,
            'fits' => count($matchedSizes) > 0
        ]);
        exit;
    } catch (Exception $e) {
        error_log('stylist error: '.$e->getMessage());
        ob_end_clean();
        echo json_encode(['success'=>false,'message'=>'Lỗi server']);
        exit;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stylist — Thử dáng</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.container { max-width:1100px; }
.model-wrap { background:#fff;border-radius:12px;padding:12px;box-shadow:0 8px 20px rgba(0,0,0,0.04); }
.preset-card { border-radius:10px; padding:12px; box-shadow:0 6px 18px rgba(0,0,0,0.04); background:#fff; height:100%; }
.preset-thumb { width:100%; height:160px; object-fit:cover; border-radius:6px; background: #e91e63; display:flex; align-items:center; justify-content:center; color:#6c757d; }
.product-thumb.selected { border:2px solid  #e91e63; }
.small-muted { font-size:12px; color:#6c757d; }
#checkBtn {
    background: linear-gradient(135deg, rgba(233, 30, 99, 0.9), rgba(240, 98, 146, 0.8)) !important;
    border:none !important;
    color:#fff !important;
    border-radius:30px;
    font-weight:600;
    padding:10px 0;
}

#checkBtn:hover {
    background:#d11656 !important;
}
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm mb-3">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php">Fashion Review</a>
    <div>
      <?php if ($brand): ?>
        <a href="brand.php?id=<?= $brand['id'] ?>" class="btn btn-outline-secondary btn-sm">Quay lại <?= htmlspecialchars($brand['name']) ?></a>
      <?php else: ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Quay lại</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container my-4">
  <h4 class="mb-3">Stylist — Thử dáng</h4>
  <?php if ($brand): ?>
    <p class="text-muted small">Thử dáng cho thương hiệu: <strong><?= htmlspecialchars($brand['name']) ?></strong></p>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-7">
      <div class="model-wrap">
        <!-- Product picker: hiển thị ảnh sản phẩm chính và thumbnails -->
        <?php
          $mainImg = null;
          $mainName = '';
          if (!empty($products)) {
              $mainImg = $products[0]['image'] && file_exists(__DIR__ . '/uploads/products/' . $products[0]['image'])
                       ? 'uploads/products/' . $products[0]['image']
                       : null;
              $mainName = $products[0]['name'] ?? '';
          }
        ?>
        <div id="productMain" style="height:420px;background:#f6f6f6;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:10px;">
            <?php if ($mainImg): ?>
                <img id="productMainImg" src="<?= htmlspecialchars($mainImg) ?>" alt="<?= htmlspecialchars($mainName) ?>" style="max-height:100%;max-width:100%;object-fit:contain;border-radius:8px;">
            <?php else: ?>
                <div id="productPlaceholder" class="text-center text-muted">
                    <div style="font-size:36px; font-weight:600; color:#adb5bd;">No Image</div>
                    <div class="mt-2">Chọn sản phẩm để thử dáng</div>
                </div>
            <?php endif; ?>
        </div>

        <div id="productSizes" class="small-muted mt-2 mb-1"></div>

        <div class="d-flex gap-2 mt-3" style="overflow:auto;">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $p):
                    $img = $p['image'] && file_exists(__DIR__ . '/uploads/products/' . $p['image']) ? 'uploads/products/' . $p['image'] : null;
                    $sizesJson = htmlspecialchars(json_encode($p['sizes'] ?? []), ENT_QUOTES);
                ?>
                    <button type="button"
                            class="btn btn-light product-thumb p-0"
                            style="width:88px;height:88px;border-radius:8px;overflow:hidden"
                            data-id="<?= (int)$p['id'] ?>"
                            data-src="<?= htmlspecialchars($img ?? '') ?>"
                            data-sizes="<?= $sizesJson ?>"
                            title="<?= htmlspecialchars($p['name']) ?>">
                        <?php if ($img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f0f0f0;color:#888;">
                                No
                            </div>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted small">Không có sản phẩm thuộc thương hiệu này.</div>
            <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      <div class="card p-3">
        <h6>Số đo của bạn</h6>
        <form id="measureForm">
          <div class="mb-2"><label class="form-label">Chiều cao (cm)</label><input name="height" type="number" min="100" max="220" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Cân nặng (kg) <small class="text-muted">(tuỳ chọn)</small></label><input name="weight" type="number" min="30" max="150" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Vòng ngực (cm)</label><input name="bust" type="number" min="60" max="160" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Vòng eo (cm)</label><input name="waist" type="number" min="50" max="140" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Vòng hông (cm)</label><input name="hip" type="number" min="60" max="170" class="form-control" required></div>

          <input type="hidden" name="preset" id="presetInput" value="C">
          <input type="hidden" name="action" value="check">
          <div class="d-grid gap-2 mt-3"><button class="btn btn-primary" id="checkBtn" type="submit">Kiểm tra</button></div>
        </form>

        <div id="result" class="mt-3" style="display:none"></div>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <h6>Giới thiệu các mã dáng</h6>
    <div class="row">
      <?php foreach ($bodyPresets as $code => $p): ?>
        <div class="col-md-4 mb-3">
          <div class="card p-2">
            <div class="fw-bold"><?= htmlspecialchars($code) ?> — <?= htmlspecialchars($p['name']) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($p['desc']) ?></div>
            <div class="small mt-1">Chiều cao: <?= $p['height'][0] ?>–<?= $p['height'][1] ?> cm • Ngực <?= $p['bust'][0] ?>–<?= $p['bust'][1] ?> cm</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
/* product picker: cập nhật ảnh chính, hiển thị sizes summary và gán product_id cho form */
document.querySelectorAll('.product-thumb').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const src = btn.dataset.src;
        const id = btn.dataset.id;
        const sizesData = btn.dataset.sizes ? JSON.parse(btn.dataset.sizes) : [];
        const mainImg = document.getElementById('productMainImg');
        const placeholder = document.getElementById('productPlaceholder');
        const sizesEl = document.getElementById('productSizes');

        if (src && src.length) {
            if (mainImg) {
                mainImg.src = src;
            } else {
                const img = document.createElement('img');
                img.id = 'productMainImg';
                img.style.maxHeight = '100%';
                img.style.maxWidth = '100%';
                img.style.objectFit = 'contain';
                img.style.borderRadius = '8px';
                img.src = src;
                const container = document.getElementById('productMain');
                container.innerHTML = '';
                container.appendChild(img);
            }
            if (placeholder) placeholder.style.display = 'none';
        } else {
            if (mainImg) mainImg.remove();
            if (placeholder) placeholder.style.display = '';
        }

        // show sizes summary
        if (sizesEl) {
            if (sizesData && sizesData.length) {
                const lines = sizesData.map(s=>{
                    return `${s.size_label}: ngực ${s.bust_min}-${s.bust_max} • eo ${s.waist_min}-${s.waist_max} • mông ${s.hip_min}-${s.hip_max}`;
                });
                sizesEl.textContent = lines.join(' · ');
            } else {
                sizesEl.textContent = 'Sản phẩm chưa có dữ liệu size chi tiết.';
            }
        }

        // set hidden input product_id
        let inp = document.getElementById('productInput');
        if (!inp) {
            inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'product_id';
            inp.id = 'productInput';
            document.getElementById('measureForm').appendChild(inp);
        }
        inp.value = id;

        document.querySelectorAll('.product-thumb').forEach(b=>b.classList.remove('selected'));
        btn.classList.add('selected');
    });
});

// auto-select first product thumb
window.addEventListener('DOMContentLoaded', ()=>{
    const firstThumb = document.querySelector('.product-thumb');
    if (firstThumb) firstThumb.click();
});

/* gửi form AJAX */
document.getElementById('measureForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const form = new FormData(this);
  const resEl = document.getElementById('result');
  resEl.style.display = 'none';
  try {
    const resp = await fetch('stylist.php', { method: 'POST', body: form });
    const data = await resp.json();
    if (data.success) {
      let fitMsg = '';
      if (data.fits && data.matched_sizes && data.matched_sizes.length) {
        fitMsg = `<div class="mb-1">Sản phẩm có size phù hợp: <strong>${data.matched_sizes.join(', ')}</strong></div>`;
      } else if (data.product_id) {
        fitMsg = `<div class="mb-1 text-warning">Sản phẩm này không có size phù hợp với số đo của bạn.</div>`;
      }
      resEl.innerHTML = `<div class="alert ${data.confidence >= 0.6 ? 'alert-success' : (data.confidence >= 0.4 ? 'alert-warning' : 'alert-info')}">
        ${fitMsg}
        <strong>${data.message}</strong>
        <div class="mt-2"><strong>${data.body_code} — ${data.body_name}</strong><div class="small text-muted mt-1">${data.body_desc}</div></div>
      </div>`;
      resEl.style.display = 'block';
      resEl.scrollIntoView({behavior:'smooth'});
    } else {
      resEl.innerHTML = `<div class="alert alert-danger">${data.message || 'Lỗi'}</div>`;
      resEl.style.display = 'block';
    }
  } catch (err) {
    resEl.innerHTML = `<div class="alert alert-danger">Lỗi kết nối</div>`;
    resEl.style.display = 'block';
  }
});
</script>
</body>
</html>
