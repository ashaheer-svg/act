<?php
$lines = file('classes/Reports.php');
foreach ($lines as $num => $l) {
    if (stripos($l, "invoice_type = 'Invoice'") !== false) {
        echo "Line " . ($num + 1) . ": " . trim($l) . "\n";
    }
}
