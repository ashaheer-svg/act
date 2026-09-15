<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "=== VERIFYING 8 STRATEGIC REPORTS ON 2009-2026 DATASET ===\n\n";

// 1. LTV
$ltv = $reports->getCustomerLTVReport();
echo "1. Customer LTV Matrix:\n";
print_r($ltv['summary']);

// 2. Churn
$churn = $reports->getAccountChurnReport();
echo "\n2. Account Churn Pipeline:\n";
print_r($churn['summary']);

// 3. EOL
$eol = $reports->getHardwareEOLReport();
echo "\n3. Hardware EOL:\n";
print_r($eol['summary']);

// 4. Contracts
$contracts = $reports->getContractsRenewalReport();
echo "\n4. Contracts Renewal:\n";
print_r($contracts['summary']);

// 5. Rental ROI
$rental = $reports->getRentalROIReport();
echo "\n5. Rental Fleet Yield:\n";
print_r($rental['summary']);

// 6. Brand Growth
$brand = $reports->getBrandGrowthReport();
echo "\n6. Brand Lifecycle & Growth:\n";
print_r($brand['summary']);

// 7. DSO Trends
$dso = $reports->getDSOTrendsReport();
echo "\n7. Working Capital & DSO:\n";
print_r($dso['summary']);

// 8. Tax Audit
$tax = $reports->getTaxAuditReport();
echo "\n8. Statutory Tax Audit:\n";
print_r($tax['summary']);
