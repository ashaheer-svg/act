                <!-- 7. Working Capital & DSO Collection Velocity -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Accounts Billed</span>
                        <span class="metric-pill-val"><?php echo number_format($dsoSummary['total_accounts'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Year: <?php echo htmlspecialchars($year === 'all' ? 'All Time (2009–2026)' : $year); ?></span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Invoiced</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($dsoSummary['grand_gross_billed'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Total Working Capital Demand</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Realized Collections</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($dsoSummary['grand_collected'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Cash Converted to Bank</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Outstanding Receivables</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo htmlspecialchars($currency) . number_format($dsoSummary['grand_outstanding'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Working Capital Trapped</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Average DSO</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo $dsoSummary['grand_avg_dso'] ?? 0; ?> Days</span>
                        <span class="metric-pill-sub">Mean Days Sales Outstanding</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Client / Enterprise Account</th>
                                    <th style="width: 80px;">Channel</th>
                                    <th style="width: 50px;">Rep</th>
                                    <th class="text-right" style="width: 55px;">Invoices</th>
                                    <th class="text-right" style="width: 105px;">Gross Billed</th>
                                    <th class="text-right" style="width: 105px;">Cash Collected</th>
                                    <th class="text-right" style="width: 105px;">Outstanding</th>
                                    <th class="text-right" style="width: 75px;">Collection %</th>
                                    <th class="text-right" style="width: 75px;">Avg DSO</th>
                                    <th class="text-right" style="width: 75px;">Max Delay</th>
                                    <th class="text-center" style="width: 80px;">Liquidity Risk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dsoData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No accounts found for the active filter.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dsoData as $row): 
                                        $dsoColor = '#15803d'; $dsoBg = '#ecfdf5';
                                        if ($row['risk_badge'] === 'CRITICAL') { $dsoColor = '#b91c1c'; $dsoBg = '#fee2e2'; }
                                        elseif ($row['risk_badge'] === 'DELAYED') { $dsoColor = '#b45309'; $dsoBg = '#fef3c7'; }
                                        elseif ($row['risk_badge'] === 'NORMAL') { $dsoColor = '#4338ca'; $dsoBg = '#e0e7ff'; }
                                    ?>
                                    <tr onclick="window.location.href='customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>'">
                                        <td style="font-weight: 600;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['customer_type'] ?: 'Direct'); ?></span></td>
                                        <td><span style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['sales_rep'] ?: '—'); ?></span></td>
                                        <td class="text-right dense-num"><?php echo $row['invoice_count']; ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['gross_billed'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #15803d;"><?php echo number_format($row['collected_amount'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $row['outstanding_amount'] > 0 ? '#b91c1c' : '#64748b'; ?>;"><?php echo number_format($row['outstanding_amount'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo $row['collection_rate_pct']; ?>%</td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $dsoColor; ?>;"><?php echo $row['avg_dso_days']; ?>d</td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo $row['max_dso_days']; ?>d</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $dsoBg; ?>; color: <?php echo $dsoColor; ?>; font-weight: 800;">
                                                <?php echo $row['risk_badge']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

