<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://act.active.lk/reports.php?api=details&inv=ASN000108');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
unset($ch);

echo "=== Response for ASN000108 ===\n";
echo $res . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://act.active.lk/reports.php?api=details&inv=ASN000104');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res2 = curl_exec($ch);
unset($ch);

echo "=== Response for ASN000104 ===\n";
echo $res2 . "\n";
