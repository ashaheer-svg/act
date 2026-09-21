                <!-- Partner vs End Customer Cohort View -->
                <div class="card" style="margin-bottom: 25px;">
                    <h2>Partner vs. End-Customer Cohort Analysis</h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">Channel performance breakdown comparing B2B partners against direct end customers.</p>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer Channel</th>
                                <th class="text-right">Active Accounts</th>
                                <th class="text-right">Orders / Invoices</th>
                                <th class="text-right">Gross Revenue</th>
                                <th class="text-right">Net Base Revenue</th>
                                <th class="text-right">Avg Order Value</th>
                                <th class="text-right">Avg Turnaround (DSO)</th>
                                <th class="text-right">Revenue Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cohortData as $row): ?>
                            <tr>
                                <td style="font-weight: 700; font-size: 15px;">
                                    <span class="badge <?php echo $row['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>" style="font-size: 13px; padding: 6px 14px;">
                                        <?php echo htmlspecialchars($row['customer_type']); ?>
                                    </span>
                                </td>
                                <td class="text-right" style="font-weight: 700;"><?php echo number_format($row['total_accounts']); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_orders']); ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['total_gross'], 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($row['total_base'], 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency) . number_format($row['avg_order_value'], 0); ?></td>
                                <td class="text-right" style="font-weight: 800; color: #0284c7;"><?php echo $row['avg_days_to_pay']; ?> Days</td>
                                <td class="text-right" style="font-weight: 800; font-family: 'Inter Tight', sans-serif;"><?php echo $row['revenue_share_pct']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

