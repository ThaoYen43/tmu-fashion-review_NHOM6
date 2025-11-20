<?php
/**
 * Improved Size Matching Logic
 * Cải thiện thuật toán tìm size phù hợp với flexible matching và scoring
 */

/**
 * Tìm size phù hợp với scoring system
 * 
 * @param array $sizes Danh sách sizes của sản phẩm
 * @param int $bust Vòng ngực
 * @param int $waist Vòng eo
 * @param int $hip Vòng mông
 * @param int $tolerance Khoảng dung sai (cm)
 * @return array ['matched' => [...], 'nearest' => [...], 'suggestions' => '...']
 */
function find_matching_sizes_improved($sizes, $bust, $waist, $hip, $tolerance = 3) {
    $results = [];
    
    foreach ($sizes as $size) {
        $label = $size['size_label'];
        $bmin = (int)$size['bust_min'];
        $bmax = (int)$size['bust_max'];
        $wmin = (int)$size['waist_min'];
        $wmax = (int)$size['waist_max'];
        $hmin = (int)$size['hip_min'];
        $hmax = (int)$size['hip_max'];
        
        // Tính điểm cho từng số đo (0-100)
        $bustScore = calculate_fit_score($bust, $bmin, $bmax, $tolerance);
        $waistScore = calculate_fit_score($waist, $wmin, $wmax, $tolerance);
        $hipScore = calculate_fit_score($hip, $hmin, $hmax, $tolerance);
        
        // Trọng số: ngực quan trọng nhất (40%), eo (35%), mông (25%)
        $totalScore = ($bustScore * 0.4) + ($waistScore * 0.35) + ($hipScore * 0.25);
        
        // Phân tích chi tiết
        $analysis = [
            'bust' => analyze_measurement($bust, $bmin, $bmax, 'ngực'),
            'waist' => analyze_measurement($waist, $wmin, $wmax, 'eo'),
            'hip' => analyze_measurement($hip, $hmin, $hmax, 'mông'),
        ];
        
        $results[] = [
            'label' => $label,
            'score' => $totalScore,
            'bust_score' => $bustScore,
            'waist_score' => $waistScore,
            'hip_score' => $hipScore,
            'analysis' => $analysis,
            'size_data' => $size
        ];
    }
    
    // Sắp xếp theo điểm
    usort($results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    // Phân loại kết quả
    $matched = [];      // Score >= 80: Perfect fit
    $goodFit = [];      // Score >= 60: Good fit
    $possible = [];     // Score >= 40: Possible fit
    
    foreach ($results as $r) {
        if ($r['score'] >= 80) {
            $matched[] = $r;
        } elseif ($r['score'] >= 60) {
            $goodFit[] = $r;
        } elseif ($r['score'] >= 40) {
            $possible[] = $r;
        }
    }
    
    // Tạo suggestions
    $suggestions = generate_suggestions($results, $matched, $goodFit, $possible);
    
    return [
        'matched' => $matched,
        'good_fit' => $goodFit,
        'possible' => $possible,
        'nearest' => $results[0] ?? null,
        'all_results' => $results,
        'suggestions' => $suggestions
    ];
}

/**
 * Tính điểm fit cho một số đo (0-100)
 */
function calculate_fit_score($measurement, $min, $max, $tolerance) {
    // Perfect fit: trong khoảng
    if ($measurement >= $min && $measurement <= $max) {
        // Điểm cao hơn nếu ở giữa khoảng
        $range = $max - $min;
        $position = $measurement - $min;
        $centerDistance = abs($position - $range / 2);
        return 100 - ($centerDistance / ($range / 2) * 10); // 90-100 điểm
    }
    
    // Trong khoảng tolerance
    if ($measurement >= ($min - $tolerance) && $measurement <= ($max + $tolerance)) {
        $distance = min(abs($measurement - $min), abs($measurement - $max));
        return 80 - ($distance / $tolerance * 20); // 60-80 điểm
    }
    
    // Ngoài tolerance: điểm giảm theo khoảng cách
    $distance = min(abs($measurement - $min), abs($measurement - $max));
    $score = max(0, 60 - ($distance - $tolerance) * 5);
    return $score;
}

/**
 * Phân tích một số đo cụ thể
 */
function analyze_measurement($measurement, $min, $max, $name) {
    if ($measurement >= $min && $measurement <= $max) {
        return ['status' => 'perfect', 'message' => "Vòng {$name} vừa vặn"];
    }
    
    if ($measurement < $min) {
        $diff = $min - $measurement;
        if ($diff <= 2) {
            return ['status' => 'slightly_small', 'message' => "Vòng {$name} hơi nhỏ ({$diff}cm)"];
        } else {
            return ['status' => 'too_small', 'message' => "Vòng {$name} nhỏ hơn {$diff}cm"];
        }
    }
    
    if ($measurement > $max) {
        $diff = $measurement - $max;
        if ($diff <= 2) {
            return ['status' => 'slightly_large', 'message' => "Vòng {$name} hơi lớn ({$diff}cm)"];
        } else {
            return ['status' => 'too_large', 'message' => "Vòng {$name} lớn hơn {$diff}cm"];
        }
    }
}

/**
 * Tạo gợi ý dựa trên kết quả
 */
function generate_suggestions($allResults, $matched, $goodFit, $possible) {
    if (!empty($matched)) {
        $labels = array_map(fn($r) => $r['label'], $matched);
        return "Size " . implode(', ', $labels) . " phù hợp hoàn hảo với bạn!";
    }
    
    if (!empty($goodFit)) {
        $best = $goodFit[0];
        $issues = array_filter(array_column($best['analysis'], 'message'), function($msg) {
            return strpos($msg, 'hơi') !== false;
        });
        
        $suggestion = "Size {$best['label']} khá phù hợp";
        if (!empty($issues)) {
            $suggestion .= " (lưu ý: " . implode(', ', $issues) . ")";
        }
        return $suggestion;
    }
    
    if (!empty($possible)) {
        $best = $possible[0];
        return "Size {$best['label']} có thể vừa, nhưng bạn nên thử để chắc chắn.";
    }
    
    if (!empty($allResults)) {
        $nearest = $allResults[0];
        $issues = array_column($nearest['analysis'], 'message');
        return "Size {$nearest['label']} là gần nhất, nhưng " . implode(', ', $issues) . ". Bạn có thể cần size đặc biệt.";
    }
    
    return "Không tìm thấy size phù hợp. Vui lòng liên hệ shop để tư vấn.";
}

/**
 * Demo function
 */
function demo_improved_matching() {
    // Giả lập sizes của một sản phẩm
    $sizes = [
        ['size_label' => 'S', 'bust_min' => 78, 'bust_max' => 84, 'waist_min' => 58, 'waist_max' => 64, 'hip_min' => 82, 'hip_max' => 88],
        ['size_label' => 'M', 'bust_min' => 84, 'bust_max' => 90, 'waist_min' => 64, 'waist_max' => 70, 'hip_min' => 88, 'hip_max' => 94],
        ['size_label' => 'L', 'bust_min' => 90, 'bust_max' => 96, 'waist_min' => 70, 'waist_max' => 76, 'hip_min' => 94, 'hip_max' => 100],
    ];
    
    // Test case: người có số đo 86-66-90 (gần M nhưng ngực hơi lớn)
    $result = find_matching_sizes_improved($sizes, 86, 66, 90);
    
    echo "<h3>Demo: Số đo 86-66-90</h3>";
    echo "<h4>Matched sizes (≥80 điểm):</h4>";
    if (!empty($result['matched'])) {
        foreach ($result['matched'] as $r) {
            echo "<p>Size {$r['label']}: {$r['score']} điểm</p>";
        }
    } else {
        echo "<p>Không có</p>";
    }
    
    echo "<h4>Good fit (≥60 điểm):</h4>";
    if (!empty($result['good_fit'])) {
        foreach ($result['good_fit'] as $r) {
            echo "<p>Size {$r['label']}: {$r['score']} điểm - " . implode(', ', array_column($r['analysis'], 'message')) . "</p>";
        }
    } else {
        echo "<p>Không có</p>";
    }
    
    echo "<h4>Gợi ý:</h4>";
    echo "<p>{$result['suggestions']}</p>";
    
    echo "<h4>Chi tiết tất cả sizes:</h4>";
    echo "<pre>" . json_encode($result['all_results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
}

// Nếu chạy trực tiếp
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Improved Size Matching Demo</title></head><body>";
    echo "<h2>🎯 Improved Size Matching Algorithm</h2>";
    demo_improved_matching();
    echo "</body></html>";
}
?>
