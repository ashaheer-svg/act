                <!-- 8. Statutory Tax & IRD Audit Ledger -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Audited Invoices</span>
                        <span class="metric-pill-val"><?php echo number_format($taxTotal); ?></span>
                        <span class="metric-pill-sub">Total Statutory Filings</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Taxable Base</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($taxSummary['total_taxable_base'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Net Assessed Revenue</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Statutory 18% VAT Assessed</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?php echo htmlspecialchars($currency) . number_format($taxSummary['total_vat_assessed'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Total IRD Tax Obligation</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Gross Billed Total</span>
                        <span class="metric-pill-val"><?php echo htmlspecialchars($currency) . number_format($taxSummary['grand_gross_total'] ?? 0, 0); ?></span>
                        <span class="metric-pill-sub">Includes Inclusive & Plus VAT</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Breakdown Integrity</span>
                        <span class="metric-pill-val" style="font-size: 13px; color: #15803d;">
                            +VAT: <?php echo htmlspecialchars($currency) . number_format($taxSummary['statutory_plus_vat'] ?? 0, 0); ?>
                        </span>
                        <span class="metric-pill-sub" style="color: #64748b;">
                            Inc Sales: <?php echo htmlspecialchars($currency) . number_format($taxSummary['inclusive_sales_volume'] ?? 0, 0); ?>
                        </span>
                    </div>
                </div>

                <div class="table-dense-container">
                    <div style="overflow-x: auto; max-height: calc(100vh - 180px);">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th style="width: 75px;">Date</th>
                                    <th style="width: 95px;">Invoice #</th>
                                    <th style="width: 80px;">Type</th>
                                    <th>Customer / Enterprise</th>
                                    <th style="width: 85px;">VAT Treatment</th>
                                    <th class="text-right" style="width: 105px;">Taxable Base</th>
                                    <th class="text-right" style="width: 95px;">18% VAT</th>
                                    <th class="text-right" style="width: 110px;">Gross Total</th>
                                    <th class="text-center" style="width: 75px;">Settled Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($taxData)): ?>
                                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No invoices found for the selected period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($taxData as $row): 
                                        $isPlus = $row['vat_treatment'] === 'PLUS_VAT';
                                    ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                                        <td><span class="dense-doc-num"><?php echo htmlspecialchars($row['invoice_number']); ?></span></td>
                                        <td><span class="dense-badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($row['invoice_type']); ?></span></td>
                                        <td style="font-weight: 600; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                        </td>
                                        <td>
                                            <span class="dense-badge <?php echo ($isPlus || $row['vat_treatment'] === 'VAT_INCLUSIVE') ? 'dense-badge-plusvat' : 'dense-badge-exempt'; ?>">
                                                <?php echo ($isPlus || $row['vat_treatment'] === 'VAT_INCLUSIVE') ? '+VAT' : 'EXEMPT'; ?>
                                            </span>
                                        </td>
                                        <td class="text-right dense-num" style="color: #475569;"><?php echo number_format($row['taxable_base'], 0); ?></td>
                                        <td class="text-right dense-num-bold" style="color: var(--primary);"><?php echo number_format($row['vat_18_component'], 0); ?></td>
                                        <td class="text-right dense-num-bold"><?php echo number_format($row['gross_total'], 0); ?></td>
                                        <td class="text-center" style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo htmlspecialchars($row['paid_date'] ?: '—'); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $baseUrl = "reports.php?type=tax_audit&year=" . urlencode($year) . "&month=" . urlencode($month);
                    echo renderPaginationRail($p, $taxPages, $taxTotal, $limit, $baseUrl, 'records');
                    ?>
                </div>

