<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config.php';
    $dbPath = defined('DATABASE_PATH') ? DATABASE_PATH : __DIR__ . '/../data/sales_bi.db';
    
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Add unit_cost and gross_profit to invoice_items and sales if missing
    $cols = [];
    $stmt = $pdo->query("PRAGMA table_info(invoice_items)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $row['name'];
    }

    $added = [];
    if (!in_array('unit_cost', $cols)) {
        $pdo->exec("ALTER TABLE invoice_items ADD COLUMN unit_cost DECIMAL(12,2)");
        $added[] = 'invoice_items.unit_cost';
    }
    if (!in_array('gross_profit', $cols)) {
        $pdo->exec("ALTER TABLE invoice_items ADD COLUMN gross_profit DECIMAL(12,2)");
        $added[] = 'invoice_items.gross_profit';
    }

    $sCols = [];
    $stmt = $pdo->query("PRAGMA table_info(sales)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sCols[] = $row['name'];
    }
    if (!in_array('unit_cost', $sCols)) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN unit_cost DECIMAL(12,2)");
        $added[] = 'sales.unit_cost';
    }

    // 2. Run Taxonomy Refinement on invoice_items
    $itemsStmt = $pdo->query("
        SELECT id, clean_product_name, product_type, brand, category, base_value
        FROM invoice_items
        WHERE brand = 'Other' OR brand IS NULL OR brand = ''
           OR category = 'Other / Unassigned' OR category IS NULL OR category = ''
    ");
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $updates = [];
    foreach ($items as $r) {
        $itemId = (int)$r['id'];
        $name = $r['clean_product_name'] ?? '';
        $brand = $r['brand'];
        $cat = $r['category'];
        $name_lower = strtolower($name);
        $new_brand = $brand;
        $new_cat = $cat;

        // Detect Brand if Other or missing
        if (in_array($new_brand, ['Other', '', null])) {
            if (preg_match('/(?:synology|diskstation|rackstation|plus\s*hdd|hat33|hat53|sat52|rx12|ds4|ds9|ds18|rs36|rc18)/i', $name_lower)) {
                $new_brand = 'Synology';
            } elseif (preg_match('/(?:seagate|ironwolf|barracuda|skyhawk|exos)/i', $name_lower)) {
                $new_brand = 'Seagate';
            } elseif (preg_match('/(?:western\s*digital|wd\s*red|wd\s*purple|ultrastar)/i', $name_lower)) {
                $new_brand = 'Western Digital';
            } elseif (stripos($name_lower, 'toshiba') !== false) {
                $new_brand = 'Toshiba';
            } elseif (stripos($name_lower, 'bdcom') !== false) {
                $new_brand = 'BDCOM';
            } elseif (preg_match('/(?:draytek|vigor)/i', $name_lower)) {
                $new_brand = 'DrayTek';
            } elseif (stripos($name_lower, 'acronis') !== false) {
                $new_brand = 'Acronis';
            } elseif (stripos($name_lower, 'eset') !== false) {
                $new_brand = 'ESET';
            } elseif (preg_match('/(?:maintenance|hospital\s*network|annual\s*maintenance|amc|service\s*charge|configuration)/i', $name_lower)) {
                $new_brand = 'Active Solutions';
            }
        }

        // Detect Category
        if (preg_match('/(?:hdd|hard\s*drive|enterprise\s*sata|plus\s*hdd|sata\s*hdd|nas\s*hard\s*drive)/i', $name_lower)) {
            $new_cat = 'Enterprise Hard Drives';
        } elseif (preg_match('/(?:nas|bay|diskstation|rackstation|expansion\s*unit|expansion|ds4|ds9|ds18|ds2|rs12|rs36|rc18)/i', $name_lower)) {
            $new_cat = 'NAS & Storage Servers';
        } elseif (preg_match('/(?:maintenance|amc|sla|annual\s*maintenance)/i', $name_lower)) {
            $new_cat = 'SLA & Maintenance Contracts';
        } elseif (preg_match('/(?:switch|router|access\s*point|poe)/i', $name_lower)) {
            $new_cat = 'Network Switches & Routers';
        } elseif (preg_match('/(?:service|installation|configuration|troubleshooting|cpanel|hosting)/i', $name_lower)) {
            $new_cat = 'Professional Services & Deployments';
        } elseif (preg_match('/(?:license|licence|subscription)/i', $name_lower)) {
            $new_cat = 'Software Licenses & SaaS';
        } elseif (preg_match('/(?:ram|cable|cord|adapter|rail\s*kit|bracket|transceiver)/i', $name_lower)) {
            $new_cat = 'Accessories & Peripherals';
        }

        if ($new_brand !== $brand || $new_cat !== $cat) {
            $updates[] = [$new_brand, $new_cat, $itemId];
        }
    }

    if (!empty($updates)) {
        $upStmt = $pdo->prepare("UPDATE invoice_items SET brand = ?, category = ? WHERE id = ?");
        foreach ($updates as $u) {
            $upStmt->execute($u);
        }
    }

    $remainingOther = $pdo->query("SELECT COUNT(*) FROM invoice_items WHERE category = 'Other / Unassigned'")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'added_columns' => $added,
        'refined_items_count' => count($updates),
        'remaining_unassigned_cat' => $remainingOther
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
