<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->syncSchema();

$apiKey = $db->getSetting('api_secret_key');
if (empty($apiKey)) {
    $apiKey = 'test_secret_key_123';
    $db->setSetting('api_secret_key', $apiKey);
}

// Prepare sample payload
$payload = [
    'source' => 'test_script',
    'timestamp' => date('c'),
    'customers' => [
        [
            'name' => 'Acme Technologies Ltd',
            'customer_type' => 'Partner',
            'is_vat_registered' => 1
        ]
    ],
    'invoices' => [
        [
            'Type' => 'Invoice',
            'Date' => '2026-09-01',
            'Num' => 'AS-TEST-9901',
            'Name' => 'Acme Technologies Ltd',
            'Item' => 'Hardware:Server',
            'Description' => 'Enterprise Server R750 [S/N: R750-TEST-SN99]',
            'Sales Tax Code' => 'Taxable Sales',
            'Qty' => 1,
            'Amount' => 100000.00,
            'Product Category' => 'Hardware',
            'subtotal' => 100000.00,
            'applied_amount' => 0,
            'balance_remaining' => 100000.00,
            'is_paid' => 0,
            'end_customer' => 'Apex General Hospital'
        ]
    ],
    'credit_memos' => [
        [
            'Type' => 'Credit Memo',
            'Date' => '2026-09-05',
            'Num' => 'CM-TEST-9901',
            'Name' => 'Acme Technologies Ltd',
            'Item' => 'Hardware:Server',
            'Description' => 'Returned component under warranty [S/N: R750-TEST-SN99]',
            'Sales Tax Code' => 'Taxable Sales',
            'Qty' => 1,
            'Amount' => 25000.00,
            'Product Category' => 'Hardware',
            'applied_to_invoice' => 'AS-TEST-9901',
            'applied_amount' => 25000.00,
            'linked_txns' => [
                [
                    'txn_type' => 'Invoice',
                    'ref_number' => 'AS-TEST-9901',
                    'amount' => 25000.00
                ]
            ]
        ]
    ],
    'payments' => []
];

// Clean up any test rows first
$db->execute("DELETE FROM sales WHERE invoice_number IN ('AS-TEST-9901', 'CM-TEST-9901')");
$db->execute("DELETE FROM payments WHERE invoice_num = 'AS-TEST-9901'");
$db->execute("DELETE FROM customer_profiles WHERE customer_name = 'Acme Technologies Ltd'");

// Emulate sync processing logic from api/sync.php
$invoices = $payload['invoices'];
$creditMemos = $payload['credit_memos'];
$customers = $payload['customers'];

// 1. Process customer
foreach ($customers as $c) {
    $db->execute("INSERT INTO customer_profiles (customer_name, customer_type, is_vat_registered) VALUES (?, ?, ?)", [$c['name'], $c['customer_type'], $c['is_vat_registered']]);
}

// 2. Process invoice
$insertInvoiceStmt = "
    INSERT INTO sales (
        invoice_type, invoice_date, invoice_number, customer_name,
        item_description, tax_code, quantity, qb_amount,
        base_value, vat_component, applied_tax_rate, total_amount,
        product_category, subtotal, balance_remaining, is_paid, end_customer
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

foreach ($invoices as $inv) {
    $db->execute($insertInvoiceStmt, [
        $inv['Type'], $inv['Date'], $inv['Num'], $inv['Name'], $inv['Description'],
        $inv['Sales Tax Code'], $inv['Qty'], $inv['Amount'], $inv['Amount'], 0, 0,
        $inv['Amount'], $inv['Product Category'], $inv['subtotal'], $inv['balance_remaining'],
        $inv['is_paid'], $inv['end_customer']
    ]);
}

// 3. Process credit memo
$insertCreditMemoStmt = "
    INSERT INTO sales (
        invoice_type, invoice_date, invoice_number, customer_name,
        item_description, tax_code, quantity, qb_amount,
        base_value, vat_component, applied_tax_rate, total_amount,
        product_category, sales_rep_code, po_number, memo, qb_txn_id, vat_treatment,
        subtotal, sales_tax_total, sales_tax_rate, unit_price, end_customer
    ) VALUES ('Credit Memo', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

foreach ($creditMemos as $cm) {
    $signedAmount = -abs($cm['Amount']);
    $db->execute($insertCreditMemoStmt, [
        $cm['Date'], $cm['Num'], $cm['Name'], $cm['Description'], $cm['Sales Tax Code'],
        -1, $signedAmount, $signedAmount, 0, 0, $signedAmount, $cm['Product Category'],
        '', '', '', '', 'VAT_EXEMPT', $signedAmount, 0, 0, $cm['Amount'], ''
    ]);

    $appliedInv = $cm['applied_to_invoice'];
    $appliedAmt = $cm['applied_amount'];
    if (!empty($appliedInv) && $appliedAmt > 0) {
        $db->execute(
            "INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num, payment_method, memo) VALUES (?, ?, ?, ?, ?, 'Credit Memo', ?)",
            [$cm['Name'], $cm['Date'], $cm['Num'], $appliedAmt, $appliedInv, "Applied Credit Memo #" . $cm['Num']]
        );

        $db->execute(
            "UPDATE sales SET balance_remaining = MAX(0, balance_remaining - ?), applied_amount = applied_amount + ? WHERE invoice_number = ? AND customer_name = ?",
            [$appliedAmt, $appliedAmt, $appliedInv, $cm['Name']]
        );
    }
}

// Check database results
$invRow = $db->fetch("SELECT * FROM sales WHERE invoice_number = 'AS-TEST-9901'");
$cmRow = $db->fetch("SELECT * FROM sales WHERE invoice_number = 'CM-TEST-9901'");
$pmtRow = $db->fetch("SELECT * FROM payments WHERE invoice_num = 'AS-TEST-9901' AND payment_method = 'Credit Memo'");

echo "=== VERIFICATION RESULTS ===\n";
echo "Invoice Found: " . ($invRow ? "YES" : "NO") . "\n";
echo "  End Customer: " . ($invRow['end_customer'] ?? 'NONE') . "\n";
echo "  Balance Remaining: " . ($invRow['balance_remaining'] ?? 'N/A') . " (Expected: 75000)\n";
echo "Credit Memo Found: " . ($cmRow ? "YES" : "NO") . "\n";
echo "  Invoice Type: " . ($cmRow['invoice_type'] ?? 'N/A') . "\n";
echo "  Signed Amount: " . ($cmRow['qb_amount'] ?? 'N/A') . "\n";
echo "Credit Settlement Payment Found: " . ($pmtRow ? "YES" : "NO") . "\n";
echo "  Payment Method: " . ($pmtRow['payment_method'] ?? 'N/A') . "\n";
echo "  Amount: " . ($pmtRow['amount'] ?? 'N/A') . "\n";

// Clean up
$db->execute("DELETE FROM sales WHERE invoice_number IN ('AS-TEST-9901', 'CM-TEST-9901')");
$db->execute("DELETE FROM payments WHERE invoice_num = 'AS-TEST-9901'");
$db->execute("DELETE FROM customer_profiles WHERE customer_name = 'Acme Technologies Ltd'");
echo "Test cleanup complete.\n";
