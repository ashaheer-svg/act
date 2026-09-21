<?php
/**
 * Settings Module: Multi-Period VAT Regimes & Tax Rules
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$auth->requireAccounts();

// Handle POST actions for Tax & VAT Rules
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_tax_rule') {
        $auth->requireAdmin();
        try {
            $db->saveTaxRule([
                'tax_name' => $_POST['tax_name'] ?? 'VAT Rule',
                'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
                'effective_from' => !empty($_POST['effective_from']) ? $_POST['effective_from'] : null,
                'effective_to' => !empty($_POST['effective_to']) ? $_POST['effective_to'] : null,
                'invoice_range_start' => !empty($_POST['invoice_range_start']) ? trim($_POST['invoice_range_start']) : null,
                'invoice_range_end' => !empty($_POST['invoice_range_end']) ? trim($_POST['invoice_range_end']) : null,
                'is_inclusive_default' => isset($_POST['is_inclusive_default']) ? intval($_POST['is_inclusive_default']) : 1,
                'notes' => trim($_POST['notes'] ?? '')
            ]);
            $message = 'Tax rule saved successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'TAX_RULE_SAVED', "Saved tax rule: " . ($_POST['tax_name'] ?? ''));
        } catch (Exception $e) {
            $message = 'Error saving tax rule: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_tax_rule') {
        $auth->requireAdmin();
        try {
            $ruleId = (int)($_POST['rule_id'] ?? 0);
            $db->deleteTaxRule($ruleId);
            $message = 'Tax rule deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'TAX_RULE_DELETED', "Deleted tax rule ID: $ruleId");
        } catch (Exception $e) {
            $message = 'Error deleting tax rule: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'recalculate_historical_vat') {
        $auth->requireAdmin();
        try {
            $recalculated = $db->recalculateHistoricalVat();
            $message = "Successfully recalculated VAT and inclusivity across $recalculated sales records!";
            $messageType = 'success';
            $db->logActivity($user['id'], 'VAT_RECALCULATED', "Recalculated VAT on $recalculated records");
        } catch (Exception $e) {
            $message = 'Recalculation error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch active tax rules
$taxRules = $db->getTaxRules();
?>

<!-- Multi-Period VAT Regimes & Invoice Sequences Card -->
<div class="card" style="border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
        <div>
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                Multi-Period VAT Regimes &amp; Invoice Sequences
                <span style="font-size: 11px; font-weight: 700; color: #166534; background: #dcfce7; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Deterministic Engine</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 13px; margin-top: 5px; margin-bottom: 0;">
                Matches invoices by <strong>Invoice Number Sequence</strong> (highest precision), falling back to <strong>Date Ranges</strong>. Automatically calculates base amounts and VAT components according to historical tax laws.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="vat_review.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px; text-decoration: none; border: 1px solid #cbd5e1; background: #ffffff; color: #1e293b; font-weight: 600; padding: 8px 14px; border-radius: 6px;">
                <span>🔍</span> Review &amp; Switch Invoices
            </a>
            <?php if ($auth->isAdmin()): ?>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Recalculate VAT across all historical sales records using these active sequence rules? This runs in < 1 second.');">
                <input type="hidden" name="action" value="recalculate_historical_vat">
                <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.2);">
                    <span>⚡</span> Recalculate Historical VAT
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 16px; margin-bottom: 25px; font-size: 13px; color: #1e40af;">
        <strong>Inclusivity Rule:</strong> For any taxable regime (&gt;0%), invoices without an explicit separate VAT line are automatically calculated as <strong>VAT-inclusive</strong> (<code>base = amount / (1 + rate)</code>, <code>vat = amount - base</code>).
    </div>

    <div style="display: grid; grid-template-columns: 1fr <?php echo $auth->isAdmin() ? '340px' : ''; ?>; gap: 30px;">
        <div style="overflow-x: auto;">
            <table class="tax-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 10px 8px;">Regime / Name</th>
                        <th style="padding: 10px 8px;">Rate</th>
                        <th style="padding: 10px 8px;">Invoice Sequence</th>
                        <th style="padding: 10px 8px;">Effective Dates</th>
                        <th style="padding: 10px 8px;">Default Mode</th>
                        <?php if ($auth->isAdmin()): ?>
                        <th style="padding: 10px 8px; text-align: right;">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($taxRules)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No tax rules defined. Click 'Recalculate Historical VAT' to seed default sequences.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($taxRules as $rule): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 8px;">
                                <strong><?php echo htmlspecialchars($rule['tax_name']); ?></strong>
                                <?php if (!empty($rule['notes'])): ?>
                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($rule['notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 8px;">
                                <?php if ($rule['tax_rate'] > 0): ?>
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; background: #dbeafe; color: #1e40af;">
                                    <?php echo ($rule['tax_rate'] * 100); ?>%
                                </span>
                                <?php else: ?>
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; background: #f1f5f9; color: #475569;">
                                    0% (Exempt)
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 8px; font-family: monospace; font-size: 12px;">
                                <?php if (!empty($rule['invoice_range_start'])): ?>
                                    <span style="background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <?php echo htmlspecialchars($rule['invoice_range_start']); ?> &rarr; <?php echo htmlspecialchars($rule['invoice_range_end'] ?: 'Open'); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Any / Date-based</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 8px; font-size: 12px; color: var(--text-muted);">
                                <?php 
                                if (!empty($rule['effective_from']) && !empty($rule['effective_to'])) {
                                    echo date('Y-m-d', strtotime($rule['effective_from'])) . ' to ' . date('Y-m-d', strtotime($rule['effective_to']));
                                } elseif (!empty($rule['effective_from'])) {
                                    echo 'From ' . date('Y-m-d', strtotime($rule['effective_from']));
                                } else {
                                    echo 'All Dates';
                                }
                                ?>
                            </td>
                            <td style="padding: 10px 8px; font-size: 11px;">
                                <?php if ($rule['tax_rate'] == 0): ?>
                                    <span style="color: #64748b;">Exempt</span>
                                <?php elseif (!empty($rule['is_inclusive_default'])): ?>
                                    <span style="color: #059669; font-weight: 600;">VAT Inclusive</span>
                                <?php else: ?>
                                    <span style="color: #d97706; font-weight: 600;">Pre-Tax Base</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($auth->isAdmin()): ?>
                            <td style="padding: 10px 8px; text-align: right;">
                                <form method="POST" onsubmit="return confirm('Delete tax rule \'<?php echo addslashes($rule['tax_name']); ?>\'?');" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_tax_rule">
                                    <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                    <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 16px; padding: 4px;" title="Delete Rule">🗑️</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($auth->isAdmin()): ?>
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); height: fit-content;">
            <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 15px; color: var(--text-main);">Add Custom Sequence Rule</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_tax_rule">
                
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 12px;">Regime Description</label>
                    <input type="text" name="tax_name" class="form-control" placeholder="e.g. 15% VAT Regime" required style="font-size: 13px;">
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 12px;">Tax Rate (Decimal e.g. 0.18 for 18%)</label>
                    <input type="number" step="0.001" name="tax_rate" class="form-control" value="0.180" required style="font-size: 13px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Invoice Start</label>
                        <input type="text" name="invoice_range_start" class="form-control" placeholder="AS010021" style="font-size: 12px; font-family: monospace;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Invoice End</label>
                        <input type="text" name="invoice_range_end" class="form-control" placeholder="AS011260" style="font-size: 12px; font-family: monospace;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Date From (Opt)</label>
                        <input type="date" name="effective_from" class="form-control" style="font-size: 12px;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Date To (Opt)</label>
                        <input type="date" name="effective_to" class="form-control" style="font-size: 12px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 12px;">Default Treatment</label>
                    <select name="is_inclusive_default" class="form-control" style="font-size: 13px;">
                        <option value="1">VAT-Inclusive (Amount contains VAT)</option>
                        <option value="0">VAT-Exclusive (Pre-tax Base Amount)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-size: 12px;">Notes / Reference</label>
                    <input type="text" name="notes" class="form-control" placeholder="Statutory gazette or reason" style="font-size: 12px;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 13px;">Save Tax Rule</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
