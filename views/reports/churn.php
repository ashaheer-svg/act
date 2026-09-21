                <!-- 2. Account Churn & Reactivation Pipeline -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">At-Risk Accounts</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($churnTotal); ?></span>
                        <span class="metric-pill-sub">Inactive > 90 Days</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Historical Revenue at Risk</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($churnSummary['at_risk_historical_spend'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Cumulative Client Value</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Dormant (> 365d)</span>
                        <span class="metric-pill-val" style="color: #64748b;"><?php echo number_format($churnSummary['dormant_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Reactivation Campaign Candidates</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Critical Churn (180-365d)</span>
                        <span class="metric-pill-val" style="color: #ea580c;"><?php echo number_format($churnSummary['critical_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Immediate Outreach Window</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Average Inactivity</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo round($churnSummary['avg_inactivity_days'] ?? 0); ?> Days</span>
                        <span class="metric-pill-sub">Mean Portfolio Idle Days</span>
                    </div>
                </div>

                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="churn">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Inactivity Severity:</span>
                        <select name="risk" class="cmd-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($risk ?? 'all') === 'all' ? 'selected' : ''; ?>>All Inactive (≥ 90 Days)</option>
                            <option value="WATCHLIST" <?php echo ($risk ?? '') === 'WATCHLIST' ? 'selected' : ''; ?>>Watchlist (90–180 Days)</option>
                            <option value="CRITICAL" <?php echo ($risk ?? '') === 'CRITICAL' ? 'selected' : ''; ?>>Critical Alert (180–365 Days)</option>
                            <option value="DORMANT" <?php echo ($risk ?? '') === 'DORMANT' ? 'selected' : ''; ?>>Dormant (> 1 Year)</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Rep:</span>
                        <select name="rep_code" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Reps</option>
                            <?php foreach($salesReps as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['rep_code']); ?>" <?php echo ($rep_code ?? '') === $r['rep_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['rep_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th style="width: 80px;">Channel</th>
                                    <th style="width: 50px;">Rep</th>
                                    <th style="width: 90px;">Last Order Date</th>
                                    <th class="text-right" style="width: 80px;">Days Inactive</th>
                                    <th class="text-right" style="width: 60px;">Orders</th>
                                    <th class="text-right" style="width: 100px;">Base Spend</th>
                                    <th class="text-right" style="width: 90px;">VAT Paid</th>
                                    <th class="text-right" style="width: 110px;">Historical Gross</th>
                                    <th class="text-center" style="width: 90px;">Churn Risk</th>
                                    <th class="text-center" style="width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($churnData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No inactive accounts match the active filter criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($churnData as $row): 
                                        $riskColor = '#b91c1c';
                                        $riskBg = '#fee2e2';
                                        if ($row['churn_risk'] === 'CRITICAL') { $riskColor = '#c2410c'; $riskBg = '#ffedd5'; }
                                        elseif ($row['churn_risk'] === 'WATCHLIST') { $riskColor = '#b45309'; $riskBg = '#fef3c7'; }
                                        elseif ($row['churn_risk'] === 'DORMANT') { $riskColor = '#475569'; $riskBg = '#f1f5f9'; }
                                    ?>
                                    <tr onclick="window.location.href='customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>'">
                                        <td style="font-weight: 600; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['customer_type'] ?: 'Direct'); ?></span></td>
                                        <td><span style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['sales_rep'] ?: '—'); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['last_order_date']); ?></td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $riskColor; ?>;"><?php echo number_format($row['days_inactive']); ?>d</td>
                                        <td class="text-right dense-num"><?php echo number_format($row['historical_invoices']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['historical_base'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['historical_vat'], 0); ?></td>
                                        <td class="text-right dense-num-bold dense-num" style="color: var(--text-main);"><?php echo number_format($row['historical_gross'], 0); ?></td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $riskBg; ?>; color: <?php echo $riskColor; ?>; font-weight: 800;">
                                                <?php echo $row['churn_risk']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;">Engage</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=churn&risk=" . urlencode($risk ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $churnPages, $churnTotal, $limit, $baseUrl, 'accounts');
                    ?>
                </div>

