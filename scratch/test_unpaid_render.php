<?php
require_once 'config.php';
session_name(SESSION_NAME);
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['last_activity'] = time();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Test unpaid_invoices view
$_GET = ['type' => 'unpaid_invoices'];
ob_start();
require 'reports.php';
$html = ob_get_clean();

echo "unpaid_invoices page length: " . strlen($html) . " bytes\n";

if (strpos($html, 'rational-table') !== false) {
    echo "  [PASS] Found rational-table in unpaid_invoices\n";
} else {
    echo "  [FAIL] Missing rational-table\n";
}

if (strpos($html, 'dense-doc-num') !== false) {
    echo "  [PASS] Found dense-doc-num\n";
} else {
    echo "  [FAIL] Missing dense-doc-num\n";
}

if (strpos($html, 'dense-badge') !== false) {
    echo "  [PASS] Found dense-badge\n";
} else {
    echo "  [FAIL] Missing dense-badge\n";
}

if (strpos($html, 'pagination-rail') !== false || strpos($html, 'renderPaginationRail') !== false || strpos($html, 'Showing') !== false) {
    echo "  [PASS] Found pagination rail\n";
} else {
    echo "  [FAIL] Missing pagination\n";
}

if (strpos($html, 'Fatal error') !== false || strpos($html, 'Parse error') !== false) {
    echo "  [FAIL] Detected PHP error!\n";
} else {
    echo "  [PASS] No PHP fatal or parse errors\n";
}
