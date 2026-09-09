<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== All Customer Records for Network Information Technologies ===\n";
$custs = $db->fetchAll("SELECT * FROM customer_profiles WHERE customer_name LIKE '%Network Information Technologies%'");
foreach ($custs as $c) {
    print_r($c);
}

echo "\n=== Check customer in CSV ===\n";
$f = __DIR__ . '/../app/exports/qb_export_customers_2026-09-05_125436.csv';
$fh = fopen($f, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < count($header)) continue;
    $d = array_combine($header, $row);
    if (stripos($d['Name'], 'Network Information Technologies') !== false) {
        print_r($d);
    }
}
fclose($fh);
