<?php
$lines = file('classes/Reports.php');
echo "Searching classes/Reports.php for 'invoice_type':\n";
foreach ($lines as $i => $line) {
    if (stripos($line, 'invoice_type') !== false) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}

echo "\nSearching classes/Reports.php for 'Credit Memo':\n";
foreach ($lines as $i => $line) {
    if (stripos($line, 'Credit Memo') !== false) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}
