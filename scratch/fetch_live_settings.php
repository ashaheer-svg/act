<?php
$cookieFile = __DIR__ . '/cookie_test.txt';
if (file_exists($cookieFile)) @unlink($cookieFile);

// 1. Login
$ch = curl_init('https://act.active.lk/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'admin123'
]));
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);
echo "Login HTTP Code: $code\n";

// 2. Fetch settings.php
$ch = curl_init('https://act.active.lk/settings.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);
echo "Settings HTTP Code: $code, Length: " . strlen($html) . "\n";

file_put_contents(__DIR__ . '/live_settings.html', $html);

echo "Has id='team': " . (strpos($html, 'id="team"') !== false ? 'YES' : 'NO') . "\n";
echo "Has id='system': " . (strpos($html, 'id="system"') !== false ? 'YES' : 'NO') . "\n";
echo "Has System Users: " . (strpos($html, 'System Users') !== false ? 'YES' : 'NO') . "\n";
echo "Has RBAC Matrix: " . (strpos($html, 'Granular Report Permissions Matrix') !== false ? 'YES' : 'NO') . "\n";

// Check where in HTML id="team" appears
$idx = strpos($html, 'id="team"');
if ($idx !== false) {
    echo "\nContext around id='team':\n";
    echo substr($html, max(0, $idx - 150), 400) . "\n";
}

@unlink($cookieFile);
