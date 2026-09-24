<?php
/**
 * View: Unlinked Payments Audit Ledger
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH')) {
    exit('Direct access not permitted.');
}
?>

<!-- Filter Toolbar -->
<div class="report-filters" style="margin-bottom: 20px;">
    <form method="GET" action="reports.php" class="filter-form" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="type" value="unlinked_payments">

        <div class="form-group" style="margin: 0; min-width: 260px;">
            <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Search Customer or Ref #</label>
            <div style="position: relative;">
                <i class="icon-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer name, cheque #, memo..." class="form-control" style="padding-left: 32px; height: 36px; font-size: 13px; border-radius: 6px; border: 1px solid var(--border-color); width: 100%;">
            </div>
        </div>

        <div class="form-group" style="margin: 0; min-width: 140px;">
            <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Calendar Year</label>
            <select name="year" class="form-control" style="height: 36px; font-size: 13px; border-radius: 6px; border: 1px solid var(--border-color);">
                <option value="all" <?php echo $filterYear === 'all' ? 'selected' : ''; ?>>All Years</option>
                <?php foreach ($unlinkedYears as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo (string)$filterYear === (string)$yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="height: 36px; padding: 0 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; background: #2563eb; color: #fff; border: none; cursor: pointer;">
                <i class="icon-filter"></i> Filter
            </button>
            <?php if (!empty($search) || ($filterYear !== 'all')): ?>
                <a href="reports.php?type=unlinked_payments" class="btn btn-secondary" style="height: 36px; padding: 0 12px; font-size: 13px; display: inline-flex; align-items: center; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-main); text-decoration: none;">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- KPI Summary Cards -->
<div class="summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 24px;">
    <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Unallocated Receipts</div>
                <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                    <?php echo number_format($unlinkedSummary['total_count'] ?? 0); ?>
                </div>
            </div>
            <div style="background: #eff6ff; color: #2563eb; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="icon-file-text"></i>
            </div>
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 8px;">
            Receipts without commercial invoice link
        </div>
    </div>

    <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Total Unallocated Cash</div>
                <div style="font-size: 24px; font-weight: 800; color: #b45309; margin-top: 4px; font-family: monospace;">
                    LKR <?php echo number_format(round($unlinkedSummary['total_amount'] ?? 0)); ?>
                </div>
            </div>
            <div style="background: #fffbeb; color: #d97706; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="icon-dollar-sign"></i>
            </div>
        </div>
        <div style="font-size: 11.5px; color: #92400e; margin-top: 8px;">
            Cash collected into bank accounts
        </div>
    </div>

    <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Accounts Impacted</div>
                <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                    <?php echo number_format($unlinkedSummary['distinct_customers'] ?? 0); ?>
                </div>
            </div>
            <div style="background: #f5f3ff; color: #7c3aed; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="icon-users"></i>
            </div>
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 8px;">
            Unique corporate client profiles
        </div>
    </div>

    <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Date Range Span</div>
                <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 6px;">
                    <?php echo ($unlinkedSummary['earliest_date'] ?? 'N/A') . ' &rarr; ' . ($unlinkedSummary['latest_date'] ?? 'N/A'); ?>
                </div>
            </div>
            <div style="background: #f0fdf4; color: #16a34a; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="icon-calendar"></i>
            </div>
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 8px;">
            Historical ledger timeframe
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="table-responsive" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px; margin: 0;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 11px; font-weight: 700;">
                <th style="padding: 12px 16px; width: 80px;">ID</th>
                <th style="padding: 12px 16px; width: 110px;">Payment Date</th>
                <th style="padding: 12px 16px;">Customer Name</th>
                <th style="padding: 12px 16px;">Cheque / Reference #</th>
                <th style="padding: 12px 16px;">Method / Deposit</th>
                <th style="padding: 12px 16px;">Memo / Notes</th>
                <th style="padding: 12px 16px; text-align: right;">Amount (LKR)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($unlinkedData)): ?>
                <tr>
                    <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 13px;">
                        <i class="icon-check-circle" style="font-size: 28px; color: #10b981; display: block; margin-bottom: 8px;"></i>
                        No unlinked payments found matching criteria.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($unlinkedData as $row): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 16px; font-family: monospace; color: var(--text-muted); font-weight: 600;">
                            #<?php echo $row['id']; ?>
                        </td>
                        <td style="padding: 10px 16px; font-weight: 600; color: #334155;">
                            <?php echo htmlspecialchars($row['payment_date'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 10px 16px;">
                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: #2563eb; text-decoration: none; font-weight: 700;" title="View Customer Dossier">
                                <?php echo htmlspecialchars($row['customer_name']); ?>
                            </a>
                        </td>
                        <td style="padding: 10px 16px;">
                            <?php if (!empty($row['reference_num'])): ?>
                                <span class="dense-badge" style="background: #f1f5f9; color: #334155; font-family: monospace; font-weight: 600;">
                                    <?php echo htmlspecialchars($row['reference_num']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 16px; color: var(--text-muted);">
                            <?php 
                            $methodParts = array_filter([$row['payment_method'] ?? '', $row['deposit_account'] ?? '']);
                            echo !empty($methodParts) ? htmlspecialchars(implode(' &bull; ', $methodParts)) : '<span style="color: #94a3b8;">&mdash;</span>';
                            ?>
                        </td>
                        <td style="padding: 10px 16px; color: var(--text-muted); font-size: 11.5px; max-width: 250px;">
                            <?php echo !empty($row['memo']) ? htmlspecialchars($row['memo']) : '<span style="color: #cbd5e1;">&mdash;</span>'; ?>
                        </td>
                        <td style="padding: 10px 16px; text-align: right; font-weight: 800; font-family: monospace; font-size: 13px; color: #0f172a;">
                            LKR <?php echo number_format($row['amount'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($unlinkedData)): ?>
            <tfoot>
                <tr style="background: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 800;">
                    <td colspan="6" style="padding: 12px 16px; color: #0f172a;">
                        Total Displayed (Page <?php echo $p; ?> of <?php echo $unlinkedPages; ?> &bull; <?php echo count($unlinkedData); ?> of <?php echo $unlinkedTotal; ?> records)
                    </td>
                    <td style="padding: 12px 16px; text-align: right; color: #0f172a; font-family: monospace; font-size: 14px;">
                        LKR <?php echo number_format(array_sum(array_column($unlinkedData, 'amount')), 2); ?>
                    </td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<!-- Pagination -->
<?php if ($unlinkedPages > 1): ?>
    <div style="margin-top: 20px;">
        <?php renderReportPagination($p, $unlinkedPages, $unlinkedTotal, $limit, $isAll, [
            'type' => 'unlinked_payments',
            'search' => $search,
            'year' => $filterYear
        ]); ?>
    </div>
<?php endif; ?>
