                <!-- 1. Customer Lifetime Value (LTV) & Loyalty Matrix -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Analyzed Clients</span>
                        <span class="metric-pill-val"><?php echo number_format($ltvTotal); ?></span>
                        <span class="metric-pill-sub">Total Historical Accounts</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Lifetime Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($ltvSummary['grand_lifetime_revenue'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Gross Billed Realization</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Core Net Base</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($ltvSummary['grand_lifetime_base'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Pre-VAT Recognized Revenue</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Avg Order (AOV)</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo htmlspecialchars($currency) . number_format($ltvSummary['grand_aov'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Mean Transaction Yield</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tier Distribution</span>
                        <span class="metric-pill-val" style="font-size: 13px; color: #4f46e5;">
                            Plat: <?php echo number_format($ltvSummary['platinum_count'] ?? 0); ?> | Gold: <?php echo number_format($ltvSummary['gold_count'] ?? 0); ?>
                        </span>
                        <span class="metric-pill-sub">
                            Silver: <?php echo number_format($ltvSummary['silver_count'] ?? 0); ?> | Bronze: <?php echo number_format($ltvSummary['bronze_count'] ?? 0); ?>
                        </span>
                    </div>
                </div>

                <!-- Secondary Filters -->
                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="ltv">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Tier:</span>
                        <select name="tier" class="cmd-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($tier ?? 'all') === 'all' ? 'selected' : ''; ?>>All Tiers</option>
                            <option value="PLATINUM" <?php echo ($tier ?? '') === 'PLATINUM' ? 'selected' : ''; ?>>Platinum (≥ 20M)</option>
                            <option value="GOLD" <?php echo ($tier ?? '') === 'GOLD' ? 'selected' : ''; ?>>Gold (5M–20M)</option>
                            <option value="SILVER" <?php echo ($tier ?? '') === 'SILVER' ? 'selected' : ''; ?>>Silver (1M–5M)</option>
                            <option value="BRONZE" <?php echo ($tier ?? '') === 'BRONZE' ? 'selected' : ''; ?>>Bronze (< 1M)</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Channel:</span>
                        <select name="customer_type" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <option value="Partner" <?php echo ($customer_type ?? '') === 'Partner' ? 'selected' : ''; ?>>Partners</option>
                            <option value="End Customer" <?php echo ($customer_type ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customers</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Sort:</span>
                        <select name="sort" class="cmd-select" onchange="this.form.submit()">
                            <option value="ltv_desc" <?php echo ($sort ?? '') === 'ltv_desc' ? 'selected' : ''; ?>>Gross Spend (High to Low)</option>
                            <option value="invoices_desc" <?php echo ($sort ?? '') === 'invoices_desc' ? 'selected' : ''; ?>>Order Frequency (High to Low)</option>
                            <option value="tenure_desc" <?php echo ($sort ?? '') === 'tenure_desc' ? 'selected' : ''; ?>>Account Tenure (Longest First)</option>
                            <option value="aov_desc" <?php echo ($sort ?? '') === 'aov_desc' ? 'selected' : ''; ?>>Avg Order Value (High to Low)</option>
                            <option value="name_asc" <?php echo ($sort ?? '') === 'name_asc' ? 'selected' : ''; ?>>Customer Name (A-Z)</option>
                        </select>
                    </div>
                </form>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Customer Organization</th>
                                    <th style="width: 80px;">Channel</th>
                                    <th style="width: 50px;">Rep</th>
                                    <th style="width: 80px;">First Order</th>
                                    <th style="width: 80px;">Last Order</th>
                                    <th class="text-right" style="width: 60px;">Tenure</th>
                                    <th class="text-right" style="width: 55px;">Invoices</th>
                                    <th class="text-right" style="width: 100px;">Base Net</th>
                                    <th class="text-right" style="width: 90px;">18% VAT</th>
                                    <th class="text-right" style="width: 110px;">Lifetime Gross</th>
                                    <th class="text-right" style="width: 95px;">Avg Order</th>
                                    <th class="text-center" style="width: 75px;">Loyalty Tier</th>
                                    <th class="text-center" style="width: 60px;">Dossier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ltvData)): ?>
                                    <tr><td colspan="13" style="text-align: center; padding: 40px; color: var(--text-muted);">No customer accounts found for the active criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ltvData as $row): 
                                        $tierColor = '#64748b';
                                        $tierBg = '#f1f5f9';
                                        if ($row['ltv_tier'] === 'PLATINUM') { $tierColor = '#4338ca'; $tierBg = '#e0e7ff'; }
                                        elseif ($row['ltv_tier'] === 'GOLD') { $tierColor = '#b45309'; $tierBg = '#fef3c7'; }
                                        elseif ($row['ltv_tier'] === 'SILVER') { $tierColor = '#0f766e'; $tierBg = '#ccfbf1'; }
                                    ?>
                                    <tr onclick="window.location.href='customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>'">
                                        <td style="font-weight: 600; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['customer_type'] ?: 'Direct'); ?></span></td>
                                        <td><span style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($row['sales_rep'] ?: '—'); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['first_invoice']); ?></td>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['last_invoice']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo $row['tenure_years']; ?>y</td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['total_invoices']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['lifetime_base'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['lifetime_vat'], 0); ?></td>
                                        <td class="text-right dense-num-bold dense-num" style="color: var(--text-main);"><?php echo number_format($row['lifetime_gross'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['avg_order_value'], 0); ?></td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $tierBg; ?>; color: <?php echo $tierColor; ?>; font-weight: 800;">
                                                <?php echo $row['ltv_tier']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;">Dossier</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=ltv&tier=" . urlencode($tier ?? '') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&search=" . urlencode($search ?? '') . "&sort=" . urlencode($sort ?? '');
                    echo renderPaginationRail($p, $ltvPages, $ltvTotal, $limit, $baseUrl, 'accounts');
                    ?>
                </div>

