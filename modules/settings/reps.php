<?php
/**
 * Settings Module: Sales Representatives Mapping
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$auth->requireAccounts();

// Handle POST actions for Sales Reps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_sales_rep') {
        try {
            $repCode = trim($_POST['rep_code'] ?? '');
            $repName = trim($_POST['rep_name'] ?? '');
            if (empty($repCode) || empty($repName)) {
                throw new Exception('Code and Name are required');
            }
            $db->addSalesRep($repCode, $repName);
            $message = 'Sales representative mapped successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'SALES_REP_ADDED', "Mapped $repCode to $repName");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_sales_rep') {
        try {
            $repId = trim($_POST['rep_id'] ?? '');
            $db->deleteSalesRep($repId);
            $message = 'Sales representative mapping deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'SALES_REP_DELETED', "Deleted rep mapping: $repId");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch sales reps
$salesReps = $db->getSalesReps();
?>

<!-- Sales Rep Mapping Card -->
<div class="card">
    <h2 style="display: flex; justify-content: space-between; align-items: center;">
        Sales Representative Mapping
        <span style="font-size: 11px; font-weight: 700; color: #7c3aed; background: #f5f3ff; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Team Management</span>
    </h2>
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Map ERP sales representative initials/codes to their full display names for executive reporting.</p>

    <div style="display: grid; grid-template-columns: 1fr <?php echo ($auth->isAdmin() || $auth->isAccounts()) ? '350px' : ''; ?>; gap: 40px;">
        <div>
            <table class="tax-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 10px;">Rep Code</th>
                        <th style="padding: 10px;">Display Name</th>
                        <th style="padding: 10px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salesReps)): ?>
                    <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No sales rep mappings found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($salesReps as $r): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px;"><code style="font-size: 13px; font-weight: 700; background: #f1f5f9; padding: 3px 8px; border-radius: 4px;"><?php echo htmlspecialchars($r['rep_code']); ?></code></td>
                            <td style="padding: 10px;"><strong><?php echo htmlspecialchars($r['rep_name']); ?></strong></td>
                            <td style="padding: 10px; text-align: right;">
                                <form method="POST" onsubmit="return confirm('Delete mapping for \'<?php echo addslashes($r['rep_code']); ?>\'?');" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_sales_rep">
                                    <input type="hidden" name="rep_id" value="<?php echo htmlspecialchars($r['rep_code']); ?>">
                                    <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 18px;" title="Delete">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid var(--border-color); height: fit-content;">
            <h3 style="font-size: 16px; margin-top: 0; margin-bottom: 20px;">Add New Mapping</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_sales_rep">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Sales Rep Code (from ERP)</label>
                    <input type="text" name="rep_code" class="form-control" placeholder="e.g. SR01" required style="font-size: 13px; text-transform: uppercase;">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Full Name / Display Name</label>
                    <input type="text" name="rep_name" class="form-control" placeholder="e.g. John Doe" required style="font-size: 13px;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Mapping</button>
            </form>
        </div>
    </div>
</div>
