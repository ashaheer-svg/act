<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== INSPECTING 2026 AS000xxx vs ASN000xxx INVOICES ===\n\n";

// Find 2026 AS invoices between AS000001 and AS000200
$sql = "
    SELECT 
        s.invoice_number as as_inv,
        'ASN' || SUBSTR(s.invoice_number, 3) as expected_asn,
        s.invoice_date,
        s.customer_name,
        SUM(s.total_amount) as as_total,
        COUNT(*) as as_lines
    FROM sales s
    WHERE s.invoice_number LIKE 'AS000%'
      AND strftime('%Y', s.invoice_date) = '2026'
    GROUP BY s.invoice_number
    ORDER BY s.invoice_number ASC
";

$asRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Total 2026 AS000xxx invoices found: " . count($asRows) . "\n\n";

$toDelete = [];
$noAsn = [];

foreach ($asRows as $row) {
    $asInv = $row['as_inv'];
    $expectedAsn = $row['expected_asn'];
    
    // Check if ASN exists in sales
    $asnCheck = $pdo->prepare("
        SELECT invoice_number, invoice_date, customer_name, SUM(total_amount) as asn_total, COUNT(*) as asn_lines
        FROM sales
        WHERE invoice_number = ?
        GROUP BY invoice_number
    ");
    $asnCheck->execute([$expectedAsn]);
    $asnRow = $asnCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($asnRow) {
        $toDelete[] = [
            'as_inv' => $asInv,
            'asn_inv' => $expectedAsn,
            'date' => $row['invoice_date'],
            'customer' => $row['customer_name'],
            'as_total' => $row['as_total'],
            'asn_total' => $asnRow['asn_total'],
            'total_match' => (abs($row['as_total'] - $asnRow['asn_total']) < 0.01) ? 'YES' : 'DIFF'
        ];
    } else {
        $noAsn[] = $row;
    }
}

echo "Found " . count($toDelete) . " invoices where AS000xxx has a corresponding ASN000xxx:\n";
echo sprintf("%-12s -> %-12s | %-10s | %-12s | %-12s | %-6s | %-25s\n", "AS Inv", "ASN Inv", "Date", "AS Total", "ASN Total", "Match?", "Customer");
echo str_repeat("-", 95) . "\n";

foreach ($toDelete as $td) {
    echo sprintf(
        "%-12s -> %-12s | %-10s | %-12s | %-12s | %-6s | %-25s\n",
        $td['as_inv'],
        $td['asn_inv'],
        $td['date'],
        number_format($td['as_total'], 0),
        number_format($td['asn_total'], 0),
        $td['total_match'],
        substr($td['customer'], 0, 25)
    );
}

if (!empty($noAsn)) {
    echo "\nFound " . count($noAsn) . " 2026 AS000xxx invoices that do NOT have a corresponding ASN:\n";
    foreach ($noAsn as $na) {
        echo "  {$na['as_inv']} ({$na['invoice_date']}) - {$na['customer_name']}: LKR " . number_format($na['as_total'], 2) . "\n";
    }
}
