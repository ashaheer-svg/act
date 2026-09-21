                <!-- 3. Hardware End-of-Life (EOL) & Refresh Forecast -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Serialized Units</span>
                        <span class="metric-pill-val"><?php echo number_format($eolSummary['total_tracked_assets'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware Fleet Inventory</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Expired Warranties (EOL)</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($eolSummary['total_expired'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Primary Hardware Refresh Pipeline</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Expiring ≤ 90 Days</span>
                        <span class="metric-pill-val" style="color: #6366f1;"><?php echo number_format($eolSummary['expiring_90d'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Warranty Extension Opportunities</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Expiring ≤ 30 Days</span>
                        <span class="metric-pill-val" style="color: #ea580c;"><?php echo number_format($eolSummary['expiring_30d'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Urgent Renewal Alerts</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Under Coverage</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo number_format($eolSummary['active_assets'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Healthy Hardware Base</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Serial Number</th>
                                    <th style="width: 140px;">Model SKU</th>
                                    <th>Product Description</th>
                                    <th style="width: 80px;">Brand</th>
                                    <th>Customer Organization</th>
                                    <th style="width: 90px;">Invoice #</th>
                                    <th style="width: 75px;">Start Date</th>
                                    <th style="width: 75px;">Expiry Date</th>
                                    <th class="text-right" style="width: 70px;">Warranty</th>
                                    <th class="text-right" style="width: 70px;">Remaining</th>
                                    <th class="text-center" style="width: 85px;">Fleet Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($eolData)): ?>
                                    <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">No hardware assets found matching the filter criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($eolData as $row): 
                                        $statusColor = '#15803d';
                                        $statusBg = '#ecfdf5';
                                        if ($row['eol_status'] === 'EXPIRED') { $statusColor = '#b91c1c'; $statusBg = '#fee2e2'; }
                                        elseif ($row['eol_status'] === 'EXPIRING_30D') { $statusColor = '#c2410c'; $statusBg = '#ffedd5'; }
                                        elseif ($row['eol_status'] === 'EXPIRING_90D') { $statusColor = '#4338ca'; $statusBg = '#e0e7ff'; }
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 11px;">
                                                <?php echo htmlspecialchars($row['serial_number']); ?>
                                            </span>
                                        </td>
                                        <td style="font-family: monospace; font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['model_sku'] ?: '—'); ?></td>
                                        <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500;" title="<?php echo htmlspecialchars($row['product_name']); ?>">
                                            <?php echo htmlspecialchars($row['product_name']); ?>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #334155;"><?php echo htmlspecialchars($row['brand'] ?: 'Unassigned'); ?></span></td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-doc-num"><?php echo htmlspecialchars($row['invoice_number']); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['warranty_start_date'] ?: '—'); ?></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['warranty_expiry_date']); ?></td>
                                        <td class="text-right dense-num"><?php echo $row['warranty_months']; ?>m</td>
                                        <td class="text-right dense-num-bold" style="color: <?php echo $statusColor; ?>;"><?php echo $row['days_remaining']; ?>d</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; font-weight: 800;">
                                                <?php echo $row['eol_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=eol&status=" . urlencode($status ?? '') . "&brand=" . urlencode($brand ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $eolPages, $eolTotal, $limit, $baseUrl, 'units');
                    ?>
                </div>

