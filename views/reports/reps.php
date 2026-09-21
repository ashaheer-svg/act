                <!-- Sales Rep Performance & Collection Health View -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2>Sales Rep Performance & DSO Collection Efficiency</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Revenue contribution, customer reach, and payment collection turnaround per representative.</p>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rep Code</th>
                                <th>Representative Name</th>
                                <th class="text-right">Invoices</th>
                                <th class="text-right">Active Clients</th>
                                <th class="text-right">Gross Sales</th>
                                <th class="text-right">Collected Revenue</th>
                                <th class="text-right">Outstanding</th>
                                <th class="text-right">Collection Rate</th>
                                <th class="text-right">Avg DSO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($repsData as $row): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 800; color: var(--primary); font-size: 14px;"><?php echo htmlspecialchars($row['sales_rep_code']); ?></td>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($row['rep_name']); ?></td>
                                <td class="text-right"><?php echo number_format($row['invoice_count']); ?></td>
                                <td class="text-right"><?php echo number_format($row['client_count']); ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['gross_revenue'], 0); ?></td>
                                <td class="text-right" style="color: #15803d; font-weight: 700;"><?php echo htmlspecialchars($currency) . number_format($row['collected_revenue'], 0); ?></td>
                                <td class="text-right" style="color: #b91c1c; font-weight: 700;"><?php echo htmlspecialchars($currency) . number_format($row['outstanding_revenue'], 0); ?></td>
                                <td class="text-right" style="font-weight: 800;"><?php echo $row['collection_rate_pct']; ?>%</td>
                                <td class="text-right" style="font-weight: 800; color: <?php echo $row['avg_dso'] > 60 ? '#ef4444' : ($row['avg_dso'] > 40 ? '#f59e0b' : '#10b981'); ?>;">
                                    <?php echo $row['avg_dso']; ?> Days
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
