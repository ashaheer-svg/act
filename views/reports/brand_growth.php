                <!-- 6. Brand & Category Performance (2009–2026) -->
                
                <?php if (($viewMode ?? 'brand') === 'category'): ?>
                <!-- Category Performance View -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Categories</span>
                        <span class="metric-pill-val" style="color: #059669;"><?php echo number_format($categoryPerfSummary['total_categories'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Commercial Verticals</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Category Portfolio Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($categoryPerfSummary['total_gross_portfolio'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Consolidated Extracted Revenue</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Units Dispatched</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($categoryPerfSummary['total_units_dispatched'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware, Services & Licenses</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Modern Era Sales (2023+)</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($categoryPerfSummary['modern_sales_volume'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Post-Crisis Expansion</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Market Penetration</span>
                        <span class="metric-pill-val"><?php echo number_format($categoryPerfSummary['total_client_reach'] ?? 0); ?> Accounts</span>
                        <span class="metric-pill-sub">Category-Customer Touchpoints</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Strategic Category</th>
                                    <th class="text-right" style="width: 70px;">Invoices</th>
                                    <th class="text-right" style="width: 80px;">Client Reach</th>
                                    <th class="text-right" style="width: 70px;">Units Sold</th>
                                    <th class="text-right" style="width: 105px;">Base Revenue</th>
                                    <th class="text-right" style="width: 90px;">18% VAT</th>
                                    <th class="text-right" style="width: 115px;">Lifetime Gross</th>
                                    <th class="text-right" style="width: 100px;">Pre-2023 Era</th>
                                    <th class="text-right" style="width: 100px;">Post-2023 Era</th>
                                    <th class="text-right" style="width: 75px;">Share %</th>
                                    <th class="text-center" style="width: 85px;">Trajectory</th>
                                    <th class="text-center" style="width: 45px;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categoryPerfData)): ?>
                                    <tr><td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">No category sales records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categoryPerfData as $row): 
                                        $isExpanding = $row['growth_trajectory'] === 'EXPANDING';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($row['category_color'] ?: '#059669'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 700; color: var(--text-main); font-size: 12px;"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                                <?php if (!empty($row['category_code'])): ?>
                                                    <span style="font-size: 9.5px; padding: 1px 4px; background: #f1f5f9; border-radius: 3px; color: #64748b; font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($row['category_code']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_invoices']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['client_reach']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_units_sold']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['lifetime_base_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['lifetime_vat_revenue'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['lifetime_gross_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['historical_pre_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: #15803d;"><?php echo number_format($row['modern_post_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: #059669;"><?php echo $row['revenue_share_pct']; ?>%</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $isExpanding ? '#ecfdf5' : '#fee2e2'; ?>; color: <?php echo $isExpanding ? '#15803d' : '#b91c1c'; ?>; font-weight: 800;">
                                                <?php echo $row['growth_trajectory']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="product_mapping.php?tab=products&status=ALL&category=<?php echo urlencode($row['category_name']); ?>" class="action-icon-btn" title="Inspect Products in this Category" target="_blank">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif (($viewMode ?? 'brand') === 'matrix'): ?>
                <!-- Brand x Category Matrix View -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Combinations</span>
                        <span class="metric-pill-val" style="color: #7c3aed;"><?php echo count($matrixData); ?></span>
                        <span class="metric-pill-sub">Brand &times; Category Intersections</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Portfolio Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format(array_sum(array_column($matrixData, 'gross_revenue')), 0); ?></span>
                        <span class="metric-pill-sub">All Intersections</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Units Sold</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format(array_sum(array_column($matrixData, 'units_sold'))); ?></span>
                        <span class="metric-pill-sub">Hardware, licenses & contracts</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Product Category</th>
                                    <th class="text-right" style="width: 100px;">Invoices</th>
                                    <th class="text-right" style="width: 100px;">Units Sold</th>
                                    <th class="text-right" style="width: 140px;">Gross Revenue</th>
                                    <th class="text-right" style="width: 90px;">Share %</th>
                                    <th class="text-center" style="width: 55px;">Audit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($matrixData)): ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No cross-portfolio records found.</td></tr>
                                <?php else: 
                                    $totalMatrixGross = array_sum(array_column($matrixData, 'gross_revenue'));
                                    foreach ($matrixData as $m): 
                                        $sharePct = $totalMatrixGross > 0 ? round(($m['gross_revenue'] / $totalMatrixGross) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($m['brand_color'] ?: '#2563eb'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($m['brand_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($m['category_color'] ?: '#059669'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($m['category_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num"><?php echo number_format($m['invoice_count']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($m['units_sold']); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo htmlspecialchars($currency) . number_format($m['gross_revenue'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: var(--primary);"><?php echo $sharePct; ?>%</td>
                                        <td class="text-center">
                                            <a href="product_mapping.php?tab=products&status=ALL&brand=<?php echo urlencode($m['brand_name']); ?>&category=<?php echo urlencode($m['category_name']); ?>" class="action-icon-btn" title="View Products in this Brand/Category" target="_blank">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php else: ?>
                <!-- Brand Performance View (Default) -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Brands</span>
                        <span class="metric-pill-val"><?php echo number_format($brandGrowthSummary['total_brands'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware & Software Makers</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Portfolio Gross</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($brandGrowthSummary['total_gross_portfolio'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">All-Time 17-Year Volume</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Units Dispatched</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($brandGrowthSummary['total_units_dispatched'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Hardware & Licenses</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Modern Era Sales (2023+)</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo htmlspecialchars($currency) . number_format($brandGrowthSummary['modern_sales_volume'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Post-Crisis Expansion</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Market Penetration</span>
                        <span class="metric-pill-val"><?php echo number_format($brandGrowthSummary['total_client_reach'] ?? 0); ?> Accounts</span>
                        <span class="metric-pill-sub">Brand-Customer Touchpoints</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Brand / Strategic Product Line</th>
                                    <th class="text-right" style="width: 70px;">Invoices</th>
                                    <th class="text-right" style="width: 80px;">Client Reach</th>
                                    <th class="text-right" style="width: 70px;">Units Sold</th>
                                    <th class="text-right" style="width: 105px;">Base Revenue</th>
                                    <th class="text-right" style="width: 90px;">18% VAT</th>
                                    <th class="text-right" style="width: 115px;">Lifetime Gross</th>
                                    <th class="text-right" style="width: 100px;">Pre-2023 Era</th>
                                    <th class="text-right" style="width: 100px;">Post-2023 Era</th>
                                    <th class="text-right" style="width: 75px;">Share %</th>
                                    <th class="text-center" style="width: 85px;">Trajectory</th>
                                    <th class="text-center" style="width: 45px;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($brandGrowthData)): ?>
                                    <tr><td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">No brand sales records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($brandGrowthData as $row): 
                                        $isExpanding = $row['growth_trajectory'] === 'EXPANDING';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo htmlspecialchars($row['brand_color'] ?: '#2563eb'); ?>; display: inline-block;"></span>
                                                <span style="font-weight: 700; color: var(--text-main); font-size: 12px;"><?php echo htmlspecialchars($row['brand_name']); ?></span>
                                                <?php if (!empty($row['brand_code'])): ?>
                                                    <span style="font-size: 9.5px; padding: 1px 4px; background: #f1f5f9; border-radius: 3px; color: #64748b; font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($row['brand_code']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_invoices']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['client_reach']); ?></td>
                                        <td class="text-right dense-num"><?php echo number_format($row['total_units_sold']); ?></td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['lifetime_base_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['lifetime_vat_revenue'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['lifetime_gross_revenue'], 0); ?></td>
                                        <td class="text-right dense-num" style="color: #64748b;"><?php echo number_format($row['historical_pre_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: #15803d;"><?php echo number_format($row['modern_post_2023'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: var(--primary);"><?php echo $row['revenue_share_pct']; ?>%</td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $isExpanding ? '#ecfdf5' : '#fee2e2'; ?>; color: <?php echo $isExpanding ? '#15803d' : '#b91c1c'; ?>; font-weight: 800;">
                                                <?php echo $row['growth_trajectory']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="product_mapping.php?tab=products&status=ALL&brand=<?php echo urlencode($row['brand_name']); ?>" class="action-icon-btn" title="Inspect Products for this Brand" target="_blank">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

