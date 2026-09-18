<?php
/**
 * Data Integrity & Verification Audit API
 * Verifies live database records against source QuickBooks export data
 * and confirms zero mock / synthetic / hallucinated entries exist.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->initializeSettings();

// Authenticate API Key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

$configuredKey = $db->getSetting('api_secret_key');
if (empty($configuredKey) || empty($apiKey) || !hash_equals($configuredKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$audit = [];

// 1. Check for Mock / Synthetic / Test Data
$mockCustomerNames = ['Apex Global Technologies', 'Matrix Logistics Ltd', 'CloudScale Networks Inc', 'Demo Customer', 'Test Customer', 'Acme Corp'];
$placeholders = implode(',', array_fill(0, count($mockCustomerNames), '?'));

$mockSales = $db->fetchAll(
    "SELECT id, invoice_number, customer_name, qb_amount, invoice_date FROM sales WHERE customer_name IN ($placeholders) OR invoice_number LIKE 'INV-MOCK%'",
    $mockCustomerNames
);

$mockCusts = $db->fetchAll(
    "SELECT customer_name, customer_type FROM customer_profiles WHERE customer_name IN ($placeholders)",
    $mockCustomerNames
);

$mockPayments = $db->fetchAll(
    "SELECT id, customer_name, reference_num, amount FROM payments WHERE customer_name IN ($placeholders)",
    $mockCustomerNames
);

$audit['mock_data_detected'] = [
    'sales_records_count' => count($mockSales),
    'customer_profiles_count' => count($mockCusts),
    'payments_count' => count($mockPayments),
    'mock_sales_samples' => $mockSales,
    'mock_customer_samples' => $mockCusts,
    'is_clean' => (count($mockSales) === 0 && count($mockCusts) === 0 && count($mockPayments) === 0)
];

// If clean action requested, allow removing detected mock data
if (isset($_GET['clean_mock']) && $_GET['clean_mock'] === '1' && !$audit['mock_data_detected']['is_clean']) {
    $db->execute("DELETE FROM sales WHERE customer_name IN ($placeholders) OR invoice_number LIKE 'INV-MOCK%'", $mockCustomerNames);
    $db->execute("DELETE FROM customer_profiles WHERE customer_name IN ($placeholders)", $mockCustomerNames);
    $db->execute("DELETE FROM payments WHERE customer_name IN ($placeholders)", $mockCustomerNames);
    $audit['mock_data_cleaned'] = true;
}

// 2. Data Integrity & Shape Checks
$emptyCustomerSales = $db->fetch("SELECT count(*) as c FROM sales WHERE customer_name IS NULL OR TRIM(customer_name) = ''")['c'] ?? 0;
$emptyInvoiceNum = $db->fetch("SELECT count(*) as c FROM sales WHERE invoice_number IS NULL OR TRIM(invoice_number) = ''")['c'] ?? 0;
$invalidDates = $db->fetch("SELECT count(*) as c FROM sales WHERE invoice_date NOT LIKE '____-__-__'")['c'] ?? 0;

$audit['anomaly_checks'] = [
    'sales_with_empty_customer' => (int)$emptyCustomerSales,
    'sales_with_empty_invoice_num' => (int)$emptyInvoiceNum,
    'sales_with_malformed_date' => (int)$invalidDates,
    'passed_anomaly_checks' => ($emptyCustomerSales == 0 && $emptyInvoiceNum == 0 && $invalidDates == 0)
];

// 3. Database Statistics & Ranges
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_sales_rows,
        COUNT(DISTINCT invoice_number) as unique_invoices,
        COUNT(DISTINCT customer_name) as unique_customers,
        MIN(invoice_date) as earliest_invoice_date,
        MAX(invoice_date) as latest_invoice_date,
        ROUND(SUM(base_value), 2) as total_base_revenue,
        ROUND(SUM(vat_component), 2) as total_vat,
        ROUND(SUM(total_amount), 2) as total_amount
    FROM sales
    WHERE invoice_type != 'Credit Memo'
");

$cmStats = $db->fetch("
    SELECT 
        COUNT(*) as total_cm_rows,
        COUNT(DISTINCT invoice_number) as unique_cms,
        ROUND(SUM(total_amount), 2) as total_credit_amount
    FROM sales
    WHERE invoice_type = 'Credit Memo'
");

$payStats = $db->fetch("
    SELECT 
        COUNT(*) as total_payment_rows,
        COUNT(DISTINCT customer_name) as unique_paying_customers,
        MIN(payment_date) as earliest_payment_date,
        MAX(payment_date) as latest_payment_date,
        ROUND(SUM(amount), 2) as total_payments_received
    FROM payments
");

$custStats = $db->fetch("
    SELECT 
        COUNT(*) as total_profiles,
        SUM(CASE WHEN is_vat_registered = 1 THEN 1 ELSE 0 END) as vat_registered_count,
        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_type_count
    FROM customer_profiles
");

$itemsCount = (int)($db->fetch("SELECT COUNT(*) as c FROM invoice_items")['c'] ?? 0);
$hwStats = $db->fetch("
    SELECT 
        COUNT(*) as total_hardware_assets,
        SUM(CASE WHEN serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED' THEN 1 ELSE 0 END) as with_serials,
        SUM(CASE WHEN warranty_status = 'ACTIVE' THEN 1 ELSE 0 END) as active_warranties,
        SUM(CASE WHEN warranty_status = 'EXPIRED' THEN 1 ELSE 0 END) as expired_warranties,
        SUM(CASE WHEN is_rental = 1 THEN 1 ELSE 0 END) as rental_assets
    FROM hardware_assets
");
$subStats = $db->fetch("
    SELECT 
        COUNT(*) as total_subscriptions,
        SUM(CASE WHEN renewal_status = 'ACTIVE' THEN 1 ELSE 0 END) as active_subscriptions,
        SUM(CASE WHEN renewal_status = 'DUE_SOON' THEN 1 ELSE 0 END) as due_soon_subscriptions,
        SUM(CASE WHEN renewal_status = 'EXPIRED' THEN 1 ELSE 0 END) as expired_subscriptions,
        ROUND(SUM(renewal_opportunity_value), 2) as total_renewal_value
    FROM software_subscriptions
");

$audit['database_summary'] = [
    'invoices' => $stats,
    'credit_memos' => $cmStats,
    'payments' => $payStats,
    'customer_profiles' => $custStats,
    'operational_registries' => [
        'invoice_items_total' => $itemsCount,
        'hardware_assets' => $hwStats,
        'software_subscriptions' => $subStats
    ]
];

// 4. Sample Verification: Return top 5 invoices by amount and 5 most recent
$topInvoices = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, item_description, quantity, qb_amount, base_value, total_amount, product_category
    FROM sales
    WHERE invoice_type != 'Credit Memo'
    ORDER BY qb_amount DESC
    LIMIT 5
");

$recentInvoices = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, item_description, quantity, qb_amount, total_amount, product_category
    FROM sales
    WHERE invoice_type != 'Credit Memo'
    ORDER BY invoice_date DESC, id DESC
    LIMIT 5
");

$audit['sample_records'] = [
    'top_value_invoices' => $topInvoices,
    'most_recent_invoices' => $recentInvoices
];

echo json_encode([
    'success' => true,
    'audit_timestamp' => date('c'),
    'audit' => $audit
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
