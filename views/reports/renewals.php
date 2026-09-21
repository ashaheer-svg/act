
                <!-- Renewals KPI Ribbon -->
                <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
                    <div class="metric-card">
                        <div class="metric-label">Subscriptions & Licenses</div>
                        <div class="metric-value"><?php echo number_format($renewalKpis['total_subscriptions'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Managed Recurring Offerings
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total Licensed Seats</div>
                        <div class="metric-value" style="color: var(--primary);"><?php echo number_format($renewalKpis['total_seats'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            User / Endpoint Licenses
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Renewals Due ≤ 60 Days</div>
                        <div class="metric-value" style="color: #b45309;"><?php echo number_format($renewalKpis['due_soon_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Actionable Pipeline
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Upcoming Pipeline Value</div>
                        <div class="metric-value price-tag"><?php echo htmlspecialchars($currency) . number_format($renewalKpis['pipeline_value'] ?? 0, 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Recurring ARR at Stake
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expired / Lapsed</div>
                        <div class="metric-value" style="color: #b91c1c;"><?php echo number_format($renewalKpis['expired_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Win-back Opportunities
                        </div>
                    </div>
                </div>

                <!-- Monthly Renewal Calendar Timeline -->
                <?php if (!empty($renewalCalendar)): ?>
                <div class="card" style="margin-bottom: 25px;">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <i class="icon-calendar" style="color: var(--primary);"></i> 12-Month SaaS & Subscription Renewal Outlook
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px;">
                        <?php foreach($renewalCalendar as $cal): ?>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
                            <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">
                                <?php echo date('M Y', strtotime($cal['renewal_month'] . '-01')); ?>
                            </div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--primary); margin: 4px 0;">
                                <?php echo $cal['count']; ?> <span style="font-size: 10px; color: var(--text-muted); font-weight: 500;">contracts</span>
                            </div>
                            <div style="font-size: 11px; font-weight: 700; color: #15803d;">
                                <?php echo htmlspecialchars($currency) . number_format($cal['renewal_value'] ?? 0, 0); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Subscriptions Pipeline Table -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2 style="margin: 0; font-size: 22px;">Software Licenses & SaaS Renewals Pipeline</h2>
                            <p style="color: var(--text-muted); font-size: 14px; margin: 4px 0 0 0;">
                                Track license seats, service start/end dates, and estimated contract renewal opportunity values.
                            </p>
                        </div>
                    </div>

                    <!-- Filter Controls -->
                    <form method="GET" action="reports.php" class="filter-controls" style="margin-bottom: 25px; background: #f8fafc; padding: 18px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                        <input type="hidden" name="type" value="renewals">

                        <div class="filter-group" style="flex: 1; min-width: 220px; margin: 0;">
                            <span class="filter-label">Search Software / Customer / Invoice</span>
                            <input type="text" name="search" class="filter-select" placeholder="Acronis, ESET, customer..." value="<?php echo htmlspecialchars($renewalSearch ?? ''); ?>" style="width: 100%;">
                        </div>

                        <div class="filter-group" style="margin: 0;">
                            <span class="filter-label">Renewal Status</span>
                            <select name="status" class="filter-select" style="min-width: 160px;">
                                <option value="all" <?php echo ($renewalStatus ?? 'all') === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="ACTIVE" <?php echo ($renewalStatus ?? '') === 'ACTIVE' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="DUE_SOON" <?php echo ($renewalStatus ?? '') === 'DUE_SOON' ? 'selected' : ''; ?>>Due Soon (≤ 60 Days)</option>
                                <option value="EXPIRED" <?php echo ($renewalStatus ?? '') === 'EXPIRED' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-view" style="padding: 10px 18px;"><i class="icon-filter"></i> Filter</button>
                            <?php if (!empty($renewalSearch) || ($renewalStatus ?? 'all') !== 'all'): ?>
                                <a href="reports.php?type=renewals" class="btn-view" style="background: #e2e8f0; color: #475569; text-decoration: none; padding: 10px 14px;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Table -->
                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">Software / Service Offering</th>
                                    <th>Edition / Tier</th>
                                    <th style="min-width: 180px;">Customer</th>
                                    <th>Invoice #</th>
                                    <th class="text-center">Seats</th>
                                    <th>Coverage Period</th>
                                    <th class="text-right">Opportunity Value</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($renewalSubs)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                                        <i class="icon-refresh-cw" style="font-size: 36px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                                        No subscription or SaaS contracts found. Run the AI Entity Extractor from <a href="settings.php" style="color: var(--primary); font-weight: 700;">Settings</a> to identify software and recurring service periods.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach($renewalSubs as $sub): 
                                    $st = $sub['dynamic_status'];
                                    $stBadgeBg = '#ecfdf5';
                                    $stBadgeColor = '#15803d';
                                    $stLabel = 'Active (' . $sub['days_remaining'] . 'd)';

                                    if ($st === 'EXPIRED') {
                                        $stBadgeBg = '#fee2e2';
                                        $stBadgeColor = '#b91c1c';
                                        $stLabel = 'Expired';
                                    } elseif ($st === 'DUE_SOON') {
                                        $stBadgeBg = '#fef3c7';
                                        $stBadgeColor = '#b45309';
                                        $stLabel = 'Due in ' . $sub['days_remaining'] . 'd';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 13px;">
                                            <?php echo htmlspecialchars($sub['software_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569;">
                                            <?php echo htmlspecialchars($sub['edition_tier'] ?: 'Standard'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="customer_report.php?name=<?php echo urlencode($sub['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600; font-size: 13px;">
                                            <?php echo htmlspecialchars($sub['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 13px;">
                                            <?php echo htmlspecialchars($sub['invoice_number']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center" style="font-size: 13px; font-weight: 800; color: #334155;">
                                        <?php echo number_format($sub['license_seats'] ?? 1); ?>
                                    </td>
                                    <td style="font-size: 12px; color: #475569;">
                                        <?php echo htmlspecialchars($sub['period_start_date'] ?: '—'); ?> → <strong><?php echo htmlspecialchars($sub['period_end_date'] ?: '—'); ?></strong>
                                    </td>
                                    <td class="text-right price-tag" style="font-size: 13px; font-weight: 800;">
                                        <?php echo htmlspecialchars($currency) . number_format($sub['renewal_opportunity_value'] ?? 0, 0); ?>
                                    </td>
                                    <td class="text-center">
                                        <span style="display: inline-block; padding: 3px 10px; border-radius: 14px; background: <?php echo $stBadgeBg; ?>; color: <?php echo $stBadgeColor; ?>; font-size: 10px; font-weight: 800; text-transform: uppercase;">
                                            <?php echo $stLabel; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($sub['invoice_number']); ?>')" style="padding: 5px 10px; font-size: 11px;">
                                            <i class="icon-file-text"></i> Invoice
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (Concept B: Modern Floating Rail) -->
                    <?php 
                    $rbUrl = "reports.php?type=renewals&status=" . urlencode($renewalStatus ?? 'all') . "&search=" . urlencode($renewalSearch ?? '');
                    echo renderPaginationRail($p, $renewalPages, $renewalTotal, $limit, $rbUrl, 'subscriptions');
                    ?>
                </div>
