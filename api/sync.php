<?php
/**
 * QuickBooks Desktop Automated Sync API Endpoint
 *
 * Receives read-only extracted invoices (with full line item descriptions & serial numbers)
 * and payment records from the local Windows SalesBISync tool.
 */

header('Content-Type: application/json; charset=utf-8');

// Ensure root config and classes are loaded
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. POST required.']);
    exit;
}

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->initializeSettings();

// Authenticate API Key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($apiKey) && !empty($authHeader)) {
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    }
}

if (empty($apiKey)) {
    $apiKey = $_POST['api_key'] ?? $_GET['api_key'] ?? '';
}

$configuredKey = $db->getSetting('api_secret_key');

if (empty($configuredKey) || empty($apiKey) || !hash_equals($configuredKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid or missing API key.']);
    exit;
}

// Parse JSON Body
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$invoices = $payload['invoices'] ?? [];
$payments = $payload['payments'] ?? [];

$invoicesImported = 0;
$invoicesSkipped = 0;
$paymentsImported = 0;
$errors = [];

try {
    $db->beginTransaction();

    // 1. Process Invoices
    if (!empty($invoices)) {
        // Prepare statements
        $checkStmt = $db->execute("
            SELECT id FROM sales 
            WHERE invoice_number = ? AND customer_name = ? AND item_description = ? AND qb_amount = ?
            LIMIT 1
        ", ['', '', '', 0]); // dummy initialize
        
        $insertInvoiceStmt = "
            INSERT INTO sales (
                invoice_type, invoice_date, invoice_number, customer_name,
                item_description, tax_code, quantity, qb_amount,
                base_value, vat_component, applied_tax_rate, total_amount,
                product_category, sales_rep_code, po_number, memo, qb_txn_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        foreach ($invoices as $inv) {
            $num = trim($inv['Num'] ?? $inv['invoice_number'] ?? '');
            $customer = trim($inv['Name'] ?? $inv['customer_name'] ?? '');
            
            // Item description: preserve full description including serial numbers
            $itemDesc = trim($inv['Description'] ?? $inv['Item'] ?? $inv['item_description'] ?? 'Item');
            $rawAmount = $inv['Amount'] ?? $inv['amount'] ?? 0;
            $cleanAmount = floatval(str_replace(',', '', $rawAmount));

            if (empty($num) || empty($customer)) {
                $invoicesSkipped++;
                continue;
            }

            // Check duplicate
            $existing = $db->fetch(
                "SELECT id FROM sales WHERE invoice_number = ? AND customer_name = ? AND item_description = ? AND qb_amount = ? LIMIT 1",
                [$num, $customer, $itemDesc, $cleanAmount]
            );

            if ($existing) {
                $invoicesSkipped++;
                continue;
            }

            // Date parsing
            $rawDate = $inv['Date'] ?? $inv['invoice_date'] ?? date('Y-m-d');
            $date = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));

            // VAT calculation
            $taxCode = trim($inv['Sales Tax Code'] ?? $inv['tax_code'] ?? 'Taxable Sales');
            $rate = $db->getTaxRateForDate($date);

            if (stripos($taxCode, 'Non') !== false || stripos($taxCode, 'Zero') !== false || stripos($taxCode, 'Exempt') !== false) {
                $base = $cleanAmount;
                $vat = 0.00;
                $total = $cleanAmount;
            } else {
                // Inclusive VAT calculation (QuickBooks line amount includes tax or standard VAT formula)
                $base = round($cleanAmount / (1 + $rate), 2);
                $vat = round($cleanAmount - $base, 2);
                $total = $cleanAmount;
            }

            $qty = floatval($inv['Qty'] ?? $inv['quantity'] ?? 1);
            $category = trim($inv['Product Category'] ?? $inv['category'] ?? '');
            $rep = trim($inv['Rep'] ?? $inv['sales_rep_code'] ?? '');
            $poNumber = trim($inv['PONumber'] ?? $inv['po_number'] ?? '');
            $memo = trim($inv['Memo'] ?? $inv['memo'] ?? '');
            $txnId = trim($inv['QBTxnID'] ?? $inv['qb_txn_id'] ?? '');

            // Fallback category mapping from product mappings table
            if (empty($category)) {
                $mapping = $db->fetch("SELECT product_category FROM product_mappings WHERE item_description = ?", [$itemDesc]);
                if ($mapping) {
                    $category = $mapping['product_category'];
                }
            }

            $db->execute($insertInvoiceStmt, [
                $inv['Type'] ?? 'Invoice',
                $date,
                $num,
                $customer,
                $itemDesc,
                $taxCode,
                $qty,
                $cleanAmount,
                $base,
                $vat,
                $rate,
                $total,
                $category,
                $rep,
                $poNumber,
                $memo,
                $txnId
            ]);

            $invoicesImported++;
        }
    }

    // 2. Process Payments & Settlements
    if (!empty($payments)) {
        foreach ($payments as $pay) {
            $customer = trim($pay['customer_name'] ?? $pay['Name'] ?? '');
            $rawDate = $pay['payment_date'] ?? $pay['Date'] ?? date('Y-m-d');
            $payDate = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));
            $ref = trim($pay['reference_num'] ?? $pay['RefNumber'] ?? '');
            $amount = floatval(str_replace(',', '', $pay['amount'] ?? $pay['Amount'] ?? 0));
            $invoiceNum = trim($pay['invoice_num'] ?? $pay['AppliedToInvoice'] ?? '');

            if (empty($customer) || $amount <= 0) {
                continue;
            }

            $db->execute(
                "INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num) VALUES (?, ?, ?, ?, ?)",
                [$customer, $payDate, $ref, $amount, $invoiceNum]
            );

            // If matched to an invoice, compute days to pay and mark settled
            if (!empty($invoiceNum)) {
                $matchedInvoice = $db->fetch(
                    "SELECT invoice_date FROM sales WHERE invoice_number = ? AND customer_name = ? LIMIT 1",
                    [$invoiceNum, $customer]
                );

                if ($matchedInvoice) {
                    $invTime = strtotime($matchedInvoice['invoice_date']);
                    $payTime = strtotime($payDate);
                    $days = round(($payTime - $invTime) / (60 * 60 * 24));
                    if ($days < 0) $days = 0;

                    $db->execute(
                        "UPDATE sales SET paid_date = ?, days_to_pay = ? WHERE invoice_number = ? AND customer_name = ?",
                        [$payDate, $days, $invoiceNum, $customer]
                    );
                }
            }

            $paymentsImported++;
        }
    }

    // Update CRM profiles
    $db->syncCustomerProfiles();

    // Update settings: last sync timestamp & count
    $now = date('Y-m-d H:i:s');
    $db->setSetting('last_qb_sync', $now);
    $db->setSetting('last_qb_sync_summary', "Imported $invoicesImported invoices, $paymentsImported payments at $now");

    // Audit log
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $db->logActivity(
        1,
        'QB_API_SYNC',
        "Sync completed: $invoicesImported invoices imported ($invoicesSkipped skipped), $paymentsImported payments added",
        $clientIp
    );

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sync completed successfully.',
        'imported_invoices' => $invoicesImported,
        'skipped_invoices' => $invoicesSkipped,
        'imported_payments' => $paymentsImported,
        'sync_timestamp' => $now
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sync failed: ' . $e->getMessage()
    ]);
}
