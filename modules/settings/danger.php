<?php
/**
 * Settings Module: System Maintenance, Data Sorting & Danger Zone
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$auth->requireAdmin();

// Handle POST actions for Danger Zone
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sort_all_data') {
        try {
            require_once __DIR__ . '/../../classes/DataSorter.php';
            $sorter = new DataSorter($db);
            $candidates = $db->fetchAll("SELECT DISTINCT invoice_number FROM sales WHERE total_amount > 0 ORDER BY invoice_date DESC");
            $processed = 0;
            $itemsTotal = 0;
            $hwTotal = 0;
            $subTotal = 0;
            foreach ($candidates as $c) {
                $sorted = $sorter->sortInvoice($c['invoice_number']);
                $res = $sorter->persistSortedData($sorted);
                $processed++;
                $itemsTotal += $res['items'];
                $hwTotal += $res['hardware_assets'];
                $subTotal += $res['subscriptions'];
            }
            $message = "Successfully sorted and normalized all {$processed} invoices! Extracted {$itemsTotal} clean catalog items, {$hwTotal} hardware assets (with serials & warranties), and {$subTotal} software/MA contracts.";
            $messageType = 'success';
            $db->logActivity($user['id'], 'ALL_DATA_SORTED', "Sorted {$processed} invoices into operational registries");
        } catch (Exception $e) {
            $message = 'Data Sorting Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'reset_database') {
        try {
            $db->resetPaymentData();
            $message = 'Payment and settlement data has been reset. All payments and collection speed metrics have been cleared. Sales records remain intact.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'PAYMENT_RESET', 'Payment data reset performed');
        } catch (Exception $e) {
            $message = 'Reset Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Data Sorter & Registry Statistics
try {
    $totalInvoicesCount = (int)($db->fetch("SELECT COUNT(DISTINCT invoice_number) as c FROM sales WHERE total_amount > 0")['c'] ?? 0);
    $sortedInvoicesCount = (int)($db->fetch("SELECT COUNT(DISTINCT invoice_number) as c FROM invoice_items")['c'] ?? 0);
    $totalItemsCount = (int)($db->fetch("SELECT COUNT(*) as c FROM invoice_items")['c'] ?? 0);
    $totalHardwareAssets = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets")['c'] ?? 0);
    $hardwareWithSerials = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")['c'] ?? 0);
    $activeWarranties = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE warranty_status = 'ACTIVE'")['c'] ?? 0);
    $totalSubscriptions = (int)($db->fetch("SELECT COUNT(*) as c FROM software_subscriptions")['c'] ?? 0);
    $activeSubscriptions = (int)($db->fetch("SELECT COUNT(*) as c FROM software_subscriptions WHERE renewal_status = 'ACTIVE' OR renewal_status = 'UPCOMING'")['c'] ?? 0);
} catch (Exception $e) {
    $totalInvoicesCount = $sortedInvoicesCount = $totalItemsCount = $totalHardwareAssets = $hardwareWithSerials = $activeWarranties = $totalSubscriptions = $activeSubscriptions = 0;
}
$sortProgressPct = $totalInvoicesCount > 0 ? round(($sortedInvoicesCount / $totalInvoicesCount) * 100, 1) : 0;
?>

<!-- Data Sorting & Commercial Asset Normalization Card -->
<div class="card" style="border-left: 4px solid #0284c7;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
        <div>
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                Data Sorting &amp; Commercial Asset Normalization
                <span style="font-size: 11px; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Deterministic + AI Engine</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 13px; margin-top: 5px; margin-bottom: 0;">
                Disaggregates multi-line QuickBooks raw invoice items into clean product lines, extracts discrete hardware serial numbers and warranty lifecycles, and normalizes Software and Maintenance Agreement (MA) recurring contracts.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Sort and normalize all <?php echo number_format($totalInvoicesCount); ?> historical sales invoices into operational registries? This will process in just a few seconds.');">
                <input type="hidden" name="action" value="sort_all_data">
                <button type="submit" class="btn btn-primary" style="background: #0284c7; border-color: #0369a1; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(2,132,199,0.25);">
                    <span>⚡</span> Sort &amp; Normalize All Invoices
                </button>
            </form>
        </div>
    </div>

    <!-- Metric Tiles -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px;">
            <span style="font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Invoices Processed</span>
            <div style="font-size: 24px; font-weight: 800; color: #0c4a6e; margin-top: 4px;">
                <?php echo number_format($sortedInvoicesCount); ?> <span style="font-size: 14px; font-weight: 500; color: #64748b;">/ <?php echo number_format($totalInvoicesCount); ?></span>
            </div>
            <div style="width: 100%; background: #e2e8f0; height: 6px; border-radius: 3px; margin-top: 10px; overflow: hidden;">
                <div style="width: <?php echo min(100, $sortProgressPct); ?>%; background: #0284c7; height: 100%; border-radius: 3px;"></div>
            </div>
            <small style="color: #0369a1; font-size: 11px; font-weight: 600; margin-top: 6px; display: block;"><?php echo $sortProgressPct; ?>% Normalized</small>
        </div>

        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 16px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Catalog Line Items</span>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-top: 4px;">
                <?php echo number_format($totalItemsCount); ?>
            </div>
            <small style="color: var(--text-muted); font-size: 11px; margin-top: 6px; display: block;">Clean items (excludes zero-value serial notes &amp; levies)</small>
        </div>

        <div style="background: #fdf4ff; border: 1px solid #f5d0fe; border-radius: 12px; padding: 16px;">
            <span style="font-size: 11px; font-weight: 700; color: #86198f; text-transform: uppercase; letter-spacing: 0.5px;">Hardware Assets</span>
            <div style="font-size: 24px; font-weight: 800; color: #701a75; margin-top: 4px;">
                <?php echo number_format($totalHardwareAssets); ?>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 6px; font-size: 11px; color: #86198f;">
                <span><strong><?php echo number_format($hardwareWithSerials); ?></strong> with Serials</span>
                <span>•</span>
                <span><strong><?php echo number_format($activeWarranties); ?></strong> Active</span>
            </div>
        </div>

        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 16px;">
            <span style="font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px;">Software &amp; MA Contracts</span>
            <div style="font-size: 24px; font-weight: 800; color: #064e3b; margin-top: 4px;">
                <?php echo number_format($totalSubscriptions); ?>
            </div>
            <small style="color: #047857; font-size: 11px; font-weight: 600; margin-top: 6px; display: block;">
                <strong><?php echo number_format($activeSubscriptions); ?></strong> Active / Upcoming Renewals
            </small>
        </div>
    </div>
</div>

<!-- Testing & Maintenance Card -->
<div class="card" style="margin-top: 30px; border: 2px dashed #fee2e2;">
    <h2 style="color: var(--danger);">Reset Payment Data</h2>
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
        <strong>Reset Payment Data:</strong> This will clear all records in the <code>payments</code> table and reset the <code>paid_date</code> and <code>days_to_pay</code> metrics in your sales records. 
        <br><br>
        <span style="color: var(--danger); font-weight: 700;">Note:</span> Your core sales invoices, line items, and customer profiles will NOT be affected.
    </p>
    <form method="POST" onsubmit="return confirm('RESET CONFIRMATION: This will clear ALL payment history and settlement metrics. Sales invoices will remain. Are you sure?');">
        <input type="hidden" name="action" value="reset_database">
        <button type="submit" class="btn btn-danger">Reset Payment &amp; Settlement Data</button>
    </form>
</div>
