<?php
$content = file_get_contents('classes/Reports.php');
$methods = [
    'getCustomerLTVReport', 'getAccountChurnReport', 'getHardwareEOLReport',
    'getContractsRenewalReport', 'getRentalROIReport', 'getTaxAuditReport',
    'getRenewalsReport', 'getStockMovementAnalysis'
];

foreach ($methods as $m) {
    if (preg_match('/function\s+' . $m . '\s*\([^)]*\)\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s', $content, $match)) {
        echo "=== Method: $m ===\n";
        $body = $match[1];
        preg_match_all('/.*\$limit.*/', $body, $limitLines);
        foreach ($limitLines[0] as $ll) {
            echo "  " . trim($ll) . "\n";
        }
    }
}
