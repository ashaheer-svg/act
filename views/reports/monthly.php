                <!-- ========================================================================= -->
                <!-- EXECUTIVE MONTHLY COLUMN-WISE SALES PERFORMANCE MATRIX (WITH BREAKDOWNS)  -->
                <!-- ========================================================================= -->
                <div class="monthly-matrix-wrapper" style="display: flex; flex-direction: column; gap: 14px;">
                    
                    <!-- Sub-view Navigation Pill Bar -->
                    <div class="no-print" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #ffffff; padding: 8px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); flex-wrap: wrap;">
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <a href="reports.php?type=monthly&view=overview&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>" 
                               style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-decoration: none; <?php echo $monthlyView === 'overview' ? 'background: var(--primary); color: #ffffff;' : 'background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color);'; ?>">
                                <i class="icon-sliders" style="font-size: 12px;"></i> Macro KPI Matrix
                            </a>
                            <a href="reports.php?type=monthly&view=customer&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>" 
                               style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-decoration: none; <?php echo $monthlyView === 'customer' ? 'background: var(--primary); color: #ffffff;' : 'background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color);'; ?>">
                                <i class="icon-users" style="font-size: 12px;"></i> By Customer Breakdown
                            </a>
                            <a href="reports.php?type=monthly&view=rep&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>" 
                               style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-decoration: none; <?php echo $monthlyView === 'rep' ? 'background: var(--primary); color: #ffffff;' : 'background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color);'; ?>">
                                <i class="icon-award" style="font-size: 12px;"></i> By Sales Rep Breakdown
                            </a>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <a href="reports.php?type=monthly&view=<?php echo urlencode($monthlyView); ?>&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>&search=<?php echo urlencode($monthlyFilters['search'] ?? ''); ?>&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>&customer_type=<?php echo urlencode($monthlyFilters['customer_type'] ?? ''); ?>&export=csv" class="cmd-btn" style="font-size: 11px; padding: 4px 10px; height: 26px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; font-weight: 600;">
                                <i class="icon-download" style="font-size: 12px;"></i> Export CSV
                            </a>
                            <button type="button" onclick="window.print()" class="cmd-btn" style="font-size: 11px; padding: 4px 10px; height: 26px; display: inline-flex; align-items: center; gap: 4px; background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; font-weight: 600;">
                                <i class="icon-printer" style="font-size: 12px;"></i> Print View
                            </button>
                        </div>
                    </div>

                    <?php if ($monthlyView === 'customer'): ?>
                    <!-- ========================================================================= -->
                    <!-- 1. BY CUSTOMER MONTHLY BREAKDOWN                                          -->
                    <!-- ========================================================================= -->
                    <!-- Top 4 Customer KPI Cards -->
                    <?php 
                    $custCount = count($matrixData['rows']);
                    $totRev = (float)($matrixData['summary']['total_revenue'] ?? 0);
                    $avgPerCust = $custCount > 0 ? ($totRev / $custCount) : 0;
                    ?>
                    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 2px;">
                        <div class="metric-card" style="border-top: 3px solid var(--primary); padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">ACTIVE CLIENT ACCOUNTS</div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo number_format($custCount); ?> <span style="font-size: 12px; font-weight: 600; color: var(--text-muted);">Clients</span>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Invoices: <strong style="color: var(--text-main);"><?php echo number_format((int)($matrixData['summary']['total_invoices'] ?? 0)); ?></strong> &bull; Units: <strong style="color: var(--text-main);"><?php echo number_format((float)($matrixData['summary']['total_units'] ?? 0)); ?></strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid #0284c7; padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">TOTAL CLIENT GROSS BILLED</div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: #0284c7; margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo htmlspecialchars($currency) . number_format($totRev, 0); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Net Base: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['summary']['total_net_base'] ?? 0) / 1000000, 2); ?>M</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid var(--success); padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">AVERAGE SPEND PER CLIENT</div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--success); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo htmlspecialchars($currency) . number_format($avgPerCust, 0); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Monthly Run-Rate: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['summary']['monthly_average'] ?? 0) / 1000000, 2); ?>M/mo</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid #8b5cf6; padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">TOP CORPORATE CLIENT</div>
                            <div class="metric-value" style="font-size: 14px; font-weight: 800; color: var(--text-main); margin: 8px 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($matrixData['summary']['top_customer']); ?>">
                                <?php echo htmlspecialchars($matrixData['summary']['top_customer']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Period Yield: <strong style="color: #6d28d9;"><?php echo htmlspecialchars($currency) . number_format((float)$matrixData['summary']['top_customer_revenue'], 0); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Filter / Search Header -->
                    <form method="GET" action="reports.php" class="no-print" style="display: flex; gap: 8px; align-items: center; background: #f8fafc; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <input type="hidden" name="type" value="monthly">
                        <input type="hidden" name="view" value="customer">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($monthlyMode); ?>">
                        <input type="hidden" name="year" value="<?php echo htmlspecialchars($selectedYear); ?>">
                        <input type="hidden" name="customer_type" value="<?php echo htmlspecialchars($monthlyFilters['customer_type'] ?? ''); ?>">
                        <input type="hidden" name="brand" value="<?php echo htmlspecialchars($monthlyFilters['brand'] ?? ''); ?>">
                        <i class="icon-search" style="color: var(--text-muted); font-size: 13px;"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($monthlyFilters['search'] ?? ''); ?>" placeholder="Filter clients by name..." style="flex: 1; border: 1px solid var(--border-color); padding: 5px 10px; font-size: 12px; border-radius: 4px; outline: none; background: #ffffff;">
                        <button type="submit" class="cmd-btn" style="height: 28px; padding: 0 12px; font-size: 11px; font-weight: 600; background: var(--primary); color: #ffffff; border: none; border-radius: 4px; cursor: pointer;">Search</button>
                        <?php if (!empty($monthlyFilters['search'])): ?>
                            <a href="reports.php?type=monthly&view=customer&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>&customer_type=<?php echo urlencode($monthlyFilters['customer_type'] ?? ''); ?>&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>" style="font-size: 11px; color: var(--text-muted); text-decoration: none; padding: 4px 8px;">Clear</a>
                        <?php endif; ?>
                    </form>

                    <!-- Customer Matrix Table Card -->
                    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                        <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                            <div>
                                <h3 style="margin: 0; font-size: 13px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                                    <span>Customer Monthly Sales Matrix — <?php echo htmlspecialchars($matrixData['period_title']); ?></span>
                                    <span style="font-size: 10px; font-weight: 700; background: #e0e7ff; color: #3730a3; padding: 2px 7px; border-radius: 4px;"><?php echo count($matrixData['rows']); ?> Clients</span>
                                </h3>
                                <p style="margin: 2px 0 0 0; font-size: 11px; color: var(--text-muted);">Monthly invoiced sales per client across 12 periods with Period Total and Monthly Average Benchmark columns.</p>
                            </div>
                        </div>

                        <div class="matrix-table-wrap" style="width: 100%; overflow-x: auto; scrollbar-width: thin;">
                            <table class="rational-table matrix-sales-table" style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 11.5px;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="position: sticky; left: 0; z-index: 3; background: #f8fafc; text-align: left; padding: 8px 12px; font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; min-width: 220px; width: 220px; border-bottom: 2px solid var(--border-color); border-right: 2px solid var(--border-color);">
                                            CUSTOMER / CORPORATE ACCOUNT
                                        </th>
                                        <?php foreach ($matrixData['months'] as $m): ?>
                                            <th style="text-align: right; padding: 8px 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; min-width: 86px; border-bottom: 2px solid var(--border-color); border-right: 1px solid var(--border-divider); <?php echo $m['is_current'] ? 'background: #eef2ff; color: #4338ca;' : 'color: var(--text-muted);'; ?>">
                                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 1px;">
                                                    <span style="font-weight: 800; font-size: 10.5px;"><?php echo $m['short_label']; ?></span>
                                                    <?php if ($m['is_current']): ?>
                                                        <span style="font-size: 8.5px; background: #4338ca; color: #ffffff; padding: 0 4px; border-radius: 2px; font-weight: 800;">NOW</span>
                                                    <?php endif; ?>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th style="text-align: right; padding: 8px 12px; font-size: 10px; font-weight: 800; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.04em; min-width: 115px; background: #eef2ff; border-bottom: 2px solid #c7d2fe; border-left: 2px solid #c7d2fe; border-right: 1px solid #c7d2fe;">
                                            PERIOD TOTAL
                                        </th>
                                        <th style="text-align: right; padding: 8px 12px; font-size: 10px; font-weight: 800; color: #0369a1; text-transform: uppercase; letter-spacing: 0.04em; min-width: 105px; background: #f0f9ff; border-bottom: 2px solid #bae6fd;">
                                            MONTHLY AVG
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($matrixData['rows'])): ?>
                                    <tr>
                                        <td colspan="15" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                            No customer records found matching the active filters.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $rowCounter = 0;
                                    foreach ($matrixData['rows'] as $row): 
                                        $rowCounter++;
                                        $rowBg = ($rowCounter % 2 === 0 ? '#fafafa' : '#ffffff');
                                    ?>
                                    <tr style="background: <?php echo $rowBg; ?>; transition: background 0.15s ease;" onmouseover="this.style.background='rgba(79, 70, 229, 0.04)'" onmouseout="this.style.background='<?php echo $rowBg; ?>'">
                                        <!-- Sticky Customer Column -->
                                        <td style="position: sticky; left: 0; z-index: 2; background: inherit; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); border-right: 2px solid var(--border-color); white-space: nowrap;">
                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                <div style="font-weight: 700; color: var(--text-main); font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                    <a href="customers.php?search=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none;">
                                                        <?php echo htmlspecialchars($row['customer_name']); ?>
                                                    </a>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 5px; font-size: 9.5px; color: var(--text-muted);">
                                                    <span style="display: inline-block; padding: 0 4px; border-radius: 3px; font-weight: 700; <?php echo $row['customer_type'] === 'Partner' ? 'background: #e0e7ff; color: #3730a3;' : 'background: #f1f5f9; color: #475569;'; ?>">
                                                        <?php echo htmlspecialchars($row['customer_type']); ?>
                                                    </span>
                                                    <?php if (!empty($row['top_brand'])): ?>
                                                        <span>&bull; <?php echo htmlspecialchars($row['top_brand']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 12 Month Cells -->
                                        <?php for ($i = 1; $i <= 12; $i++): 
                                            $val = (float)($row['m_' . $i] ?? 0);
                                            $mDef = $matrixData['months'][$i - 1];
                                            $isFuture = $mDef['is_future'];
                                        ?>
                                            <td style="text-align: right; padding: 6px 8px; border-bottom: 1px solid var(--border-divider); border-right: 1px solid var(--border-divider); font-variant-numeric: tabular-nums; font-weight: <?php echo $val > 0 ? '700' : '500'; ?>; color: <?php echo $val > 0 ? 'var(--text-main)' : '#94a3b8'; ?>; <?php echo $mDef['is_current'] ? 'background: rgba(79, 70, 229, 0.02);' : ''; ?>">
                                                <?php if ($isFuture): ?>
                                                    <span style="color: #cbd5e1;">-</span>
                                                <?php elseif ($val > 0): ?>
                                                    <span><?php echo number_format($val, 0); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>

                                        <!-- Period Total Cell -->
                                        <td style="text-align: right; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); border-left: 2px solid #c7d2fe; border-right: 1px solid #c7d2fe; background: #f8fafc; font-variant-numeric: tabular-nums; font-weight: 800; color: #1e1b4b;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$row['total_revenue'], 0); ?>
                                        </td>

                                        <!-- Monthly Average Cell -->
                                        <td style="text-align: right; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); background: #f0f9ff; font-variant-numeric: tabular-nums; font-weight: 700; color: #0369a1;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$row['monthly_avg'], 0); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #eef2ff; font-weight: 800; border-top: 2px solid #818cf8;">
                                        <td style="position: sticky; left: 0; z-index: 2; background: #eef2ff; padding: 8px 12px; border-right: 2px solid #818cf8; color: #1e1b4b; text-transform: uppercase; font-size: 11px;">
                                            PORTFOLIO TOTAL (<?php echo count($matrixData['rows']); ?> ACCOUNTS)
                                        </td>
                                        <?php for ($i = 1; $i <= 12; $i++): 
                                            $totM = (float)($matrixData['summary']['monthly_totals'][$i] ?? 0);
                                        ?>
                                            <td style="text-align: right; padding: 8px 8px; border-right: 1px solid #c7d2fe; font-variant-numeric: tabular-nums; color: #1e1b4b; font-size: 11px;">
                                                <?php echo $totM > 0 ? number_format($totM, 0) : '-'; ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td style="text-align: right; padding: 8px 12px; border-left: 2px solid #818cf8; border-right: 1px solid #818cf8; background: #e0e7ff; color: #1e1b4b; font-variant-numeric: tabular-nums; font-size: 12px;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$matrixData['summary']['total_revenue'], 0); ?>
                                        </td>
                                        <td style="text-align: right; padding: 8px 12px; background: #e0f2fe; color: #0369a1; font-variant-numeric: tabular-nums; font-size: 12px;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$matrixData['summary']['monthly_average'], 0); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <?php elseif ($monthlyView === 'rep'): ?>
                    <!-- ========================================================================= -->
                    <!-- 2. BY SALES REP MONTHLY BREAKDOWN                                         -->
                    <!-- ========================================================================= -->
                    <!-- Top 4 Sales Rep KPI Cards -->
                    <?php 
                    $repCount = count($matrixData['rows']);
                    $totRev = (float)($matrixData['summary']['total_revenue'] ?? 0);
                    $avgPerRep = $repCount > 0 ? ($totRev / $repCount) : 0;
                    ?>
                    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 2px;">
                        <div class="metric-card" style="border-top: 3px solid var(--primary); padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">ACTIVE SALES REPS</div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo number_format($repCount); ?> <span style="font-size: 12px; font-weight: 600; color: var(--text-muted);">Agents</span>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Total Deals: <strong style="color: var(--text-main);"><?php echo number_format((int)($matrixData['summary']['total_invoices'] ?? 0)); ?></strong> &bull; Units: <strong style="color: var(--text-main);"><?php echo number_format((float)($matrixData['summary']['total_units'] ?? 0)); ?></strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid #0284c7; padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">TEAM GROSS BILLED</div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: #0284c7; margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo htmlspecialchars($currency) . number_format($totRev, 0); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Net Base: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['summary']['total_net_base'] ?? 0) / 1000000, 2); ?>M</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid var(--success); padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">UNIQUE CLIENT REACH</div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--success); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo number_format((int)($matrixData['summary']['total_reach'] ?? 0)); ?> <span style="font-size: 12px; font-weight: 600; color: var(--text-muted);">Accounts</span>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Avg Yield / Rep: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format($avgPerRep / 1000000, 2); ?>M</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid #8b5cf6; padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">TOP PERFORMING REP</div>
                            <div class="metric-value" style="font-size: 14px; font-weight: 800; color: var(--text-main); margin: 8px 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($matrixData['summary']['top_rep']); ?>">
                                <?php echo htmlspecialchars($matrixData['summary']['top_rep']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Period Yield: <strong style="color: #6d28d9;"><?php echo htmlspecialchars($currency) . number_format((float)$matrixData['summary']['top_rep_revenue'], 0); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Filter / Search Header -->
                    <form method="GET" action="reports.php" class="no-print" style="display: flex; gap: 8px; align-items: center; background: #f8fafc; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <input type="hidden" name="type" value="monthly">
                        <input type="hidden" name="view" value="rep">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($monthlyMode); ?>">
                        <input type="hidden" name="year" value="<?php echo htmlspecialchars($selectedYear); ?>">
                        <input type="hidden" name="brand" value="<?php echo htmlspecialchars($monthlyFilters['brand'] ?? ''); ?>">
                        <i class="icon-search" style="color: var(--text-muted); font-size: 13px;"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($monthlyFilters['search'] ?? ''); ?>" placeholder="Filter reps by name or code..." style="flex: 1; border: 1px solid var(--border-color); padding: 5px 10px; font-size: 12px; border-radius: 4px; outline: none; background: #ffffff;">
                        <button type="submit" class="cmd-btn" style="height: 28px; padding: 0 12px; font-size: 11px; font-weight: 600; background: var(--primary); color: #ffffff; border: none; border-radius: 4px; cursor: pointer;">Search</button>
                        <?php if (!empty($monthlyFilters['search'])): ?>
                            <a href="reports.php?type=monthly&view=rep&mode=<?php echo urlencode($monthlyMode); ?>&year=<?php echo urlencode($selectedYear); ?>&brand=<?php echo urlencode($monthlyFilters['brand'] ?? ''); ?>" style="font-size: 11px; color: var(--text-muted); text-decoration: none; padding: 4px 8px;">Clear</a>
                        <?php endif; ?>
                    </form>

                    <!-- Sales Rep Matrix Table Card -->
                    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                        <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                            <div>
                                <h3 style="margin: 0; font-size: 13px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                                    <span>Sales Rep Monthly Performance Matrix — <?php echo htmlspecialchars($matrixData['period_title']); ?></span>
                                    <span style="font-size: 10px; font-weight: 700; background: #e0e7ff; color: #3730a3; padding: 2px 7px; border-radius: 4px;"><?php echo count($matrixData['rows']); ?> Agents</span>
                                </h3>
                                <p style="margin: 2px 0 0 0; font-size: 11px; color: var(--text-muted);">Monthly sales performance per representative across 12 periods with Period Total and Run-Rate Average columns.</p>
                            </div>
                        </div>

                        <div class="matrix-table-wrap" style="width: 100%; overflow-x: auto; scrollbar-width: thin;">
                            <table class="rational-table matrix-sales-table" style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 11.5px;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="position: sticky; left: 0; z-index: 3; background: #f8fafc; text-align: left; padding: 8px 12px; font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; min-width: 200px; width: 200px; border-bottom: 2px solid var(--border-color); border-right: 2px solid var(--border-color);">
                                            SALES REPRESENTATIVE
                                        </th>
                                        <?php foreach ($matrixData['months'] as $m): ?>
                                            <th style="text-align: right; padding: 8px 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; min-width: 86px; border-bottom: 2px solid var(--border-color); border-right: 1px solid var(--border-divider); <?php echo $m['is_current'] ? 'background: #eef2ff; color: #4338ca;' : 'color: var(--text-muted);'; ?>">
                                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 1px;">
                                                    <span style="font-weight: 800; font-size: 10.5px;"><?php echo $m['short_label']; ?></span>
                                                    <?php if ($m['is_current']): ?>
                                                        <span style="font-size: 8.5px; background: #4338ca; color: #ffffff; padding: 0 4px; border-radius: 2px; font-weight: 800;">NOW</span>
                                                    <?php endif; ?>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th style="text-align: right; padding: 8px 12px; font-size: 10px; font-weight: 800; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.04em; min-width: 115px; background: #eef2ff; border-bottom: 2px solid #c7d2fe; border-left: 2px solid #c7d2fe; border-right: 1px solid #c7d2fe;">
                                            PERIOD TOTAL
                                        </th>
                                        <th style="text-align: right; padding: 8px 12px; font-size: 10px; font-weight: 800; color: #0369a1; text-transform: uppercase; letter-spacing: 0.04em; min-width: 105px; background: #f0f9ff; border-bottom: 2px solid #bae6fd;">
                                            MONTHLY AVG
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($matrixData['rows'])): ?>
                                    <tr>
                                        <td colspan="15" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                            No sales rep records found matching the active filters.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $rowCounter = 0;
                                    foreach ($matrixData['rows'] as $row): 
                                        $rowCounter++;
                                        $rowBg = ($rowCounter % 2 === 0 ? '#fafafa' : '#ffffff');
                                    ?>
                                    <tr style="background: <?php echo $rowBg; ?>; transition: background 0.15s ease;" onmouseover="this.style.background='rgba(79, 70, 229, 0.04)'" onmouseout="this.style.background='<?php echo $rowBg; ?>'">
                                        <!-- Sticky Sales Rep Column -->
                                        <td style="position: sticky; left: 0; z-index: 2; background: inherit; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); border-right: 2px solid var(--border-color); white-space: nowrap;">
                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <span style="font-weight: 800; color: var(--text-main); font-size: 11.5px;">
                                                        <?php echo htmlspecialchars($row['rep_name']); ?>
                                                    </span>
                                                    <span style="font-size: 9px; font-weight: 700; background: #f1f5f9; color: #475569; padding: 1px 5px; border-radius: 3px; border: 1px solid #cbd5e1;">
                                                        <?php echo htmlspecialchars($row['rep_code']); ?>
                                                    </span>
                                                </div>
                                                <div style="font-size: 9.5px; color: var(--text-muted);">
                                                    Reach: <strong style="color: var(--text-main);"><?php echo (int)$row['customer_reach']; ?></strong> clients &bull; Deals: <strong style="color: var(--text-main);"><?php echo (int)$row['total_invoices']; ?></strong>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 12 Month Cells -->
                                        <?php for ($i = 1; $i <= 12; $i++): 
                                            $val = (float)($row['m_' . $i] ?? 0);
                                            $mDef = $matrixData['months'][$i - 1];
                                            $isFuture = $mDef['is_future'];
                                        ?>
                                            <td style="text-align: right; padding: 6px 8px; border-bottom: 1px solid var(--border-divider); border-right: 1px solid var(--border-divider); font-variant-numeric: tabular-nums; font-weight: <?php echo $val > 0 ? '700' : '500'; ?>; color: <?php echo $val > 0 ? 'var(--text-main)' : '#94a3b8'; ?>; <?php echo $mDef['is_current'] ? 'background: rgba(79, 70, 229, 0.02);' : ''; ?>">
                                                <?php if ($isFuture): ?>
                                                    <span style="color: #cbd5e1;">-</span>
                                                <?php elseif ($val > 0): ?>
                                                    <span><?php echo number_format($val, 0); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>

                                        <!-- Period Total Cell -->
                                        <td style="text-align: right; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); border-left: 2px solid #c7d2fe; border-right: 1px solid #c7d2fe; background: #f8fafc; font-variant-numeric: tabular-nums; font-weight: 800; color: #1e1b4b;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$row['total_revenue'], 0); ?>
                                        </td>

                                        <!-- Monthly Average Cell -->
                                        <td style="text-align: right; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); background: #f0f9ff; font-variant-numeric: tabular-nums; font-weight: 700; color: #0369a1;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$row['monthly_avg'], 0); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #eef2ff; font-weight: 800; border-top: 2px solid #818cf8;">
                                        <td style="position: sticky; left: 0; z-index: 2; background: #eef2ff; padding: 8px 12px; border-right: 2px solid #818cf8; color: #1e1b4b; text-transform: uppercase; font-size: 11px;">
                                            SALES TEAM TOTAL (<?php echo count($matrixData['rows']); ?> AGENTS)
                                        </td>
                                        <?php for ($i = 1; $i <= 12; $i++): 
                                            $totM = (float)($matrixData['summary']['monthly_totals'][$i] ?? 0);
                                        ?>
                                            <td style="text-align: right; padding: 8px 8px; border-right: 1px solid #c7d2fe; font-variant-numeric: tabular-nums; color: #1e1b4b; font-size: 11px;">
                                                <?php echo $totM > 0 ? number_format($totM, 0) : '-'; ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td style="text-align: right; padding: 8px 12px; border-left: 2px solid #818cf8; border-right: 1px solid #818cf8; background: #e0e7ff; color: #1e1b4b; font-variant-numeric: tabular-nums; font-size: 12px;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$matrixData['summary']['total_revenue'], 0); ?>
                                        </td>
                                        <td style="text-align: right; padding: 8px 12px; background: #e0f2fe; color: #0369a1; font-variant-numeric: tabular-nums; font-size: 12px;">
                                            <?php echo htmlspecialchars($currency) . number_format((float)$matrixData['summary']['monthly_average'], 0); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- ========================================================================= -->
                    <!-- 3. MACRO KPI MONTHLY MATRIX (DEFAULT EXECUTIVE OVERVIEW)                  -->
                    <!-- ========================================================================= -->
                    <!-- Top 4 Executive KPI Cards -->
                    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 2px;">
                        <div class="metric-card" style="border-top: 3px solid var(--primary); padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">PERIOD GROSS BILLED</div>
                                <span style="font-size: 9.5px; font-weight: 700; background: #eef2ff; color: #4338ca; padding: 2px 6px; border-radius: 4px;"><?php echo (int)($matrixData['active_months_count'] ?? 0); ?> Active Mo</span>
                            </div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo htmlspecialchars($currency) . number_format((float)($matrixData['totals']['gross_sales'] ?? 0), 0); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Net: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['totals']['net_base'] ?? 0) / 1000000, 2); ?>M</strong> &bull; VAT: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['totals']['vat_component'] ?? 0) / 1000000, 2); ?>M</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid #0284c7; padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">MONTHLY RUN-RATE AVG</div>
                                <span style="font-size: 9.5px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px;">Benchmark</span>
                            </div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: #0284c7; margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo htmlspecialchars($currency) . number_format((float)($matrixData['averages']['gross_sales'] ?? 0), 0); ?><span style="font-size: 12px; font-weight: 600; color: var(--text-muted);"> / mo</span>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Avg Invoices: <strong style="color: var(--text-main);"><?php echo number_format((float)($matrixData['averages']['invoice_count'] ?? 0), 0); ?>/mo</strong> &bull; Units: <strong style="color: var(--text-main);"><?php echo number_format((float)($matrixData['averages']['total_units'] ?? 0), 0); ?>/mo</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid var(--success); padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">CASH REALIZATION RATE</div>
                                <span style="font-size: 9.5px; font-weight: 700; background: #ecfdf5; color: #065f46; padding: 2px 6px; border-radius: 4px;"><?php echo number_format((float)($matrixData['totals']['collection_rate'] ?? 0), 1); ?>% Settled</span>
                            </div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--success); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo htmlspecialchars($currency) . number_format((float)($matrixData['totals']['collected_amount'] ?? 0), 0); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                Outstanding: <strong style="color: #b91c1c;"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['totals']['outstanding_amount'] ?? 0) / 1000000, 2); ?>M</strong>
                            </div>
                        </div>

                        <div class="metric-card" style="border-top: 3px solid #8b5cf6; padding: 12px 14px; background: var(--card-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top-width: 3px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div class="metric-label" style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">TRANSACTION REACH</div>
                                <span style="font-size: 9.5px; font-weight: 700; background: #f5f3ff; color: #6d28d9; padding: 2px 6px; border-radius: 4px;"><?php echo number_format((int)($matrixData['totals']['customer_count'] ?? 0)); ?> Clients</span>
                            </div>
                            <div class="metric-value" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 6px 0 4px; font-variant-numeric: tabular-nums;">
                                <?php echo number_format((int)($matrixData['totals']['invoice_count'] ?? 0)); ?> Invoices
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                AOV: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currency) . number_format((float)($matrixData['totals']['aov'] ?? 0), 0); ?></strong> &bull; Total Units: <strong style="color: var(--text-main);"><?php echo number_format((float)($matrixData['totals']['total_units'] ?? 0)); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Visual 12-Month Cadence Chart (SVG) -->
                    <?php
                    $chartMaxGross = 1;
                    foreach ($matrixData['months'] as $m) {
                        if (!$m['is_future']) {
                            $chartMaxGross = max($chartMaxGross, (float)$m['metrics']['gross_sales']);
                        }
                    }
                    $chartMaxGross = max(1, $chartMaxGross * 1.12);
                    $chartH = 135;
                    $chartW = 960;
                    $slotW = $chartW / 12;
                    $barW = 14;
                    ?>
                    <div class="card" style="padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="icon-bar-chart-2" style="color: var(--primary); font-size: 15px;"></i>
                                <span style="font-size: 12px; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.04em;">Monthly Invoiced Revenue & Cash Realization Trajectory</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 14px; font-size: 11px; color: var(--text-muted);">
                                <span style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 10px; height: 10px; background: #4f46e5; border-radius: 2px; display: inline-block;"></span> Gross Billed
                                </span>
                                <span style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 10px; height: 10px; background: #10b981; border-radius: 2px; display: inline-block;"></span> Cash Collected
                                </span>
                                <span style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 10px; height: 10px; background: #ef4444; border-radius: 2px; display: inline-block;"></span> Outstanding
                                </span>
                            </div>
                        </div>

                        <div style="width: 100%; overflow-x: auto; scrollbar-width: none;">
                            <svg viewBox="0 0 <?php echo $chartW; ?> <?php echo $chartH + 30; ?>" style="width: 100%; height: auto; min-width: 720px; display: block;" xmlns="http://www.w3.org/2000/svg">
                                <!-- Grid Lines -->
                                <line x1="0" y1="<?php echo $chartH; ?>" x2="<?php echo $chartW; ?>" y2="<?php echo $chartH; ?>" stroke="#e2e8f0" stroke-width="1" />
                                <line x1="0" y1="<?php echo $chartH * 0.66; ?>" x2="<?php echo $chartW; ?>" y2="<?php echo $chartH * 0.66; ?>" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3,3" />
                                <line x1="0" y1="<?php echo $chartH * 0.33; ?>" x2="<?php echo $chartW; ?>" y2="<?php echo $chartH * 0.33; ?>" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3,3" />
                                
                                <?php 
                                $idx = 0;
                                foreach ($matrixData['months'] as $m): 
                                    $xCenter = $idx * $slotW + ($slotW / 2);
                                    $grossVal = (float)$m['metrics']['gross_sales'];
                                    $collVal = (float)$m['metrics']['collected_amount'];
                                    $isFuture = $m['is_future'];

                                    $grossH = $isFuture ? 0 : round(($grossVal / $chartMaxGross) * $chartH);
                                    $collH = $isFuture ? 0 : round(($collVal / $chartMaxGross) * $chartH);
                                    $grossY = $chartH - $grossH;
                                    $collY = $chartH - $collH;
                                ?>
                                    <!-- Slot Background on hover -->
                                    <rect x="<?php echo $idx * $slotW + 2; ?>" y="4" width="<?php echo $slotW - 4; ?>" height="<?php echo $chartH + 24; ?>" rx="4" fill="<?php echo $m['is_current'] ? 'rgba(79, 70, 229, 0.04)' : 'transparent'; ?>" />

                                    <?php if ($isFuture): ?>
                                        <text x="<?php echo $xCenter; ?>" y="<?php echo $chartH - 20; ?>" text-anchor="middle" font-size="11" fill="#cbd5e1" font-weight="600">-</text>
                                    <?php else: ?>
                                        <!-- Gross Bar -->
                                        <rect x="<?php echo $xCenter - $barW - 1; ?>" y="<?php echo $grossY; ?>" width="<?php echo $barW; ?>" height="<?php echo max(2, $grossH); ?>" rx="2" fill="#4f46e5" opacity="0.9">
                                            <title><?php echo $m['label']; ?> Gross: <?php echo htmlspecialchars($currency) . number_format($grossVal, 0); ?></title>
                                        </rect>
                                        <!-- Collected Bar -->
                                        <rect x="<?php echo $xCenter + 1; ?>" y="<?php echo $collY; ?>" width="<?php echo $barW; ?>" height="<?php echo max(2, $collH); ?>" rx="2" fill="#10b981" opacity="0.95">
                                            <title><?php echo $m['label']; ?> Collected: <?php echo htmlspecialchars($currency) . number_format($collVal, 0); ?></title>
                                        </rect>
                                    <?php endif; ?>

                                    <!-- Month Label -->
                                    <text x="<?php echo $xCenter; ?>" y="<?php echo $chartH + 18; ?>" text-anchor="middle" font-size="10.5" font-weight="<?php echo $m['is_current'] ? '800' : '600'; ?>" fill="<?php echo $m['is_current'] ? '#4f46e5' : ($isFuture ? '#94a3b8' : '#334155'); ?>" font-family="'Inter', sans-serif">
                                        <?php echo $m['short_label']; ?>
                                    </text>
                                    <?php if ($m['is_current']): ?>
                                        <circle cx="<?php echo $xCenter; ?>" cy="<?php echo $chartH + 26; ?>" r="2" fill="#4f46e5" />
                                    <?php endif; ?>
                                <?php 
                                    $idx++;
                                endforeach; 
                                ?>
                            </svg>
                        </div>
                    </div>

                    <!-- Monthly Performance Matrix Table (Single-Page Layout) -->
                    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                        <div style="padding: 10px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                            <div>
                                <h3 style="margin: 0; font-size: 13px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                                    <span><?php echo htmlspecialchars($matrixData['period_title']); ?></span>
                                    <span style="font-size: 10px; font-weight: 700; background: #e0e7ff; color: #3730a3; padding: 2px 7px; border-radius: 4px;">Single-Page Matrix</span>
                                </h3>
                                <p style="margin: 2px 0 0 0; font-size: 11px; color: var(--text-muted);">Tabular monthly financial metrics aligned across 12 periods with Period Total and Run-Rate Benchmark columns.</p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <a href="reports.php?type=monthly&mode=<?php echo urlencode($matrixData['mode']); ?>&year=<?php echo urlencode($matrixData['selected_year']); ?>&export=csv" class="cmd-btn" style="font-size: 11px; padding: 3px 10px; height: 26px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; font-weight: 600;">
                                    <i class="icon-download" style="font-size: 12px;"></i> Export CSV
                                </a>
                                <button type="button" onclick="window.print()" class="cmd-btn" style="font-size: 11px; padding: 3px 10px; height: 26px; display: inline-flex; align-items: center; gap: 4px; background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; font-weight: 600;">
                                    <i class="icon-printer" style="font-size: 12px;"></i> Print View
                                </button>
                            </div>
                        </div>

                        <div class="matrix-table-wrap" style="width: 100%; overflow-x: auto; scrollbar-width: thin;">
                            <table class="rational-table matrix-sales-table" style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 11.5px;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="position: sticky; left: 0; z-index: 3; background: #f8fafc; text-align: left; padding: 8px 12px; font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; min-width: 210px; width: 210px; border-bottom: 2px solid var(--border-color); border-right: 2px solid var(--border-color);">
                                            FINANCIAL METRIC / KPI
                                        </th>
                                        <?php foreach ($matrixData['months'] as $m): ?>
                                            <th style="text-align: right; padding: 8px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; min-width: 86px; border-bottom: 2px solid var(--border-color); border-right: 1px solid var(--border-divider); <?php echo $m['is_current'] ? 'background: #eef2ff; color: #4338ca;' : 'color: var(--text-muted);'; ?>">
                                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 1px;">
                                                    <span style="font-weight: 800; font-size: 10.5px;"><?php echo $m['short_label']; ?></span>
                                                    <?php if ($m['is_current']): ?>
                                                        <span style="font-size: 8.5px; background: #4338ca; color: #ffffff; padding: 0 4px; border-radius: 2px; font-weight: 800;">NOW</span>
                                                    <?php endif; ?>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th style="text-align: right; padding: 8px 12px; font-size: 10px; font-weight: 800; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.04em; min-width: 115px; background: #eef2ff; border-bottom: 2px solid #c7d2fe; border-left: 2px solid #c7d2fe; border-right: 1px solid #c7d2fe;">
                                            PERIOD TOTAL
                                        </th>
                                        <th style="text-align: right; padding: 8px 12px; font-size: 10px; font-weight: 800; color: #0369a1; text-transform: uppercase; letter-spacing: 0.04em; min-width: 105px; background: #f0f9ff; border-bottom: 2px solid #bae6fd;">
                                            MONTHLY AVG
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rowCounter = 0;
                                    foreach ($matrixData['metric_rows'] as $k => $rowDef): 
                                        $rowCounter++;
                                        $isHighlighted = !empty($rowDef['highlight']);
                                        $rowBg = $isHighlighted ? 'rgba(79, 70, 229, 0.02)' : ($rowCounter % 2 === 0 ? '#fafafa' : '#ffffff');
                                    ?>
                                    <tr style="background: <?php echo $rowBg; ?>; transition: background 0.15s ease;" onmouseover="this.style.background='rgba(79, 70, 229, 0.05)'" onmouseout="this.style.background='<?php echo $rowBg; ?>'">
                                        <!-- Sticky Left Header Cell -->
                                        <td style="position: sticky; left: 0; z-index: 2; background: <?php echo $isHighlighted ? '#fcfcff' : '#ffffff'; ?>; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); border-right: 2px solid var(--border-color); white-space: nowrap;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <i class="<?php echo htmlspecialchars($rowDef['icon']); ?>" style="font-size: 13px; color: <?php echo $isHighlighted ? 'var(--primary)' : 'var(--text-muted)'; ?>; width: 14px;"></i>
                                                <div>
                                                    <div style="font-weight: <?php echo $isHighlighted ? '800' : '600'; ?>; color: <?php echo $isHighlighted ? '#1e1b4b' : 'var(--text-main)'; ?>; font-size: 11.5px;">
                                                        <?php echo htmlspecialchars($rowDef['label']); ?>
                                                    </div>
                                                    <div style="font-size: 9.5px; color: var(--text-muted); line-height: 1.1;">
                                                        <?php echo htmlspecialchars($rowDef['description']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 12 Month Cells -->
                                        <?php foreach ($matrixData['months'] as $m): 
                                            $val = $m['metrics'][$k];
                                            $isFuture = $m['is_future'];
                                        ?>
                                            <td style="text-align: right; padding: 6px 10px; border-bottom: 1px solid var(--border-divider); border-right: 1px solid var(--border-divider); font-variant-numeric: tabular-nums; font-weight: <?php echo $isHighlighted ? '700' : '500'; ?>; color: var(--text-main); <?php echo $m['is_current'] ? 'background: rgba(79, 70, 229, 0.02);' : ''; ?>">
                                                <?php if ($isFuture): ?>
                                                    <span style="color: #cbd5e1; font-weight: 500;">-</span>
                                                <?php elseif ($rowDef['format'] === 'currency'): ?>
                                                    <span style="color: <?php echo ($k === 'outstanding_amount' && (float)$val > 0) ? '#b91c1c' : ($k === 'collected_amount' ? '#15803d' : 'inherit'); ?>;">
                                                        <?php echo number_format((float)$val, 0); ?>
                                                    </span>
                                                <?php elseif ($rowDef['format'] === 'integer'): ?>
                                                    <span><?php echo number_format((float)$val, 0); ?></span>
                                                <?php elseif ($rowDef['format'] === 'percentage'): ?>
                                                    <?php 
                                                    $pctVal = (float)$val;
                                                    $pctColor = ($pctVal >= 90) ? '#15803d' : (($pctVal >= 70) ? '#b45309' : '#b91c1c');
                                                    ?>
                                                    <span style="font-weight: 700; color: <?php echo $pctColor; ?>;">
                                                        <?php echo number_format($pctVal, 1); ?>%
                                                    </span>
                                                <?php elseif ($rowDef['format'] === 'growth_rate'): ?>
                                                    <?php if ($val === null): ?>
                                                        <span style="font-size: 10px; color: var(--text-light);">N/A</span>
                                                    <?php else: 
                                                        $gVal = (float)$val;
                                                        if ($gVal > 0): ?>
                                                            <span style="display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9.5px; font-weight: 700; background: #ecfdf5; color: #15803d; letter-spacing: -0.2px;">
                                                                +<?php echo number_format($gVal, 1); ?>%
                                                            </span>
                                                        <?php elseif ($gVal < 0): ?>
                                                            <span style="display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9.5px; font-weight: 700; background: #fee2e2; color: #b91c1c; letter-spacing: -0.2px;">
                                                                <?php echo number_format($gVal, 1); ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="font-size: 10px; color: var(--text-muted);">0.0%</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>

                                        <!-- Period Total Cell -->
                                        <td style="text-align: right; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); border-left: 2px solid #c7d2fe; border-right: 1px solid #c7d2fe; background: #f8fafc; font-variant-numeric: tabular-nums; font-weight: 800; color: #1e1b4b;">
                                            <?php 
                                            $totVal = $matrixData['totals'][$k];
                                            if ($rowDef['format'] === 'growth_rate') {
                                                echo '<span style="color: var(--text-light); font-weight: 500;">-</span>';
                                            } elseif ($rowDef['format'] === 'percentage') {
                                                echo '<span style="color: #15803d;">' . number_format((float)$totVal, 1) . '%</span>';
                                            } elseif ($rowDef['format'] === 'currency') {
                                                echo htmlspecialchars($currency) . number_format((float)$totVal, 0);
                                            } else {
                                                echo number_format((float)$totVal, 0);
                                            }
                                            ?>
                                        </td>

                                        <!-- Monthly Average Cell -->
                                        <td style="text-align: right; padding: 6px 12px; border-bottom: 1px solid var(--border-divider); background: #f0f9ff; font-variant-numeric: tabular-nums; font-weight: 700; color: #0369a1;">
                                            <?php 
                                            $avgVal = $matrixData['averages'][$k];
                                            if ($rowDef['format'] === 'growth_rate') {
                                                $avgG = (float)$avgVal;
                                                echo ($avgG >= 0 ? '+' : '') . number_format($avgG, 1) . '%';
                                            } elseif ($rowDef['format'] === 'percentage') {
                                                echo number_format((float)$avgVal, 1) . '%';
                                            } elseif ($rowDef['format'] === 'currency') {
                                                echo htmlspecialchars($currency) . number_format((float)$avgVal, 0);
                                            } else {
                                                echo number_format((float)$avgVal, 0);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
