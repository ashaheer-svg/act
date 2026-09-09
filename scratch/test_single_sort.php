<?php
require 'classes/Database.php';
require 'classes/DataSorter.php';

$db = new Database('data/sales_bi.db');
$sorter = new DataSorter($db);

foreach (['AS000064', 'AS000072', 'AS000051'] as $inv) {
    echo "Sorting $inv...\n";
    $res = $sorter->sortInvoice($inv);
    print_r($res);
    $pRes = $sorter->persistSortedData($res);
    print_r($pRes);
    echo "\n-------------------\n";
}

$items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_number IN ('AS000064', 'AS000072')");
echo json_encode($items, JSON_PRETTY_PRINT) . "\n";
