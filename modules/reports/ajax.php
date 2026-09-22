<?php
/**
 * Reports AJAX Endpoints Handler
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// AJAX Handler for Customer Details
if (isset($_GET['ajax_customer_history'])) {
    $auth->requireReportAccess('customer_report');
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $name = $_GET['ajax_customer_history'];
    echo json_encode($reports->getCustomerHistory($name), JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// AJAX Handler for Customer Payment Profile (7 Metrics + 3-Year Purchases)
if (isset($_GET['ajax_customer_payment_profile'])) {
    $auth->requireReportAccess('unpaid_invoices');
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $name = $_GET['ajax_customer_payment_profile'];
    try {
        echo json_encode($reports->getCustomerPaymentProfile($name), JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// AJAX Handler for Invoice Details (Full Line Items, Serials, Payments)
if (isset($_GET['ajax_invoice_details'])) {
    $auth->requireReportAccess('invoices');
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $inv = $_GET['ajax_invoice_details'];
    try {
        echo json_encode($reports->getInvoiceDetails($inv), JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// AJAX Handler for Real-Time Warranty & Serial Number Lookup
if (isset($_GET['ajax_warranty_lookup'])) {
    $auth->requireReportAccess('warranties');
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $q = $_GET['ajax_warranty_lookup'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
    try {
        $results = $reports->lookupWarrantySerial($q, $status, $limit);
        echo json_encode(['results' => $results], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
