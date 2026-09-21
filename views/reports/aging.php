                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2>Aging & Collections Analysis</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Monitor collection momentum and identify high-risk outstanding balances.</p>
                        </div>
                        
                        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
                            <input type="hidden" name="type" value="aging">
                            
                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Aging Bracket</span>
                                <select name="bracket" class="filter-select" style="min-width: 140px;" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($bracket ?? '') == 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="30" <?php echo ($bracket ?? '') == '30' ? 'selected' : ''; ?>>0-30 Days</option>
                                    <option value="60" <?php echo ($bracket ?? '') == '60' ? 'selected' : ''; ?>>31-60 Days</option>
                                    <option value="90" <?php echo ($bracket ?? '') == '90' ? 'selected' : ''; ?>>61-90 Days</option>
                                    <option value="180" <?php echo ($bracket ?? '') == '180' ? 'selected' : ''; ?>>91-180 Days</option>
                                    <option value="365" <?php echo ($bracket ?? '') == '365' ? 'selected' : ''; ?>>181-365 Days</option>
                                    <option value="old" <?php echo ($bracket ?? '') == 'old' ? 'selected' : ''; ?>>Over 1 Year</option>
                                </select>
                            </div>

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Status</span>
                                <select name="status" class="filter-select" style="min-width: 120px;" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($status ?? '') == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="unpaid" <?php echo ($status ?? '') == 'unpaid' ? 'selected' : ''; ?>>Unpaid Only</option>
                                    <option value="paid" <?php echo ($status ?? '') == 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                </select>
                            </div>

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Sort By</span>
                                <select name="sort" class="filter-select" style="min-width: 140px;" onchange="this.form.submit()">
                                    <option value="invoice_number" <?php echo ($sortBy ?? '') == 'invoice_number' ? 'selected' : ''; ?>>Invoice #</option>
                                    <option value="customer_name" <?php echo ($sortBy ?? '') == 'customer_name' ? 'selected' : ''; ?>>Customer Name</option>
                                    <option value="aging" <?php echo ($sortBy ?? '') == 'aging' ? 'selected' : ''; ?>>Aging Severity</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer Name</th>
                                <th>Invoice Date</th>
                                <th>Status</th>
                                <th class="text-right">Days</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($agingData)): ?>
                                <tr><td colspan="7" style="text-align: center; padding: 50px; color: var(--text-muted);">No invoices found for the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach($agingData as $row): 
                                    $isPaid = $row['paid_date'] !== null;
                                    $days = $row['aging_days'];
                                    
                                    $agingColor = '#10b981';
                                    if (!$isPaid) {
                                        if ($days > 180) $agingColor = '#ef4444';
                                        else if ($days > 90) $agingColor = '#fb923c';
                                        else if ($days > 60) $agingColor = '#f59e0b';
                                        else $agingColor = '#6366f1';
                                    }
                                ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td style="color: var(--text-muted);"><?php echo date('M d, Y', strtotime($row['invoice_date'])); ?></td>
                                    <td>
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: <?php echo $isPaid ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $isPaid ? '#15803d' : '#991b1b'; ?>;">
                                            <?php echo $isPaid ? 'Paid' : 'Unpaid'; ?>
                                        </span>
                                    </td>
                                    <td class="text-right" style="font-weight: 800; color: <?php echo $agingColor; ?>;">
                                        <?php echo $days; ?>
                                    </td>
                                    <td class="text-right price-tag">
                                        <?php echo htmlspecialchars($currency) . number_format($row['total_amount'], 0); ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" class="btn-view" style="font-size: 10px; padding: 6px 12px; text-decoration: none;">Strategic Dossier</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

