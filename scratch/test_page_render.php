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

// Test Full Page Render for Invoices
$_GET = ['type' => 'invoices', 'year' => 'all'];
ob_start();
require 'reports.php';
$pageHtml = ob_get_clean();

if (strpos($pageHtml, 'Commercial Invoices Summary') !== false && strpos($pageHtml, 'openInvoiceDetails') !== false && strpos($pageHtml, 'invoiceModalOverlay') !== false) {
    echo "PASS: reports.php?type=invoices rendered full HTML successfully with table, modal markup, and script handlers.\n";
    echo "Length of rendered HTML: " . strlen($pageHtml) . " bytes.\n";
} else {
    echo "FAIL: reports.php?type=invoices failed to render expected elements.\n";
    echo substr($pageHtml, 0, 1000);
}
