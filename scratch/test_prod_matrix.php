<?php
$urls = [
    'matrix' => 'https://active.lk/act/reports.php?type=brand_growth&view_mode=matrix',
    'brand' => 'https://active.lk/act/reports.php?type=brand_growth&view_mode=brand',
    'category' => 'https://active.lk/act/reports.php?type=brand_growth&view_mode=category',
    'taxonomy' => 'https://active.lk/act/product_mapping.php?tab=taxonomy',
    'products' => 'https://active.lk/act/product_mapping.php?tab=products'
];

foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    unset($ch);

    echo "[$name] Code: $code, Final URL: $finalUrl, Length: " . strlen($res) . "\n";
    if (stripos($res, 'Fatal error') !== false || stripos($res, 'Parse error') !== false || stripos($res, 'Warning:') !== false) {
        echo "  [ERROR DETECTED]\n";
        preg_match_all('/(Fatal error|Parse error|Warning|Notice):[^\n<]+/i', $res, $matches);
        print_r($matches[0]);
    }
    if (stripos($res, 'login.php') !== false) {
        echo "  [REDIRECTED TO LOGIN]\n";
    }
}
