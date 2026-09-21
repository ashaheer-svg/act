                <!-- High-Density Financial Metrics Ribbon -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Invoices</span>
                        <span class="metric-pill-val"><?php echo number_format($invoiceTotal); ?></span>
                        <span class="metric-pill-sub"><?php echo number_format($invoiceSummary['unique_customers'] ?? 0); ?> Unique Clients</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Billed</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($invoiceSummary['grand_gross_revenue'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub"><?php echo number_format($invoiceSummary['grand_total_qty'] ?? 0); ?> Units Dispatched</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Net Base (Pre-VAT)</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($invoiceSummary['grand_base_value'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Core Recognized Sales</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Statutory 18% VAT</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo htmlspecialchars($currency) . number_format($invoiceSummary['grand_total_vat'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">IRD Tax Liability</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Settlement Realization</span>
                        <span class="metric-pill-val" style="color: #15803d; font-size: 14px;">
                            Settled: <?php echo htmlspecialchars($currency) . number_format($invoiceSummary['settled_amount'] ?? 0, 0); ?>
                        </span>
                        <span class="metric-pill-sub" style="color: #b91c1c; font-weight: 600;">
                            Unpaid: <?php echo htmlspecialchars($currency) . number_format($invoiceSummary['unpaid_amount'] ?? 0, 0); ?> (<?php echo number_format($invoiceSummary['unpaid_invoices_count'] ?? 0); ?> inv)
                        </span>
                    </div>
                </div>

                <!-- Secondary Quick Filters -->
                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="invoices">
                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? 'all'); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Brand:</span>
                        <select name="brand" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Brands</option>
                            <?php foreach($uniqueBrands as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo ($brand ?? '') === $b['product_category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['product_category']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Category:</span>
                        <select name="customer_type" class="cmd-select" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="Partner" <?php echo ($customer_type ?? '') === 'Partner' ? 'selected' : ''; ?>>Partners Only</option>
                            <option value="End Customer" <?php echo ($customer_type ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customers</option>
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

                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span class="cmd-label">Sort:</span>
                        <select name="sort" class="cmd-select" onchange="this.form.submit()">
                            <option value="invoice_date_desc" <?php echo ($sort ?? '') === 'invoice_date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="invoice_date_asc" <?php echo ($sort ?? '') === 'invoice_date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="amount_desc" <?php echo ($sort ?? '') === 'amount_desc' ? 'selected' : ''; ?>>Amount (High to Low)</option>
                            <option value="amount_asc" <?php echo ($sort ?? '') === 'amount_asc' ? 'selected' : ''; ?>>Amount (Low to High)</option>
                            <option value="invoice_number_asc" <?php echo ($sort ?? '') === 'invoice_number_asc' ? 'selected' : ''; ?>>Invoice # (A-Z)</option>
                        </select>
                    </div>

                    <?php if (!empty($brand) || !empty($customer_type) || !empty($rep_code) || ($sort ?? '') !== 'invoice_date_desc'): ?>
                        <a href="reports.php?type=invoices&year=<?php echo urlencode($year); ?>&month=<?php echo urlencode($month); ?>&status=<?php echo urlencode($status ?? 'all'); ?>&search=<?php echo urlencode($search ?? ''); ?>" class="cmd-btn" style="height: 24px; font-size: 10.5px;">Reset Filters</a>
                    <?php endif; ?>
                </form>

                <!-- Split Master-Detail Layout -->
                <div class="split-layout" id="splitLayout">
                    <!-- Left Grid: High-Density Table -->
                    <div class="split-grid" id="splitGrid">
                        <div class="split-table-wrapper">
                            <table class="rational-table" id="invoicesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 76px;">Date</th>
                                        <th style="width: 98px;">Invoice #</th>
                                        <th>Customer</th>
                                        <th style="width: 44px;">Rep</th>
                                        <th style="width: 80px;">PO #</th>
                                        <th style="width: 140px;">Identified Data</th>
                                        <th class="text-right" style="width: 92px;">Base Net</th>
                                        <th class="text-right" style="width: 84px;">18% VAT</th>
                                        <th class="text-right" style="width: 108px;"><div style="display: flex; justify-content: flex-end; align-items: center; gap: 4px;"><span>Gross Total</span><span style="width: 30px; flex-shrink: 0;"></span></div></th>
                                        <th class="text-center" style="width: 68px;">Status</th>
                                        <th class="text-center" style="width: 58px;">Audit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($invoiceData)): ?>
                                    <tr>
                                        <td colspan="11" style="text-align: center; padding: 40px 15px; color: var(--text-muted);">
                                            No commercial invoices found for the active criteria. Try adjusting the search keywords or year filter.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($invoiceData as $row): 
                                        $isCredit = strcasecmp($row['invoice_type'], 'Credit Memo') === 0;
                                        $isSettled = !empty($row['paid_date']);
                                        $hasSerials = !empty($row['has_serials']);
                                        $taxTreat = $row['invoice_vat_treatment'] ?? '';
                                    ?>
                                    <tr onclick="selectInvoiceRow('<?php echo htmlspecialchars($row['invoice_number']); ?>', this)">
                                        <td style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo htmlspecialchars($row['invoice_date']); ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <span class="dense-doc-num">
                                                    <?php echo htmlspecialchars($row['invoice_number']); ?>
                                                </span>
                                                <?php if ($isCredit): ?>
                                                    <span class="dense-badge dense-badge-credit">CR</span>
                                                <?php endif; ?>
                                                <?php if ($hasSerials): ?>
                                                    <span class="dense-badge dense-badge-sn" title="Hardware Serial Numbers Registered">S/N</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;">
                                                <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($row['end_customer'])): ?>
                                                <div style="font-size: 10.5px; color: #047857; font-weight: 600; display: flex; align-items: center; gap: 3px; margin-top: 1px;" title="End Client: <?php echo htmlspecialchars($row['end_customer']); ?>">
                                                    <i class="icon-briefcase" style="font-size: 9px;"></i>
                                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px;"><?php echo htmlspecialchars($row['end_customer']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 11px; color: #475569;" title="<?php echo htmlspecialchars($row['rep_name']); ?>">
                                                <?php echo htmlspecialchars($row['sales_rep_code'] ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['po_number'])): ?>
                                                <span style="font-size: 10.5px; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; color: #334155;">
                                                    <?php echo htmlspecialchars(substr($row['po_number'], 0, 14)); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 4px; flex-wrap: nowrap;">
                                                <span style="font-size: 10.5px; font-weight: 600; color: #475569;">
                                                    <?php echo $row['items_count'] ?: $row['line_count']; ?> itm
                                                </span>
                                                <?php if (!empty($row['hardware_count'])): ?>
                                                    <span class="dense-badge dense-badge-hw" title="<?php echo $row['hardware_count']; ?> hardware units (<?php echo $row['serials_count']; ?> serialized)">
                                                        HW:<?php echo $row['hardware_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['subscriptions_count'])): ?>
                                                    <span class="dense-badge dense-badge-ma" title="<?php echo $row['subscriptions_count']; ?> software subscriptions / maintenance agreements">
                                                        MA:<?php echo $row['subscriptions_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right dense-num" style="color: #475569;">
                                            <?php echo number_format($row['total_base_value'], 0); ?>
                                        </td>
                                        <td class="text-right dense-num" style="color: #64748b;">
                                            <?php echo number_format($row['total_vat_component'], 0); ?>
                                        </td>
                                        <td class="text-right dense-num-bold dense-num" style="white-space: nowrap;">
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                                <span style="font-variant-numeric: tabular-nums;"><?php echo number_format($row['total_gross_amount'], 0); ?></span>
                                                <span style="display: inline-block; width: 30px; text-align: center; flex-shrink: 0;">
                                                    <?php if ($taxTreat === 'PLUS_VAT' || $taxTreat === 'VAT_INCLUSIVE'): ?>
                                                        <span class="dense-badge dense-badge-plusvat" title="Plus VAT (Statutory breakdown)" style="font-size: 8px; padding: 1px 2px; width: 100%; box-sizing: border-box; text-align: center; display: inline-block;">+VAT</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="dense-badge <?php echo $isSettled ? 'dense-badge-settled' : 'dense-badge-unpaid'; ?>">
                                                <?php echo $isSettled ? 'Paid' : 'Unpaid'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center" style="white-space: nowrap;">
                                            <button type="button" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;" onclick="event.stopPropagation(); selectInvoiceRow('<?php echo htmlspecialchars($row['invoice_number']); ?>', this.closest('tr'))">
                                                Audit
                                            </button>
                                            <?php if (!isset($auth) || $auth->canAccessReport('edit_invoices')): ?>
                                            <a href="invoice_edit.php?inv=<?php echo urlencode($row['invoice_number']); ?>" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center;" onclick="event.stopPropagation();" title="Edit Commercial Invoice">
                                                <i class="icon-edit-3" style="font-size: 10px; margin-right: 2px;"></i> Edit
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Split Table Pagination Footer (Concept B: Modern Floating Rail) -->
                        <?php 
                        $baseUrl = "reports.php?type=invoices&year=" . urlencode($year) . "&month=" . urlencode($month) . "&status=" . urlencode($status) . "&search=" . urlencode($search) . "&brand=" . urlencode($brand ?? '') . "&customer_type=" . urlencode($customer_type ?? '') . "&rep_code=" . urlencode($rep_code ?? '') . "&sort=" . urlencode($sort ?? '');
                        echo renderPaginationRail($p, $invoicePages, $invoiceTotal, $limit, $baseUrl, 'invoices');
                        ?>
                    </div>

                    <!-- Right Drawer: Master-Detail Side Audit Inspector -->
                    <div class="split-drawer drawer-collapsed" id="sideAuditDrawer">
                        <div class="drawer-header">
                            <div class="drawer-title-area">
                                <i class="icon-file-text" style="color: #818cf8; font-size: 13px;"></i>
                                <span class="drawer-title" id="drawerTitle">Invoice Audit</span>
                            </div>
                            <div class="drawer-controls">
                                <a id="drawerEditLink" href="#" class="drawer-ctrl-btn" style="color: #4f46e5; text-decoration: none; display: none; align-items: center; gap: 4px;" title="Edit Commercial Invoice">
                                    <i class="icon-edit-3"></i> Edit
                                </a>
                                <button type="button" class="drawer-ctrl-btn" onclick="toggleDrawerFullscreen()" title="Toggle Fullscreen Inspector">
                                    <i class="icon-maximize-2" id="drawerExpandIcon"></i> Full
                                </button>
                                <button type="button" class="drawer-ctrl-btn" onclick="closeDrawer()" title="Close Drawer">
                                    <i class="icon-x"></i>
                                </button>
                            </div>
                        </div>
                        <div class="drawer-body" id="drawerBody">
                            <div style="text-align: center; padding: 40px 15px; color: var(--text-muted); font-size: 12px;">
                                Select an invoice row to inspect line items, serial numbers, and payment reconciliation.
                            </div>
                        </div>
                    </div>
                </div>

