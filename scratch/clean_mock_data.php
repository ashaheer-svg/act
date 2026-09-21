<?php
$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = 'https://act.active.lk/api/audit_data.php?clean_mock=1';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $config['api_key']]);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "HTTP Code: $code\n";
$data = json_decode($res, true);
echo "Mock data is_clean: " . ($data['audit']['mock_data_detected']['is_clean'] ? 'YES' : 'NO') . "\n";
echo "Mock data cleaned: " . (!empty($data['audit']['mock_data_cleaned']) ? 'YES' : 'NO') . "\n";
