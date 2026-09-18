<?php
/**
 * Credit Memo Application & Settlement API
 *
 * 1. Inserts settlement records into `payments` table with payment_method = 'Credit Memo'
 * 2. Updates target invoices in `sales` (reduces balance_remaining, increments applied_amount, marks is_paid if settled)
 * 3. Flags returned serial numbers in `hardware_assets` with warranty_status = 'RETURNED'
 */

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

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

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

// Handle compressed payload if sent
if (!empty($payload['compressed_payload'])) {
    $decompressed = gzinflate(base64_decode($payload['compressed_payload']));
    $payload = json_decode($decompressed, true);
}

$creditMemos = $payload['credit_memos'] ?? [];
if (empty($creditMemos)) {
    echo json_encode(['success' => false, 'message' => 'No credit memos payload provided']);
    exit;
}

$paymentsInserted = 0;
$invoicesUpdated = 0;
$serialsUpdated = 0;
$settlementAudit = [];
$now = date('Y-m-d H:i:s');

$db->beginTransaction();

try {
    foreach ($creditMemos as $cm) {
        $cmNum = trim($cm['num'] ?? '');
        $customer = trim($cm['customer'] ?? '');
        $date = trim($cm['date'] ?? date('Y-m-d'));
        $linkedInvoices = $cm['linked_invoices'] ?? [];
        $serials = $cm['serials'] ?? [];

        if (empty($cmNum) || empty($customer)) {
            continue;
        }

        // 1. Process each linked invoice
        foreach ($linkedInvoices as $invNum => $appliedAmt) {
            $appliedAmt = abs(floatval($appliedAmt));
            if ($appliedAmt <= 0 || empty($invNum)) {
                continue;
            }

            // Check if payment already exists
            $existing = $db->fetch(
                "SELECT id FROM payments WHERE customer_name = ? AND reference_num = ? AND invoice_num = ? AND payment_method = 'Credit Memo' LIMIT 1",
                [$customer, $cmNum, $invNum]
            );

            if (!$existing) {
                $db->execute(
                    "INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num, payment_method, memo) VALUES (?, ?, ?, ?, ?, 'Credit Memo', ?)",
                    [$customer, $date, $cmNum, $appliedAmt, $invNum, "Applied Credit Memo #$cmNum"]
                );
                $paymentsInserted++;
            }

            // Update sales table for the invoice
            $invRow = $db->fetch(
                "SELECT invoice_number, total_amount, applied_amount, balance_remaining, is_paid FROM sales WHERE invoice_number = ? AND customer_name = ? LIMIT 1",
                [$invNum, $customer]
            );

            if ($invRow) {
                $currApplied = floatval($invRow['applied_amount']);
                $currBalance = floatval($invRow['balance_remaining']);
                $totalAmt = floatval($invRow['total_amount']);

                // If balance_remaining has not yet accounted for this payment
                $newApplied = $currApplied + $appliedAmt;
                $newBalance = max(0.0, $totalAmt - $newApplied);
                $isPaid = ($newBalance <= 0.01) ? 1 : 0;
                $paidDate = $isPaid ? $date : null;

                $db->execute(
                    "UPDATE sales SET applied_amount = ?, balance_remaining = ?, is_paid = ?, paid_date = COALESCE(paid_date, ?) WHERE invoice_number = ? AND customer_name = ?",
                    [$newApplied, $newBalance, $isPaid, $paidDate, $invNum, $customer]
                );

                $invoicesUpdated++;
                $settlementAudit[] = [
                    'cm_num' => $cmNum,
                    'customer' => $customer,
                    'invoice_num' => $invNum,
                    'applied_amount' => $appliedAmt,
                    'total_amount' => $totalAmt,
                    'new_balance' => $newBalance,
                    'is_paid' => $isPaid
                ];
            }
        }

        // 2. Mark returned serial numbers in hardware_assets
        if (!empty($serials)) {
            foreach ($serials as $sn) {
                $sn = trim($sn);
                if (empty($sn) || strlen($sn) < 4) continue;

                $hwRows = $db->fetchAll(
                    "SELECT id, serial_number, warranty_status, notes FROM hardware_assets WHERE (serial_number = ? OR serial_number LIKE ?) AND customer_name = ? AND warranty_status != 'RETURNED'",
                    [$sn, '%' . $sn . '%', $customer]
                );

                foreach ($hwRows as $hw) {
                    $note = trim(($hw['notes'] ?? '') . " [Returned via Credit Memo #$cmNum on $date]");
                    $db->execute(
                        "UPDATE hardware_assets SET warranty_status = 'RETURNED', notes = ? WHERE id = ?",
                        [$note, $hw['id']]
                    );
                    $serialsUpdated++;
                }
            }
        }
    }

    $db->commit();

    // Verify current state of payments
    $totalCmPayments = (int)($db->fetch("SELECT COUNT(*) as c FROM payments WHERE payment_method = 'Credit Memo'")['c'] ?? 0);
    $totalCmAmount = (float)($db->fetch("SELECT SUM(amount) as s FROM payments WHERE payment_method = 'Credit Memo'")['s'] ?? 0);
    $returnedHwCount = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE warranty_status = 'RETURNED'")['c'] ?? 0);

    echo json_encode([
        'success' => true,
        'message' => "Successfully applied credit memo settlements",
        'payments_inserted' => $paymentsInserted,
        'invoices_updated' => $invoicesUpdated,
        'serials_marked_returned' => $serialsUpdated,
        'cumulative_credit_memo_payments' => $totalCmPayments,
        'cumulative_credit_memo_amount' => $totalCmAmount,
        'total_returned_hardware_units' => $returnedHwCount,
        'settlement_sample' => array_slice($settlementAudit, 0, 10)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
