<?php
$lines = file('classes/Reports.php');
$methods = [
    'getCustomerLTVReport', 'getAccountChurnReport', 'getHardwareEOLReport',
    'getContractsRenewalReport', 'getRentalROIReport', 'getTaxAuditReport',
    'getRenewalsReport', 'getStockMovementAnalysis'
];

foreach ($lines as $i => $l) {
    foreach ($methods as $m) {
        if (preg_match('/function\s+' . $m . '\s*\(/', $l)) {
            echo ($i + 1) . ": " . trim($l) . "\n";
        }
    }
}
