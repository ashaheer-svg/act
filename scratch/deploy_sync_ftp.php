<?php
$fp = fopen('api/sync.php', 'r');
$ch = curl_init('ftp://active.lk/act/api/sync.php');
curl_setopt($ch, CURLOPT_USERPWD, 'activeftp:active***me');
curl_setopt($ch, CURLOPT_UPLOAD, 1);
curl_setopt($ch, CURLOPT_INFILE, $fp);
curl_setopt($ch, CURLOPT_INFILESIZE, filesize('api/sync.php'));
curl_setopt($ch, CURLOPT_FTP_USE_EPSV, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
fclose($fp);
echo 'FTP Upload result: ' . ($res ? 'SUCCESS' : 'FAILED: ' . $err) . PHP_EOL;
