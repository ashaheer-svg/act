                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-label">Invoices</div>
                        <div class="metric-value"><?php echo $summary['total_invoices'] ?? 0; ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Revenue (Base)</div>
                        <div class="metric-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total After VAT</div>
                        <div class="metric-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                    </div>
                </div>

                <div class="card">
                    <h2><?php echo htmlspecialchars($reportTitle); ?></h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Granular view of transactions for the selected period.</p>
                    
                    <?php if ($type === 'yearly' && !empty($reportData['monthly_breakdown'])): ?>
                    <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <span style="color: var(--primary);">●</span> Monthly Breakdown
                    </h4>
                    <table class="table" style="margin-bottom: 40px;">
                        <thead>
                            <tr><th>Month</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">VAT</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($reportData['monthly_breakdown'] as $m): ?>
                            <tr>
                                <td><?php echo $m['month_name']; ?></td>
                                <td class="text-right"><?php echo $m['invoice_count']; ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($m['revenue_base'], 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($m['vat_total'], 0); ?></td>
                                <td class="text-right" class="price-tag"><?php echo htmlspecialchars($currency) . number_format($m['total'], 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <span style="color: var(--secondary);">●</span> Transaction Details
                    </h4>
                    <table class="table">
                        <thead>
                            <tr><th>Date</th><th>Invoice #</th><th>Customer</th><th class="text-right">Total Value</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($reportData['data'] ?? [] as $row): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?></td>
                                <td style="font-family: monospace; font-weight: 700;"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['customer_name'], 0, 40)); ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['total_amount'], 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
