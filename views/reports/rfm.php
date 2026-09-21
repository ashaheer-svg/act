                <!-- RFM Customer Segmentation & Churn Risk View -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2>RFM Customer Segmentation & Churn Prevention</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Behavioral clustering based on Recency, Frequency, and Monetary spend.</p>
                        </div>

                        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
                            <input type="hidden" name="type" value="rfm">
                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Behavioral Segment</span>
                                <select name="segment" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($segment ?? '') == 'all' ? 'selected' : ''; ?>>All Segments</option>
                                    <option value="Champion" <?php echo ($segment ?? '') == 'Champion' ? 'selected' : ''; ?>>Champions</option>
                                    <option value="Loyal Account" <?php echo ($segment ?? '') == 'Loyal Account' ? 'selected' : ''; ?>>Loyal Accounts</option>
                                    <option value="Potential Loyalist" <?php echo ($segment ?? '') == 'Potential Loyalist' ? 'selected' : ''; ?>>Potential Loyalists</option>
                                    <option value="Recent Buyer" <?php echo ($segment ?? '') == 'Recent Buyer' ? 'selected' : ''; ?>>Recent Buyers</option>
                                    <option value="At Risk" <?php echo ($segment ?? '') == 'At Risk' ? 'selected' : ''; ?>>At Risk (Churn Alert)</option>
                                    <option value="Needs Attention" <?php echo ($segment ?? '') == 'Needs Attention' ? 'selected' : ''; ?>>Needs Attention</option>
                                    <option value="Lost / Dormant" <?php echo ($segment ?? '') == 'Lost / Dormant' ? 'selected' : ''; ?>>Lost / Dormant</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Channel</th>
                                <th>Sales Rep</th>
                                <th>Last Order</th>
                                <th class="text-right">Inactivity</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Net Base Spend</th>
                                <th class="text-center">Segment</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rfmData as $row): ?>
                            <tr>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><span class="badge <?php echo $row['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>"><?php echo htmlspecialchars($row['customer_type']); ?></span></td>
                                <td style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($row['sales_rep']); ?></td>
                                <td style="color: var(--text-muted);"><?php echo date('M d, Y', strtotime($row['last_order_date'])); ?></td>
                                <td class="text-right" style="font-weight: 800; color: <?php echo $row['recency_days'] > 120 ? '#ef4444' : ($row['recency_days'] > 60 ? '#f59e0b' : '#10b981'); ?>;">
                                    <?php echo $row['recency_days']; ?> Days
                                </td>
                                <td class="text-right"><?php echo $row['frequency']; ?></td>
                                <td class="text-right price-tag"><?php echo htmlspecialchars($currency) . number_format($row['monetary'], 0); ?></td>
                                <td class="text-center">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; background: <?php echo $row['segment_color']; ?>20; color: <?php echo $row['segment_color']; ?>; border: 1px solid <?php echo $row['segment_color']; ?>40;">
                                        <?php echo $row['segment']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="customer_report.php?name=<?php echo urlencode($row['customer_name']); ?>" class="btn-view" style="font-size: 10px; padding: 6px 12px; text-decoration: none;">Strategic Dossier</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

