<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = 'reports.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET['type'] = 'contracts';
$_GET['range'] = 'pm_90d';

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$res = $reports->getContractsRenewalReport(['range' => 'pm_90d'], 1, 10);
echo "SUCCESS: Retrieved " . count($res['rows']) . " rows out of " . $res['total'] . " total.\n";
echo "KPIs: Total Contracts: {$res['summary']['total_contracts']}, Pipeline: LKR " . number_format($res['summary']['total_opportunity_value']) . "\n";
echo "First Row: " . json_encode($res['rows'][0]) . "\n";

// Test due_30d
$res30 = $reports->getContractsRenewalReport(['range' => 'due_30d'], 1, 10);
echo "Range due_30d: Total {$res30['total']}\n";

// Test category ma
$resMa = $reports->getContractsRenewalReport(['category' => 'ma'], 1, 10);
echo "Category MA: Total {$resMa['total']}\n";

// Test category acronis
$resAcronis = $reports->getContractsRenewalReport(['category' => 'acronis'], 1, 10);
echo "Category Acronis: Total {$resAcronis['total']}\n";

// Test category hosting
$resHosting = $reports->getContractsRenewalReport(['category' => 'hosting'], 1, 10);
echo "Category Hosting: Total {$resHosting['total']}\n";
