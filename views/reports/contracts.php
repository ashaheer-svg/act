                <!-- 4. Time-Based Expiring Contracts & Recurring Invoices -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Contracts In Scope</span>
                        <span class="metric-pill-val"><?php echo number_format($contractsSummary['total_contracts'] ?? 0); ?></span>
                        <span class="metric-pill-sub">In selected expiration window</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Renewal Pipeline</span>
                        <span class="metric-pill-val" style="color: #2563eb;"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['total_opportunity_value'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Total contract recurring base</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Post Due (Overdue)</span>
                        <span class="metric-pill-val" style="color: #b91c1c;"><?php echo number_format($contractsSummary['overdue_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['overdue_value'] ?? 0, 0); ?> (Grace Period)</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Due Soon (≤ 30 Days)</span>
                        <span class="metric-pill-val" style="color: #b45309;"><?php echo number_format($contractsSummary['due_30d_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['due_30d_value'] ?? 0, 0); ?> (Urgent Outreach)</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Due 31 to 90 Days</span>
                        <span class="metric-pill-val" style="color: #15803d;"><?php echo number_format($contractsSummary['due_90d_count'] ?? 0); ?></span>
                        <span class="metric-pill-sub"><?php echo htmlspecialchars($currency) . number_format($contractsSummary['due_90d_value'] ?? 0, 0); ?> (Upcoming Pipeline)</span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 95px;">Invoice #</th>
                                    <th>Customer Organization &amp; End Client</th>
                                    <th>Service Offering / Scope</th>
                                    <th style="width: 80px;">Category</th>
                                    <th style="width: 70px;">Term</th>
                                    <th style="width: 85px;">Period Start</th>
                                    <th style="width: 95px; background: #eff6ff; color: #1e40af;">Expiring Date ▾</th>
                                    <th class="text-center" style="width: 120px;">Days Remaining</th>
                                    <th class="text-right" style="width: 110px;">Opportunity (LKR)</th>
                                    <th class="text-center" style="width: 55px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contractsData)): ?>
                                    <tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-muted);">No expiring contracts or time-based invoices found matching criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($contractsData as $row): 
                                        $days = (int)($row['days_remaining'] ?? 0);
                                        $cColor = '#15803d'; $cBg = '#ecfdf5'; $daysLabel = "Due in {$days}d";
                                        if ($days < 0) {
                                            $cColor = '#b91c1c'; $cBg = '#fee2e2';
                                            $daysLabel = "Expired " . abs($days) . "d ago";
                                        } elseif ($days === 0) {
                                            $cColor = '#b45309'; $cBg = '#fef3c7';
                                            $daysLabel = "Due Today";
                                        } elseif ($days <= 30) {
                                            $cColor = '#b45309'; $cBg = '#fef3c7';
                                            $daysLabel = "Due in {$days}d";
                                        }

                                        $catLabel = $row['category_label'] ?? 'General';
                                        $catStyle = "background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;";
                                        if ($catLabel === 'Acronis') {
                                            $catStyle = "background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe;";
                                        } elseif ($catLabel === 'MA / SLA') {
                                            $catStyle = "background: #f5f3ff; color: #6d28d9; border: 1px solid #ede9fe;";
                                        } elseif ($catLabel === 'Hosting') {
                                            $catStyle = "background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;";
                                        } elseif ($catLabel === 'License') {
                                            $catStyle = "background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5;";
                                        }
                                    ?>
                                    <tr style="<?php echo $days < 0 ? 'background: #fffafa;' : ($days <= 30 ? 'background: #fffdf5;' : ''); ?>">
                                        <td>
                                            <span class="dense-doc-num" style="cursor: pointer; color: #2563eb; font-weight: 600;" onclick="openInvoiceDetails('<?php echo htmlspecialchars($row['invoice_number']); ?>')" title="Open invoice details">
                                                <?php echo htmlspecialchars($row['invoice_number']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                            </a>
                                            <?php if (!empty($row['end_customer'])): ?>
                                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                    <i class="icon-arrow-right" style="font-size: 10px;"></i> End Client: <?php echo htmlspecialchars($row['end_customer']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #334155; font-size: 12px;">
                                            <?php echo htmlspecialchars($row['software_name']); ?>
                                        </td>
                                        <td>
                                            <span class="dense-badge" style="<?php echo $catStyle; ?> font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                                <?php echo htmlspecialchars($catLabel); ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo !empty($row['term_months']) ? $row['term_months'] . ' Mo' : '—'; ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo htmlspecialchars($row['period_start_date'] ?? '—'); ?>
                                        </td>
                                        <td style="background: #eff6ff; font-family: monospace; font-size: 11px; font-weight: 700; color: <?php echo $cColor; ?>;">
                                            <?php echo htmlspecialchars($row['period_end_date']); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="dense-badge" style="background: <?php echo $cBg; ?>; color: <?php echo $cColor; ?>; font-weight: 800; font-size: 11px;">
                                                <?php echo $daysLabel; ?>
                                            </span>
                                        </td>
                                        <td class="text-right dense-num-bold">
                                            <?php echo number_format($row['renewal_opportunity_value'] ?? 0, 0); ?>
                                        </td>
                                        <td class="text-center" style="white-space: nowrap;">
                                            <button type="button" class="cmd-btn" style="padding: 2px 6px;" onclick="openInvoiceDetails('<?php echo htmlspecialchars($row['invoice_number']); ?>')" title="Inspect Invoice Line Items &amp; Serials">
                                                <i class="icon-file-text"></i>
                                            </button>
                                            <?php if (!isset($auth) || $auth->canAccessReport('edit_invoices')): ?>
                                            <a href="invoice_edit.php?inv=<?php echo urlencode($row['invoice_number']); ?>" class="cmd-btn" style="padding: 2px 6px; color: var(--primary); text-decoration: none;" title="Edit Invoice Details &amp; Contract">
                                                <i class="icon-edit-3"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $contractsBaseUrl = "reports.php?type=contracts&range=" . urlencode($range ?? 'pm_90d') . "&category=" . urlencode($category ?? 'all') . "&status=" . urlencode($status ?? 'all') . "&sort=" . urlencode($sort ?? 'expiry_asc') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $contractsPages, $contractsTotal, $limit, $contractsBaseUrl, 'contracts');
                    ?>
                </div>

