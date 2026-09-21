                <!-- 5. Rental Fleet Utilization & Commercial Yield -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Rental Deployments</span>
                        <span class="metric-pill-val"><?php echo number_format($rentalSummary['total_deployments'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo number_format($rentalSummary['unique_clients'] ?? 0); ?> Unique Clients</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Rental Revenue</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($rentalSummary['total_rental_volume'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Cumulative Billed Yield</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Monthly Run-Rate (MRR)</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($rentalSummary['active_mrr'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Active Base Run-Rate</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Deployments</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($rentalSummary['active_deployments'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Billed ≤ 35 Days</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Delinquent Rentals</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($rentalSummary['delinquent_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Asset Recovery Candidates</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 75px;">Date</th>
                                    <th style="width: 90px;">Invoice #</th>
                                    <th>Client / Lessee</th>
                                    <th>Equipment / Rental Description</th>
                                    <th style="width: 80px;">Brand</th>
                                    <th class="text-right" style="width: 45px;">Qty</th>
                                    <th class="text-right" style="width: 85px;">Monthly Base</th>
                                    <th class="text-right" style="width: 75px;">18% VAT</th>
                                    <th class="text-right" style="width: 95px;">Gross Amount</th>
                                    <th class="text-right" style="width: 75px;">Last Billed</th>
                                    <th class="text-center" style="width: 80px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rentalData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No rental records found matching criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rentalData as $row): 
                                        $rColor = '#15803d'; $rBg = '#ecfdf5';
                                        if ($row['rental_status'] === 'SUSPENDED') { $rColor = '#b91c1c'; $rBg = '#fee2e2'; }
                                        elseif ($row['rental_status'] === 'OVERDUE') { $rColor = '#b45309'; $rBg = '#fef3c7'; }
                                    ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                                        <td><span class="dense-doc-num"><?php echo htmlspecialchars($row['invoice_number']); ?></span></td>
                                        <td style="font-weight: 600;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['clean_product_name']); ?>">
                                            <?php echo htmlspecialchars($row['clean_product_name']); ?>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #334155;"><?php echo htmlspecialchars($row['brand_category']); ?></span></td>
                                        <td class="text-right dense-num"><?php echo $row['quantity']; ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['base_value'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['vat_component'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['total_amount'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $rColor; ?>;"><?php echo $row['days_since_billed']; ?>d ago</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $rBg; ?>; color: <?php echo $rColor; ?>; font-weight: 800;">
                                                <?php echo $row['rental_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $rentalBaseUrl = "reports.php?type=rental_roi&status=" . urlencode($status ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $rentalPages, $rentalTotal, $limit, $rentalBaseUrl, 'deployments');
                    ?>
                </div>

