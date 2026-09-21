                <!-- Unpaid Invoices Compact KPI Ribbon (Matches Invoice List Density) -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Receivables Due</span>
                        <span class="metric-pill-val" style="color: #0f172a;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['grand_gross_due'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Active Receivables Balance</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Unpaid Invoices</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo number_format($unpaidSummary['total_unpaid_invoices'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Across <?php echo number_format($unpaidSummary['total_customers'] ?? 0); ?> Debtor Accounts</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Debtor Accounts</span>
                        <span class="metric-pill-val" style="color: #6366f1;"><?php echo number_format($unpaidSummary['total_customers'] ?? 0); ?></span>
                        <span class="metric-pill-sub">Clients with Open Balance</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Critical Overdue (&gt; 90d)</span>
                        <span class="metric-pill-val" style="color: #dc2626;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['critical_amount'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub" style="color: #dc2626; font-weight: 600;"><?php echo number_format($unpaidSummary['critical_count'] ?? 0); ?> High-Risk Invoices</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Current Normal (&le; 30d)</span>
                        <span class="metric-pill-val" style="color: #16a34a;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['current_amount'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub" style="color: #16a34a;"><?php echo number_format($unpaidSummary['current_count'] ?? 0); ?> Standard Cycle Invoices</span>
                    </div>
                </div>

                <!-- Secondary Quick Filter Toolbar (design.md Component 3) -->
                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 11px; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none;">
                    <input type="hidden" name="type" value="unpaid_invoices">

                    <!-- Search Input -->
                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                        <span class="cmd-label">Search:</span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Customer, inv #, PO..." class="cmd-input" style="width: 150px;">
                    </div>

                    <!-- Aging Bracket Filter -->
                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                        <span class="cmd-label">Aging:</span>
                        <select name="aging_bracket" class="cmd-select" style="max-width: 130px;" onchange="this.form.submit()">
                            <option value="all" <?php echo ($agingBracket ?? 'all') === 'all' ? 'selected' : ''; ?>>All Aging Periods</option>
                            <option value="current" <?php echo ($agingBracket ?? '') === 'current' ? 'selected' : ''; ?>>Current (&le; 30 Days)</option>
                            <option value="30_60" <?php echo ($agingBracket ?? '') === '30_60' ? 'selected' : ''; ?>>31–60 Days</option>
                            <option value="60_90" <?php echo ($agingBracket ?? '') === '60_90' ? 'selected' : ''; ?>>61–90 Days</option>
                            <option value="over_90" <?php echo ($agingBracket ?? '') === 'over_90' ? 'selected' : ''; ?>>Critical (&gt; 90 Days)</option>
                        </select>
                    </div>

                    <!-- Channel / Customer Type Filter -->
                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                        <span class="cmd-label">Channel:</span>
                        <select name="customer_type" class="cmd-select" style="max-width: 105px;" onchange="this.form.submit()">
                            <option value="">All Channels</option>
                            <option value="Partner" <?php echo ($customer_type ?? '') === 'Partner' ? 'selected' : ''; ?>>Partners Only</option>
                            <option value="End Customer" <?php echo ($customer_type ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customers</option>
                        </select>
                    </div>

                    <!-- Sales Rep Filter -->
                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                        <span class="cmd-label">Rep:</span>
                        <select name="rep_code" class="cmd-select" style="max-width: 110px;" onchange="this.form.submit()">
                            <option value="">All Reps</option>
                            <?php foreach($salesReps as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['rep_code']); ?>" <?php echo ($rep_code ?? '') === $r['rep_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['rep_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sort Options -->
                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                        <span class="cmd-label">Sort:</span>
                        <select name="sort" class="cmd-select" style="max-width: 145px;" onchange="this.form.submit()">
                            <option value="customer_asc" <?php echo ($sort ?? 'customer_asc') === 'customer_asc' ? 'selected' : ''; ?>>Customer (A &rarr; Z)</option>
                            <option value="customer_desc" <?php echo ($sort ?? '') === 'customer_desc' ? 'selected' : ''; ?>>Customer (Z &rarr; A)</option>
                            <option value="balance_desc" <?php echo ($sort ?? '') === 'balance_desc' ? 'selected' : ''; ?>>Balance (Highest First)</option>
                            <option value="balance_asc" <?php echo ($sort ?? '') === 'balance_asc' ? 'selected' : ''; ?>>Balance (Lowest First)</option>
                            <option value="aging_desc" <?php echo ($sort ?? '') === 'aging_desc' ? 'selected' : ''; ?>>Aging (Oldest First)</option>
                            <option value="count_desc" <?php echo ($sort ?? '') === 'count_desc' ? 'selected' : ''; ?>>Invoice Count (High to Low)</option>
                        </select>
                    </div>

                    <button type="submit" class="cmd-btn cmd-btn-primary" style="height: 26px; padding: 0 8px; font-size: 11px; flex-shrink: 0;" title="Apply search and filters">
                        <i class="icon-filter"></i> Apply
                    </button>

                    <?php if (!empty($search) || ($agingBracket ?? 'all') !== 'all' || !empty($customer_type) || !empty($rep_code) || ($sort ?? 'customer_asc') !== 'customer_asc'): ?>
                        <a href="reports.php?type=unpaid_invoices" class="cmd-btn" style="height: 26px; padding: 0 8px; font-size: 11px; flex-shrink: 0;" title="Reset to defaults">Reset</a>
                    <?php endif; ?>
                </form>

                <!-- Unpaid Customers List & Context Strip -->
                <div id="unpaidInvoicesContainer">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 11.5px;">
                        <span style="font-weight: 700; color: var(--text-main);">
                            Showing <strong><?php echo number_format($unpaidTotal ?? 0); ?></strong> debtor accounts 
                            (Page <?php echo $p; ?> of <?php echo $unpaidPages; ?>)
                            <?php if (!empty($search)): ?>
                                matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                        </span>
                        <span style="color: var(--text-muted);">
                            Active Receivables: <strong style="color: #0f172a; font-family: 'Inter Tight', sans-serif; font-variant-numeric: tabular-nums;"><?php echo htmlspecialchars($currency) . number_format($unpaidSummary['grand_gross_due'] ?? 0, 0); ?></strong>
                        </span>
                    </div>

                    <?php if (empty($unpaidCustomers)): ?>
                        <div class="card" style="text-align: center; padding: 50px 20px; border-radius: var(--radius-md); border: 1px dashed var(--border-color); background: #ffffff;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: #ecfdf5; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                                <i class="icon-check-circle" style="font-size: 24px; color: #10b981;"></i>
                            </div>
                            <h3 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700; color: var(--text-main);">All Accounts Settled</h3>
                            <p style="color: var(--text-muted); font-size: 12px; max-width: 420px; margin: 0 auto;">
                                No outstanding unpaid invoices match the active filter criteria.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($unpaidCustomers as $cust): 
                            $badgeClass = $cust['badge_class'] ?? 'secondary';
                            $riskBadge = $cust['risk_badge'] ?? 'CURRENT';
                            $invoices = $cust['invoices'] ?? [];
                        ?>
                        <div class="card" style="margin-bottom: 10px; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; padding: 0; background: #ffffff;">
                            <!-- Company Summary Header Banner -->
                            <div style="padding: 7px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                <!-- Left: Customer Identity & Channel -->
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <a href="customer_report.php?name=<?php echo urlencode($cust['customer_name']); ?>" style="color: var(--text-main); font-size: 13px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 5px;" title="View client billing history">
                                        <i class="icon-building-2" style="font-size: 13px; color: var(--primary);"></i>
                                        <?php echo htmlspecialchars($cust['customer_name']); ?>
                                    </a>
                                    <span class="dense-badge" style="background: #e0e7ff; color: #4338ca;">
                                        <?php echo htmlspecialchars($cust['customer_type']); ?>
                                    </span>
                                    <?php if (!empty($cust['rep_name'])): ?>
                                        <span style="font-size: 10.5px; color: #64748b;">
                                            Rep: <strong style="color: #334155;"><?php echo htmlspecialchars($cust['rep_name']); ?></strong>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Right: Aggregates, Aging & Balance Due -->
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <!-- Invoices Count Badge -->
                                    <span class="dense-badge" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                                        <?php echo $cust['unpaid_invoices_count']; ?> <?php echo $cust['unpaid_invoices_count'] === 1 ? 'Invoice' : 'Invoices'; ?>
                                    </span>

                                    <!-- Aging Risk Badge -->
                                    <span class="dense-badge dense-badge-<?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($riskBadge); ?> &bull; <?php echo $cust['max_aging_days']; ?>d Overdue
                                    </span>

                                    <!-- Outstanding Amount Pill -->
                                    <div style="background: #0f172a; color: #ffffff; font-size: 12px; font-weight: 800; padding: 2px 8px; border-radius: 4px; font-variant-numeric: tabular-nums; display: inline-flex; align-items: center; gap: 4px;">
                                        <span style="color: #94a3b8; font-size: 10px; font-weight: 600;">DUE:</span>
                                        <span><?php echo htmlspecialchars($currency) . number_format($cust['total_gross_due'] ?? 0, 0); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Embedded Individual Invoices Table (rational-table) -->
                            <div style="overflow-x: auto;">
                                <table class="rational-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 105px;">Invoice #</th>
                                            <th style="width: 80px;">Date</th>
                                            <th style="width: 110px;">Aging / Status</th>
                                            <th style="width: 90px;">PO Number</th>
                                            <th>Billed Items Summary</th>
                                            <th class="text-right" style="width: 95px;">Net Base</th>
                                            <th class="text-right" style="width: 85px;">18% VAT</th>
                                            <th class="text-right" style="width: 110px;">Gross Total</th>
                                            <th class="text-center" style="width: 55px;">Audit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $inv): ?>
                                        <tr onclick="openInvoiceDetails('<?php echo htmlspecialchars($inv['invoice_number']); ?>')">
                                            <td>
                                                <span class="dense-doc-num"><?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                                            </td>
                                            <td style="color: var(--text-muted); font-size: 11px;">
                                                <?php echo htmlspecialchars($inv['invoice_date']); ?>
                                            </td>
                                            <td>
                                                <span class="dense-badge dense-badge-<?php echo $inv['aging_badge']; ?>">
                                                    <?php echo htmlspecialchars($inv['aging_label']); ?>
                                                </span>
                                            </td>
                                            <td style="color: #475569; font-size: 10.5px;">
                                                <?php if (!empty($inv['po_number'])): ?>
                                                    <span style="background: #f1f5f9; padding: 1px 4px; border-radius: 3px; color: #334155;"><?php echo htmlspecialchars(substr($inv['po_number'], 0, 14)); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #cbd5e1;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 4px; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($inv['items_summary']); ?>">
                                                    <span style="font-size: 10px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 0 4px; border-radius: 3px; flex-shrink: 0;"><?php echo $inv['line_count']; ?> itm</span>
                                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: var(--text-main);"><?php echo htmlspecialchars($inv['items_summary']); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-right dense-num" style="color: #475569;">
                                                <?php echo number_format((float)$inv['base_value'], 0); ?>
                                            </td>
                                            <td class="text-right dense-num" style="color: #64748b;">
                                                <?php echo number_format((float)$inv['vat_component'], 0); ?>
                                            </td>
                                            <td class="text-right dense-num-bold dense-num">
                                                <?php echo htmlspecialchars($currency) . number_format((float)$inv['total_amount'], 0); ?>
                                            </td>
                                            <td class="text-center" onclick="event.stopPropagation()">
                                                <button type="button" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;" onclick="openInvoiceDetails('<?php echo htmlspecialchars($inv['invoice_number']); ?>')">
                                                    Audit
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Floating Pagination Rail (design.md Component 7) -->
                        <?php 
                        $unpaidBaseUrl = "reports.php?type=unpaid_invoices&search=" . urlencode($search ?? '') . "&aging_bracket=" . urlencode($agingBracket ?? 'all') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&sort=" . urlencode($sort ?? 'customer_asc');
                        echo renderPaginationRail($p, $unpaidPages, $unpaidTotal, $limit, $unpaidBaseUrl, 'debtor accounts');
                        ?>
                    <?php endif; ?>
                </div>

