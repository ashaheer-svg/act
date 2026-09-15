<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

function lookupWarrantySerial($pdo, $query = '', $statusFilter = 'all', $limit = 20) {
    $cleanQuery = trim($query);
    $cleanLimit = max(1, min(100, (int)$limit));
    
    $params = [];
    $whereConditions = ["ha.serial_number IS NOT NULL AND ha.serial_number != '' AND ha.serial_number != 'UNASSIGNED'"];
    
    if (!empty($cleanQuery)) {
        $searchWild = '%' . $cleanQuery . '%';
        $whereConditions[] = "(ha.serial_number LIKE ? OR ha.parent_serial_number LIKE ? OR ha.product_name LIKE ? OR ha.model_sku LIKE ? OR ha.customer_name LIKE ? OR ha.invoice_number LIKE ?)";
        $params = array_merge($params, [$searchWild, $searchWild, $searchWild, $searchWild, $searchWild, $searchWild]);
    }
    
    $whereSql = "WHERE " . implode(" AND ", $whereConditions);
    
    $sql = "
        SELECT 
            ha.id,
            ha.invoice_number,
            ha.invoice_item_id,
            ha.customer_name,
            ha.product_name,
            ha.brand,
            ha.model_sku,
            ha.serial_number,
            ha.parent_serial_number,
            ha.warranty_type,
            ha.warranty_months,
            ha.warranty_start_date,
            ha.warranty_expiry_date,
            ha.warranty_status as recorded_status,
            ha.notes,
            ha.is_rental,
            s.invoice_date,
            s.sales_rep_code,
            s.total_amount as invoice_item_amount,
            s.paid_date
        FROM hardware_assets ha
        LEFT JOIN sales s ON ha.invoice_number = s.invoice_number AND (ha.invoice_item_id IS NULL OR ha.invoice_item_id = s.id)
        $whereSql
        GROUP BY ha.serial_number, ha.invoice_number
        ORDER BY ha.warranty_expiry_date DESC, ha.warranty_start_date DESC
        LIMIT $cleanLimit
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $today = date('Y-m-d');
    $todayTs = strtotime($today);
    $results = [];
    
    foreach ($assets as $asset) {
        $serial = $asset['serial_number'];
        $startDate = $asset['warranty_start_date'] ?: $asset['invoice_date'];
        $expiryDate = $asset['warranty_expiry_date'];
        
        if (empty($expiryDate) && !empty($startDate) && !empty($asset['warranty_months'])) {
            $expiryDate = date('Y-m-d', strtotime('+' . (int)$asset['warranty_months'] . ' months', strtotime($startDate)));
        }
        
        $status = 'UNKNOWN';
        $daysDiff = null;
        $statusBadgeClass = 'secondary';
        $statusLabel = 'Status Unknown';
        $progressPct = 100;
        
        if (!empty($expiryDate)) {
            $expiryTs = strtotime($expiryDate);
            $daysDiff = (int)ceil(($expiryTs - $todayTs) / 86400);
            
            if ($daysDiff > 60) {
                $status = 'ACTIVE';
                $statusBadgeClass = 'success';
                $statusLabel = 'Active (' . number_format($daysDiff) . ' days remaining)';
            } elseif ($daysDiff >= 0) {
                $status = 'EXPIRING_SOON';
                $statusBadgeClass = 'warning';
                $statusLabel = 'Expiring Soon (' . number_format($daysDiff) . ' days left)';
            } else {
                $status = 'EXPIRED';
                $statusBadgeClass = 'danger';
                $absDays = abs($daysDiff);
                $statusLabel = 'Expired (' . number_format($absDays) . ' days ago)';
            }
            
            if (!empty($startDate)) {
                $startTs = strtotime($startDate);
                $totalSpan = max(1, $expiryTs - $startTs);
                $elapsed = max(0, $todayTs - $startTs);
                $progressPct = max(0, min(100, round(($elapsed / $totalSpan) * 100)));
            }
        }
        
        if ($statusFilter !== 'all') {
            if ($statusFilter === 'active' && $status !== 'ACTIVE') continue;
            if ($statusFilter === 'expiring_soon' && $status !== 'EXPIRING_SOON') continue;
            if ($statusFilter === 'expired' && $status !== 'EXPIRED') continue;
        }
        
        // Find all invoices
        $invStmt = $pdo->prepare("
            SELECT 
                s.invoice_number,
                MIN(s.invoice_date) as invoice_date,
                s.customer_name,
                MAX(CASE WHEN s.item_description LIKE ? THEN s.item_description ELSE NULL END) as matching_line_desc,
                SUM(s.total_amount) as total_invoice_amount,
                MAX(s.paid_date) as paid_date,
                MAX(s.days_to_pay) as days_to_pay,
                MAX(s.sales_rep_code) as sales_rep_code
            FROM sales s
            WHERE s.invoice_number = ? OR s.item_description LIKE ?
            GROUP BY s.invoice_number
            ORDER BY invoice_date DESC
        ");
        $invStmt->execute(['%' . $serial . '%', $asset['invoice_number'], '%' . $serial . '%']);
        $invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        
        // Maintenance contracts
        $maStmt = $pdo->prepare("
            SELECT 
                ss.id,
                ss.invoice_number,
                ss.customer_name,
                ss.software_name as contract_title,
                ss.edition_tier,
                ss.license_seats,
                ss.period_start_date,
                ss.period_end_date,
                ss.term_months,
                ss.renewal_status,
                ss.renewal_opportunity_value as contract_value
            FROM software_subscriptions ss
            WHERE ss.customer_name = ?
              AND (
                ss.edition_tier LIKE '%Maintenance%' 
                OR ss.software_name LIKE '%Maintenance%' 
                OR ss.software_name LIKE '%SLA%' 
                OR ss.software_name LIKE '%Support%'
              )
            ORDER BY ss.period_end_date DESC
        ");
        $maStmt->execute([$asset['customer_name']]);
        $maContracts = $maStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($maContracts as &$mc) {
            $mcEnd = $mc['period_end_date'];
            if (!empty($mcEnd)) {
                $mcEndTs = strtotime($mcEnd);
                $mcDays = (int)ceil(($mcEndTs - $todayTs) / 86400);
                if ($mcDays >= 0) {
                    $mc['status_label'] = 'ACTIVE • ' . $mcDays . ' days left';
                    $mc['status_class'] = 'success';
                } else {
                    $mc['status_label'] = 'EXPIRED • ' . abs($mcDays) . ' days ago';
                    $mc['status_class'] = 'secondary';
                }
            } else {
                $mc['status_label'] = $mc['renewal_status'] ?: 'RECORDED';
                $mc['status_class'] = 'info';
            }
        }
        
        if ($statusFilter === 'has_maintenance' && empty($maContracts)) {
            continue;
        }
        
        $asset['computed_status'] = $status;
        $asset['days_diff'] = $daysDiff;
        $asset['status_badge_class'] = $statusBadgeClass;
        $asset['status_label'] = $statusLabel;
        $asset['progress_pct'] = $progressPct;
        $asset['computed_expiry_date'] = $expiryDate;
        $asset['invoices'] = $invoices;
        $asset['maintenance_contracts'] = $maContracts;
        
        $results[] = $asset;
    }
    
    return $results;
}

$res = lookupWarrantySerial($pdo, '20C0SKRCXAQ44');
echo "Found " . count($res) . " assets for '20C0SKRCXAQ44':\n";
foreach ($res as $r) {
    echo "- S/N: " . $r['serial_number'] . " | Product: " . $r['product_name'] . " | Expiry: " . $r['computed_expiry_date'] . " (" . $r['status_label'] . ")\n";
    echo "  Invoices (" . count($r['invoices']) . "): " . implode(', ', array_column($r['invoices'], 'invoice_number')) . "\n";
    echo "  Maintenance contracts: " . count($r['maintenance_contracts']) . "\n";
    foreach ($r['maintenance_contracts'] as $mc) {
        echo "    * " . $mc['contract_title'] . " (" . $mc['edition_tier'] . ") Inv: " . $mc['invoice_number'] . " | " . $mc['status_label'] . " | LKR " . number_format($mc['contract_value'], 2) . "\n";
    }
}
