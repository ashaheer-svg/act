                <!-- Warranty KPI Ribbon -->
                <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
                    <div class="metric-card">

                        <div class="metric-label">Tracked Serial Numbers</div>
                        <div class="metric-value"><?php echo number_format($warrantyKpis['total_serials'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Registered Serialized Units
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Active Warranty</div>
                        <div class="metric-value" style="color: #10b981;"><?php echo number_format($warrantyKpis['active_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Under Manufacturer Warranty
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expiring Soon (≤ 60 Days)</div>
                        <div class="metric-value" style="color: #f59e0b;"><?php echo number_format($warrantyKpis['expiring_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Immediate SLA / Renewal Target
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Expired Warranties</div>
                        <div class="metric-value" style="color: #ef4444;"><?php echo number_format($warrantyKpis['expired_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            Out of Warranty / Refresh Candidates
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Maintenance Agreements</div>
                        <div class="metric-value" style="color: #6366f1;"><?php echo number_format($warrantyKpis['maintenance_count'] ?? 0); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
                            SLA & Maintenance Contracts
                        </div>
                    </div>
                </div>

                <!-- Warranty Lookup Hero & Interactive Search -->
                <div class="card" style="margin-bottom: 25px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-main);"><i class="icon-shield-check" style="color: #10b981;"></i> Serial Number &amp; Warranty Lifecycle Intelligence</h2>
                            <span class="sb-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 11px; padding: 2px 8px;">Live Lookup</span>
                        </div>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 0 0;">
                            Search by full or partial serial number to reveal all historical invoice distributions, active warranty countdowns, and customer maintenance contracts.
                        </p>
                    </div>

                    <!-- Search Input Box with Typeahead & Quick Chips -->
                    <form method="GET" action="reports.php" id="warrantySearchForm" onsubmit="return handleWarrantySubmit(event);" style="margin-bottom: 15px;">
                        <input type="hidden" name="type" value="warranties">
                        <input type="hidden" name="status" id="warrantyStatusField" value="<?php echo htmlspecialchars($warrantyStatus ?? 'all'); ?>">

                        <div style="position: relative; display: flex; gap: 10px; align-items: center;">
                            <div style="position: relative; flex: 1;">
                                <i class="icon-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 18px; color: #94a3b8;"></i>
                                <input type="text" 
                                       id="warrantySearchInput" 
                                       name="search" 
                                       class="form-control" 
                                       placeholder="Enter full or partial S/N (e.g. 2110RXRC, 20C0SKRC, 0W200116, SYN) or customer name..." 
                                       value="<?php echo htmlspecialchars($warrantySearch ?? ''); ?>" 
                                       autocomplete="off"
                                       style="width: 100%; height: 48px; padding-left: 48px; padding-right: 40px; font-size: 15px; font-weight: 600; border-radius: 8px; border: 2px solid #cbd5e1; transition: all 0.2s; box-sizing: border-box;">
                                <button type="button" id="warrantyClearBtn" onclick="clearWarrantySearch()" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; display: <?php echo !empty($warrantySearch) ? 'block' : 'none'; ?>;" title="Clear Search">×</button>
                            </div>
                            <button type="submit" class="btn-view" style="height: 48px; padding: 0 24px; font-size: 14px; font-weight: 700; background: var(--primary); color: white; border: none; border-radius: 8px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <i class="icon-search"></i> Search S/N
                            </button>
                        </div>
                    </form>

                    <!-- Quick Sample Chips & Status Filters -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                        <!-- Sample Chips -->
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Quick Examples:</span>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('2110RXRC')">2110RXRC <span style="opacity: 0.7; font-size: 10px;">(RS1221RP+)</span></button>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('20C0SKRCXAQ44')">20C0SKRCXAQ44 <span style="opacity: 0.7; font-size: 10px;">(Avian SLA)</span></button>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('0W200116')">0W200116 <span style="opacity: 0.7; font-size: 10px;">(Multi-Inv Switch)</span></button>
                            <button type="button" class="btn-chip" onclick="quickSearchSerial('0P32014')">0P32014 <span style="opacity: 0.7; font-size: 10px;">(BDCOM AP)</span></button>
                        </div>

                        <!-- Status Filter Tabs -->
                        <div style="display: flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 8px;">
                            <?php 
                            $currSt = $warrantyStatus ?? 'all';
                            $statuses = [
                                'all' => 'All Units',
                                'active' => 'Active',
                                'expiring_soon' => 'Expiring Soon',
                                'expired' => 'Expired',
                                'has_maintenance' => 'Has SLA / Contract'
                            ];
                            foreach ($statuses as $stKey => $stLabel):
                                $isActive = ($currSt === $stKey);
                            ?>
                            <button type="button" 
                                    class="st-tab-btn <?php echo $isActive ? 'active' : ''; ?>" 
                                    onclick="filterWarrantyStatus('<?php echo $stKey; ?>')">
                                <?php echo $stLabel; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Results Container (Populated SSR and Updated via AJAX) -->
                <div id="warrantyResultsContainer">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                            Showing <span id="warrantyCountSpan"><?php echo count($warrantyResults); ?></span> matching serialized equipment records
                            <?php if (!empty($warrantySearch)): ?>
                                for "<strong><?php echo htmlspecialchars($warrantySearch); ?></strong>"
                            <?php endif; ?>
                        </span>
                        <span id="warrantyLiveNotice" style="font-size: 12px; color: #10b981; font-weight: 600; display: none;">
                            <i class="icon-refresh-cw" style="animation: spin 1s linear infinite; display: inline-block;"></i> Searching live...
                        </span>
                    </div>

                    <?php if (empty($warrantyResults)): ?>
                        <div class="card" style="text-align: center; padding: 60px 20px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                <i class="icon-shield" style="font-size: 28px; color: #94a3b8;"></i>
                            </div>
                            <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: var(--text-main);">No matching serialized equipment found</h3>
                            <p style="color: var(--text-muted); font-size: 13px; max-width: 500px; margin: 0 auto 20px;">
                                No records matched your search query. Try searching with a partial serial number (e.g. the first 6–8 characters), or click one of the quick examples above.
                            </p>
                            <button type="button" class="btn-view" onclick="quickSearchSerial('2110RXRC')" style="padding: 8px 16px; margin: 0 auto;">
                                View Sample Serial: 2110RXRC
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($warrantyResults as $asset): 
                            $badgeClass = $asset['status_badge_class'] ?? 'secondary';
                            $statusLabel = $asset['status_label'] ?? 'Unknown';
                            $pct = $asset['progress_pct'] ?? 100;
                            $invoices = $asset['invoices'] ?? [];
                            $contracts = $asset['maintenance_contracts'] ?? [];
                        ?>
                        <div class="card warranty-asset-card" style="margin-bottom: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.03); overflow: hidden; padding: 0;">
                            <!-- Asset Header Bar -->
                            <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                                <div style="flex: 1; min-width: 280px;">
                                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                                        <span class="serial-tag" onclick="copyToClipboard('<?php echo htmlspecialchars($asset['serial_number']); ?>', this)" title="Click to copy serial number">
                                            <i class="icon-hash" style="font-size: 11px; opacity: 0.6;"></i>
                                            <strong><?php echo htmlspecialchars($asset['serial_number']); ?></strong>
                                            <i class="icon-copy" style="font-size: 11px; margin-left: 4px; opacity: 0.5;"></i>
                                        </span>
                                        <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0369a1; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($asset['brand'] ?: 'Hardware'); ?>
                                        </span>
                                        <?php if (!empty($asset['model_sku'])): ?>
                                        <span style="font-size: 11px; font-weight: 700; color: #64748b; font-family: monospace;">
                                            SKU: <?php echo htmlspecialchars($asset['model_sku']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['parent_serial_number'])): ?>
                                        <span style="font-size: 11px; color: #64748b;">
                                            Chassis S/N: <strong style="font-family: monospace;"><?php echo htmlspecialchars($asset['parent_serial_number']); ?></strong>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: var(--text-main);">
                                        <?php echo htmlspecialchars($asset['product_name']); ?>
                                    </h3>
                                    <div style="font-size: 13px; color: var(--text-muted);">
                                        Primary Account: 
                                        <a href="customer_report.php?name=<?php echo urlencode($asset['current_customer']); ?>" style="color: var(--primary); font-weight: 700; text-decoration: none;">
                                            <?php echo htmlspecialchars($asset['current_customer']); ?>
                                        </a>
                                        <?php if (!empty($asset['end_customer'])): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; background: #ecfdf5; color: #047857; font-weight: 700; font-size: 11.5px; padding: 2px 8px; border-radius: 4px; border: 1px solid #a7f3d0;">
                                                <i class="icon-briefcase" style="font-size: 10px;"></i> End Client: <?php echo htmlspecialchars($asset['end_customer']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['all_customers']) && $asset['all_customers'] !== $asset['current_customer']): ?>
                                            <span style="font-size: 11px; color: #64748b; margin-left: 6px;">(Also associated: <?php echo htmlspecialchars($asset['all_customers']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Status Badge & Lifecycle Gauge -->
                                <div style="min-width: 220px; text-align: right;">
                                    <div>
                                        <span class="warranty-status-badge badge-<?php echo $badgeClass; ?>">
                                            <span class="pulse-dot"></span>
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                        Warranty: <strong><?php echo $asset['warranty_months'] ? ($asset['warranty_months'] . ' Months') : 'Standard'; ?></strong>
                                        <?php if (!empty($asset['computed_expiry_date'])): ?>
                                            &bull; Expiry: <strong style="color: #334155;"><?php echo htmlspecialchars($asset['computed_expiry_date']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Lifecycle bar -->
                                    <div style="margin-top: 8px; width: 100%; max-width: 220px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-left: auto;" title="<?php echo $pct; ?>% of warranty lifecycle elapsed">
                                        <div style="width: <?php echo $pct; ?>%; height: 100%; background: <?php echo $badgeClass === 'success' ? '#10b981' : ($badgeClass === 'warning' ? '#f59e0b' : '#ef4444'); ?>; border-radius: 3px;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Body: Two Detailed Panels (Invoices + Maintenance Contracts) -->
                            <div style="padding: 20px 22px;">
                                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                    <!-- Invoices List Table -->
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <i class="icon-file-text" style="color: var(--primary); margin-right: 4px;"></i> Invoice Distribution (<?php echo count($invoices); ?> <?php echo count($invoices) === 1 ? 'Invoice' : 'Invoices'; ?>)
                                            </h4>
                                            <span style="font-size: 11px; color: var(--text-muted);">Where this serial number appears in billing records</span>
                                        </div>

                                        <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                            <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                    <tr>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Invoice #</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Date</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Billed Customer</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Line Item Description</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Total (LKR)</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Settlement</th>
                                                        <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($invoices)): ?>
                                                    <tr>
                                                        <td colspan="7" style="padding: 15px; text-align: center; color: var(--text-muted);">
                                                            No discrete sales invoice linked.
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($invoices as $inv): 
                                                        $isSettled = !empty($inv['paid_date']);
                                                    ?>
                                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                                        <td style="padding: 8px 12px; font-family: monospace; font-weight: 800; color: var(--primary);">
                                                            <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">
                                                            <?php echo htmlspecialchars($inv['invoice_date'] ?: '—'); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; font-weight: 600; color: #1e293b;">
                                                            <?php echo htmlspecialchars($inv['customer_name']); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; color: #334155; font-size: 11px; max-width: 320px; line-height: 1.4;">
                                                            <?php 
                                                            $desc = $inv['matching_line_desc'] ?: $asset['product_name'];
                                                            // Highlight serial
                                                            $snHighlight = htmlspecialchars($asset['serial_number']);
                                                            $safeDesc = htmlspecialchars($desc);
                                                            if (!empty($snHighlight)) {
                                                                $safeDesc = str_ireplace($snHighlight, '<mark style="background: #fef08a; padding: 1px 4px; border-radius: 3px; font-weight: 800;">' . $snHighlight . '</mark>', $safeDesc);
                                                            }
                                                            echo $safeDesc;
                                                            ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">
                                                            <?php echo number_format((float)($inv['total_invoice_amount'] ?? 0), 2); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; text-align: center;">
                                                            <?php if ($isSettled): ?>
                                                                <span class="dense-badge dense-badge-settled" title="Settled on <?php echo $inv['paid_date']; ?><?php echo !empty($inv['days_to_pay']) ? ' (' . $inv['days_to_pay'] . 'd)' : ''; ?>">
                                                                    Settled
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="dense-badge dense-badge-unpaid">
                                                                    Unpaid
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                            <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($inv['invoice_number']); ?>')" style="padding: 4px 8px; font-size: 11px;">
                                                                <i class="icon-eye"></i> Audit
                                                            </button>
                                                            <?php if (!isset($auth) || $auth->canAccessReport('edit_invoices')): ?>
                                                            <a href="invoice_edit.php?inv=<?php echo urlencode($inv['invoice_number']); ?>" class="btn-view" style="padding: 4px 8px; font-size: 11px; color: var(--primary); text-decoration: none;" title="Edit Invoice Details &amp; Warranty">
                                                                <i class="icon-edit-3"></i> Edit
                                                            </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Maintenance Contracts Section -->
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <i class="icon-shield-check" style="color: #6366f1; margin-right: 4px;"></i> Customer Maintenance Agreements & SLAs (<?php echo count($contracts); ?>)
                                            </h4>
                                            <span style="font-size: 11px; color: var(--text-muted);">Active and historical maintenance contracts for this account</span>
                                        </div>

                                        <?php if (empty($contracts)): ?>
                                            <div style="padding: 14px 18px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 10px;">
                                                <i class="icon-info" style="font-size: 16px; color: #d97706;"></i>
                                                <div>
                                                    <strong>No active Maintenance Agreement on file for this account.</strong> This equipment is an immediate candidate for post-warranty SLA or annual maintenance contract (MA) proposal.
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                                <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                        <tr>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Service / Agreement Title</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Edition Tier</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Coverage Period</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Contract Status</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Opportunity Value (LKR)</th>
                                                            <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Origin Invoice</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($contracts as $mc): ?>
                                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                                            <td style="padding: 8px 12px; font-weight: 700; color: #1e293b;">
                                                                <?php echo htmlspecialchars($mc['contract_title']); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; color: #64748b; font-size: 11px;">
                                                                <?php echo htmlspecialchars($mc['edition_tier']); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">
                                                                <?php echo htmlspecialchars($mc['period_start_date'] ?: '—'); ?> &rarr; <?php echo htmlspecialchars($mc['period_end_date'] ?: 'Ongoing'); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; text-align: center;">
                                                                <span class="warranty-status-badge badge-<?php echo $mc['badge_class'] ?? 'secondary'; ?>" style="font-size: 10px; padding: 2px 8px;">
                                                                    <?php echo htmlspecialchars($mc['status_label']); ?>
                                                                </span>
                                                            </td>
                                                            <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">
                                                                <?php echo number_format((float)($mc['contract_value'] ?? 0), 2); ?>
                                                            </td>
                                                            <td style="padding: 8px 12px; text-align: center; white-space: nowrap;">
                                                                <?php if (!empty($mc['invoice_number'])): ?>
                                                                    <button type="button" class="btn-view" onclick="openInvoiceDetails('<?php echo htmlspecialchars($mc['invoice_number']); ?>')" style="padding: 4px 8px; font-size: 11px;">
                                                                        <i class="icon-eye"></i> <?php echo htmlspecialchars($mc['invoice_number']); ?>
                                                                    </button>
                                                                    <?php if (!isset($auth) || $auth->canAccessReport('edit_invoices')): ?>
                                                                    <a href="invoice_edit.php?inv=<?php echo urlencode($mc['invoice_number']); ?>" class="btn-view" style="padding: 4px 8px; font-size: 11px; color: var(--primary); text-decoration: none;" title="Edit Contract Invoice">
                                                                        <i class="icon-edit-3"></i>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: var(--text-muted);">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <style>
                    .serial-tag {
                        display: inline-flex;
                        align-items: center;
                        background: #0f172a;
                        color: #f8fafc;
                        font-family: monospace;
                        font-size: 13px;
                        padding: 4px 10px;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.2s;
                        user-select: all;
                    }
                    .serial-tag:hover {
                        background: #1e293b;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
                    }
                    .btn-chip {
                        background: #ffffff;
                        border: 1px solid #cbd5e1;
                        border-radius: 6px;
                        padding: 3px 10px;
                        font-size: 12px;
                        font-family: monospace;
                        font-weight: 700;
                        color: #334155;
                        cursor: pointer;
                        transition: all 0.15s;
                    }
                    .btn-chip:hover {
                        background: #f1f5f9;
                        border-color: #94a3b8;
                        color: var(--primary);
                    }
                    .st-tab-btn {
                        background: transparent;
                        border: none;
                        padding: 5px 12px;
                        font-size: 12px;
                        font-weight: 700;
                        color: #64748b;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.15s;
                    }
                    .st-tab-btn.active {
                        background: #ffffff;
                        color: var(--primary);
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    }
                    .warranty-status-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 11px;
                        font-weight: 800;
                        letter-spacing: 0.3px;
                        text-transform: uppercase;
                    }
                    .warranty-status-badge.badge-success {
                        background: #ecfdf5;
                        color: #065f46;
                        border: 1px solid #a7f3d0;
                    }
                    .warranty-status-badge.badge-warning {
                        background: #fffbeb;
                        color: #92400e;
                        border: 1px solid #fde68a;
                    }
                    .warranty-status-badge.badge-danger {
                        background: #fef2f2;
                        color: #991b1b;
                        border: 1px solid #fecaca;
                    }
                    .warranty-status-badge.badge-secondary {
                        background: #f1f5f9;
                        color: #475569;
                        border: 1px solid #e2e8f0;
                    }
                    .pulse-dot {
                        width: 7px;
                        height: 7px;
                        border-radius: 50%;
                        background: currentColor;
                        display: inline-block;
                    }
                    .badge-success .pulse-dot {
                        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.4);
                        animation: pulseDot 2s infinite;
                    }
                    @keyframes pulseDot {
                        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                        70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
                        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                    }
                </style>

                <script>
                    let warrantyDebounceTimer = null;

                    function handleWarrantySubmit(e) {
                        e.preventDefault();
                        const q = document.getElementById('warrantySearchInput').value.trim();
                        const st = document.getElementById('warrantyStatusField').value;
                        window.location.href = 'reports.php?type=warranties&search=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(st);
                        return false;
                    }

                    function quickSearchSerial(sn) {
                        const input = document.getElementById('warrantySearchInput');
                        input.value = sn;
                        document.getElementById('warrantyClearBtn').style.display = 'block';
                        performWarrantyLookup(sn, document.getElementById('warrantyStatusField').value);
                    }

                    function clearWarrantySearch() {
                        const input = document.getElementById('warrantySearchInput');
                        input.value = '';
                        document.getElementById('warrantyClearBtn').style.display = 'none';
                        performWarrantyLookup('', document.getElementById('warrantyStatusField').value);
                    }

                    function filterWarrantyStatus(st) {
                        document.getElementById('warrantyStatusField').value = st;
                        document.querySelectorAll('.st-tab-btn').forEach(btn => btn.classList.remove('active'));
                        event.target.classList.add('active');
                        const q = document.getElementById('warrantySearchInput').value.trim();
                        performWarrantyLookup(q, st);
                    }

                    function copyToClipboard(text, el) {
                        navigator.clipboard.writeText(text).then(() => {
                            const orig = el.innerHTML;
                            el.innerHTML = '<i class="icon-check" style="color: #10b981;"></i> Copied!';
                            setTimeout(() => { el.innerHTML = orig; }, 1500);
                        });
                    }

                    // Live Typeahead with Debounce
                    document.getElementById('warrantySearchInput')?.addEventListener('input', function(e) {
                        const val = e.target.value;
                        document.getElementById('warrantyClearBtn').style.display = val ? 'block' : 'none';
                        clearTimeout(warrantyDebounceTimer);
                        warrantyDebounceTimer = setTimeout(() => {
                            performWarrantyLookup(val, document.getElementById('warrantyStatusField').value);
                        }, 280);
                    });

                    function performWarrantyLookup(query, status) {
                        const notice = document.getElementById('warrantyLiveNotice');
                        if (notice) notice.style.display = 'inline-block';

                        // Update browser URL without reloading
                        const newUrl = 'reports.php?type=warranties&search=' + encodeURIComponent(query) + '&status=' + encodeURIComponent(status);
                        window.history.replaceState({path: newUrl}, '', newUrl);

                        fetch(`reports.php?ajax_warranty_lookup=${encodeURIComponent(query)}&status=${encodeURIComponent(status)}&limit=50`)
                            .then(r => r.json())
                            .then(data => {
                                if (notice) notice.style.display = 'none';
                                renderWarrantyResults(data.results || [], query);
                            })
                            .catch(err => {
                                if (notice) notice.style.display = 'none';
                                console.error('Warranty lookup error:', err);
                            });
                    }

                    function renderWarrantyResults(results, query) {
                        const container = document.getElementById('warrantyResultsContainer');
                        const countSpan = document.getElementById('warrantyCountSpan');
                        if (countSpan) countSpan.innerText = results.length;

                        if (results.length === 0) {
                            container.innerHTML = `
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                                        Showing <strong>0</strong> matching serialized equipment records for "<strong>${escapeHtml(query)}</strong>"
                                    </span>
                                </div>
                                <div class="card" style="text-align: center; padding: 60px 20px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                                    <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                        <i class="icon-shield" style="font-size: 28px; color: #94a3b8;"></i>
                                    </div>
                                    <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: var(--text-main);">No matching serialized equipment found</h3>
                                    <p style="color: var(--text-muted); font-size: 13px; max-width: 500px; margin: 0 auto 20px;">
                                        No records matched your search query. Try searching with a partial serial number or click a quick example.
                                    </p>
                                </div>
                            `;
                            return;
                        }

                        let html = `
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <span style="font-size: 14px; font-weight: 700; color: var(--text-main);">
                                    Showing <span id="warrantyCountSpan">${results.length}</span> matching serialized equipment records
                                    ${query ? `for "<strong>${escapeHtml(query)}</strong>"` : ''}
                                </span>
                            </div>
                        `;

                        results.forEach(asset => {
                            const badgeClass = asset.status_badge_class || 'secondary';
                            const statusLabel = asset.status_label || 'Unknown';
                            const pct = asset.progress_pct || 100;
                            const invoices = asset.invoices || [];
                            const contracts = asset.maintenance_contracts || [];
                            const barColor = badgeClass === 'success' ? '#10b981' : (badgeClass === 'warning' ? '#f59e0b' : '#ef4444');

                            let invoiceRows = '';
                            if (invoices.length === 0) {
                                invoiceRows = `<tr><td colspan="7" style="padding: 15px; text-align: center; color: var(--text-muted);">No discrete sales invoice linked.</td></tr>`;
                            } else {
                                invoices.forEach(inv => {
                                    const isSettled = !!inv.paid_date;
                                    let desc = inv.matching_line_desc || asset.product_name || '';
                                    let safeDesc = escapeHtml(desc);
                                    if (asset.serial_number) {
                                        const re = new RegExp(escapeRegExp(asset.serial_number), 'gi');
                                        safeDesc = safeDesc.replace(re, m => `<mark style="background: #fef08a; padding: 1px 4px; border-radius: 3px; font-weight: 800;">${m}</mark>`);
                                    }
                                    invoiceRows += `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 8px 12px; font-family: monospace; font-weight: 800; color: var(--primary);">${escapeHtml(inv.invoice_number)}</td>
                                            <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">${escapeHtml(inv.invoice_date || '—')}</td>
                                            <td style="padding: 8px 12px; font-weight: 600; color: #1e293b;">${escapeHtml(inv.customer_name || '—')}</td>
                                            <td style="padding: 8px 12px; color: #334155; font-size: 11px; max-width: 320px; line-height: 1.4;">${safeDesc}</td>
                                            <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">${formatCurrency(inv.total_invoice_amount || 0)}</td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                <span class="dense-badge ${isSettled ? 'dense-badge-settled' : 'dense-badge-unpaid'}">
                                                    ${isSettled ? 'Settled' : 'Unpaid'}
                                                </span>
                                            </td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                <button type="button" class="btn-view" onclick="openInvoiceDetails('${escapeHtml(inv.invoice_number)}')" style="padding: 4px 8px; font-size: 11px;">
                                                    <i class="icon-eye"></i> Audit
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                            }

                            let contractHtml = '';
                            if (contracts.length === 0) {
                                contractHtml = `
                                    <div style="padding: 14px 18px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 10px;">
                                        <i class="icon-info" style="font-size: 16px; color: #d97706;"></i>
                                        <div>
                                            <strong>No active Maintenance Agreement on file for this account.</strong> This equipment is an immediate candidate for post-warranty SLA or annual maintenance contract (MA) proposal.
                                        </div>
                                    </div>
                                `;
                            } else {
                                let cRows = '';
                                contracts.forEach(mc => {
                                    cRows += `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 8px 12px; font-weight: 700; color: #1e293b;">${escapeHtml(mc.contract_title || '')}</td>
                                            <td style="padding: 8px 12px; color: #64748b; font-size: 11px;">${escapeHtml(mc.edition_tier || '')}</td>
                                            <td style="padding: 8px 12px; color: #475569; white-space: nowrap;">${escapeHtml(mc.period_start_date || '—')} &rarr; ${escapeHtml(mc.period_end_date || 'Ongoing')}</td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                <span class="warranty-status-badge badge-${mc.badge_class || 'secondary'}" style="font-size: 10px; padding: 2px 8px;">
                                                    ${escapeHtml(mc.status_label || '')}
                                                </span>
                                            </td>
                                            <td style="padding: 8px 12px; font-weight: 800; color: #0f172a;" class="text-right">${formatCurrency(mc.contract_value || 0)}</td>
                                            <td style="padding: 8px 12px; text-align: center;">
                                                ${mc.invoice_number ? `<button type="button" class="btn-view" onclick="openInvoiceDetails('${escapeHtml(mc.invoice_number)}')" style="padding: 4px 8px; font-size: 11px;"><i class="icon-eye"></i> ${escapeHtml(mc.invoice_number)}</button>` : '—'}
                                            </td>
                                        </tr>
                                    `;
                                });
                                contractHtml = `
                                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                        <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                <tr>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Service / Agreement Title</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Edition Tier</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Coverage Period</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Contract Status</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Opportunity Value (LKR)</th>
                                                    <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Origin Invoice</th>
                                                </tr>
                                            </thead>
                                            <tbody>${cRows}</tbody>
                                        </table>
                                    </div>
                                `;
                            }

                            html += `
                                <div class="card warranty-asset-card" style="margin-bottom: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.03); overflow: hidden; padding: 0;">
                                    <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                                        <div style="flex: 1; min-width: 280px;">
                                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                                                <span class="serial-tag" onclick="copyToClipboard('${escapeHtml(asset.serial_number)}', this)" title="Click to copy serial number">
                                                    <i class="icon-hash" style="font-size: 11px; opacity: 0.6;"></i>
                                                    <strong>${escapeHtml(asset.serial_number)}</strong>
                                                    <i class="icon-copy" style="font-size: 11px; margin-left: 4px; opacity: 0.5;"></i>
                                                </span>
                                                <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0369a1; text-transform: uppercase;">
                                                    ${escapeHtml(asset.brand || 'Hardware')}
                                                </span>
                                                ${asset.model_sku ? `<span style="font-size: 11px; font-weight: 700; color: #64748b; font-family: monospace;">SKU: ${escapeHtml(asset.model_sku)}</span>` : ''}
                                                ${asset.parent_serial_number ? `<span style="font-size: 11px; color: #64748b;">Chassis S/N: <strong style="font-family: monospace;">${escapeHtml(asset.parent_serial_number)}</strong></span>` : ''}
                                            </div>
                                            <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: var(--text-main);">
                                                ${escapeHtml(asset.product_name)}
                                            </h3>
                                            <div style="font-size: 13px; color: var(--text-muted);">
                                                Primary Account: 
                                                <a href="customer_report.php?name=${encodeURIComponent(asset.current_customer)}" style="color: var(--primary); font-weight: 700; text-decoration: none;">
                                                    ${escapeHtml(asset.current_customer || '—')}
                                                </a>
                                            </div>
                                        </div>
                                        <div style="min-width: 220px; text-align: right;">
                                            <div>
                                                <span class="warranty-status-badge badge-${badgeClass}">
                                                    <span class="pulse-dot"></span>
                                                    ${escapeHtml(statusLabel)}
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                                Warranty: <strong>${asset.warranty_months ? asset.warranty_months + ' Months' : 'Standard'}</strong>
                                                ${asset.computed_expiry_date ? `&bull; Expiry: <strong style="color: #334155;">${escapeHtml(asset.computed_expiry_date)}</strong>` : ''}
                                            </div>
                                            <div style="margin-top: 8px; width: 100%; max-width: 220px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-left: auto;">
                                                <div style="width: ${pct}%; height: 100%; background: ${barColor}; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding: 20px 22px;">
                                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                    <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        <i class="icon-file-text" style="color: var(--primary); margin-right: 4px;"></i> Invoice Distribution (${invoices.length} ${invoices.length === 1 ? 'Invoice' : 'Invoices'})
                                                    </h4>
                                                </div>
                                                <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                                    <table class="table" style="width: 100%; margin: 0; border-collapse: collapse; font-size: 12px;">
                                                        <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                            <tr>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Invoice #</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Date</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Billed Customer</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Line Item Description</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-right">Total (LKR)</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Settlement</th>
                                                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;" class="text-center">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>${invoiceRows}</tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                    <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        <i class="icon-shield-check" style="color: #6366f1; margin-right: 4px;"></i> Customer Maintenance Agreements & SLAs (${contracts.length})
                                                    </h4>
                                                </div>
                                                ${contractHtml}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        container.innerHTML = html;
                    }

                    function escapeHtml(str) {
                        if (!str) return '';
                        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }

                    function escapeRegExp(string) {
                        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    }

                    function formatCurrency(val) {
                        const n = parseFloat(val) || 0;
                        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                </script>
