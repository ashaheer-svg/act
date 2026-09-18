<?php
$config = json_decode(file_get_contents('app/binary/config.json'), true);
$ch = curl_init('https://act.active.lk/api/sync.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $config['api_key']]);
$res = curl_exec($ch);
curl_close($ch);
echo $res . PHP_EOL;
