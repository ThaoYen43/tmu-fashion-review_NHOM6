<?php
// helper lưu / đọc size sản phẩm
function save_product_sizes(PDO $db, int $productId, array $sizes): void {
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM product_sizes WHERE product_id = ?")->execute([$productId]);
        $ins = $db->prepare("INSERT INTO product_sizes (product_id, size_label, bust_min, bust_max, waist_min, waist_max, hip_min, hip_max, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        foreach ($sizes as $s) {
            $label = trim($s['size_label'] ?? '');
            if ($label === '') continue;
            $bmin = (int)($s['bust_min'] ?? 0);
            $bmax = (int)($s['bust_max'] ?? 0);
            $wmin = (int)($s['waist_min'] ?? 0);
            $wmax = (int)($s['waist_max'] ?? 0);
            $hmin = (int)($s['hip_min'] ?? 0);
            $hmax = (int)($s['hip_max'] ?? 0);
            $ins->execute([$productId, $label, $bmin, $bmax, $wmin, $wmax, $hmin, $hmax]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function get_product_sizes(PDO $db, int $productId): array {
    $q = $db->prepare("SELECT size_label, bust_min, bust_max, waist_min, waist_max, hip_min, hip_max FROM product_sizes WHERE product_id = ? ORDER BY FIELD(size_label,'S','M','L','XL')");
    $q->execute([$productId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
?>