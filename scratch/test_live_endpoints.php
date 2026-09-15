<?php
$endpoints = [
    'ltv' => 'Customer Lifetime Value',
    'churn' => 'Account Churn & Reactivation',
    'eol' => 'Hardware End-of-Life',
    'contracts' => 'Maintenance Contracts & SLA',
    'rental_roi' => 'Rental Fleet Utilization',
    'brand_growth' => 'Brand & Product Lifecycle',
    'dso_trends' => 'Working Capital & DSO',
    'tax_audit' => 'Statutory Tax & IRD Audit'
];

echo sprintf("%-15s | %-8s | %-12s | %-35s\n", "Report Type", "HTTP Code", "Content Size", "Validation String");
echo str_repeat("-", 75) . "\n";

foreach ($endpoints as $type => $label) {
    $url = "https://act.active.lk/reports.php?type=$type";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $found = (strpos($html, $label) !== false);
    $size = strlen($html);
    
    echo sprintf("%-15s | %-8d | %-12d | %-35s\n", $type, $httpCode, $size, $found ? "FOUND ($label)" : "NOT FOUND!");
}
