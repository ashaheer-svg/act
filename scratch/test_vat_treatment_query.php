<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$testSql = "
    SELECT 
        s.invoice_number,
        SUM(s.base_value) as base_net,
        SUM(s.vat_component) as vat,
        SUM(s.total_amount) as gross,
        p.is_vat_registered,
        COALESCE(
            (SELECT s2.vat_treatment FROM sales s2 WHERE s2.invoice_number = s.invoice_number AND s2.vat_treatment != 'VAT_EXEMPT' AND s2.total_amount > 0 LIMIT 1),
            (SELECT ii.vat_treatment FROM invoice_items ii WHERE ii.invoice_number = s.invoice_number AND ii.vat_treatment != 'VAT_EXEMPT' AND ii.total_amount > 0 LIMIT 1),
            CASE 
                WHEN SUM(s.vat_component) > 0 AND p.is_vat_registered = 1 THEN 'PLUS_VAT'
                WHEN SUM(s.vat_component) > 0 THEN 'VAT_INCLUSIVE'
                ELSE 'VAT_EXEMPT'
            END
        ) as resolved_treatment
    FROM sales s
    LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
    WHERE s.invoice_number IN ('ASN000111', 'ASN000110', 'ASN000109', 'ASN000108', 'ASN000107', 'ASN000106', 'ASN000105', 'ASN000104')
    GROUP BY s.invoice_number
    ORDER BY s.invoice_number DESC
";

$rows = $pdo->query($testSql)->fetchAll(PDO::FETCH_ASSOC);
echo sprintf("%-12s | %-10s | %-10s | %-10s | %-15s\n", "Invoice", "Base", "VAT", "Gross", "Resolved Treat");
echo str_repeat("-", 65) . "\n";
foreach ($rows as $r) {
    echo sprintf(
        "%-12s | %-10s | %-10s | %-10s | %-15s\n",
        $r['invoice_number'],
        number_format($r['base_net'], 0),
        number_format($r['vat'], 0),
        number_format($r['gross'], 0),
        $r['resolved_treatment']
    );
}
