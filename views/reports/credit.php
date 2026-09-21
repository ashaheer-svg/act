                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2>Customer Credit Health Index</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Historical payment performance and risk scoring based on 'Days to Pay'.</p>
                        </div>
                        <div style="background: #f8fafc; padding: 10px 20px; border-radius: 12px; border: 1px solid var(--border); text-align: center;">
                            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Average Collection Period</div>
                            <?php 
                                $allAvg = count($creditData) > 0 ? array_sum(array_column($creditData, 'avg_days')) / count($creditData) : 0;
                            ?>
                            <div style="font-size: 20px; font-weight: 800; color: var(--primary);"><?php echo round($allAvg); ?> Days</div>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Partner / End Customer</th>
                                <th class="text-right">Total Volume</th>
                                <th class="text-right">Paid Inv</th>
                                <th class="text-right">Avg Days to Pay</th>
                                <th class="text-right">Max Delay</th>
                                <th class="text-center">Risk Level</th>
                                <th class="text-right">Credit Score</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($creditData as $row): 
                                $riskColor = '#10b981'; // Excellent
                                if ($row['risk_level'] === 'Good') $riskColor = '#6366f1';
                                if ($row['risk_level'] === 'Fair') $riskColor = '#f59e0b';
                                if ($row['risk_level'] === 'At Risk') $riskColor = '#fb923c';
                                if ($row['risk_level'] === 'Critical') $riskColor = '#ef4444';
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($currency); ?><?php echo number_format($row['total_volume'], 0); ?></td>
                                <td class="text-right"><?php echo $row['paid_count']; ?> / <?php echo $row['total_invoices']; ?></td>
                                <td class="text-right"><?php echo round($row['avg_days']); ?> Days</td>
                                <td class="text-right"><?php echo $row['max_days']; ?> Days</td>
                                <td class="text-center">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: <?php echo $riskColor; ?>20; color: <?php echo $riskColor; ?>; font-size: 11px; font-weight: 800; text-transform: uppercase; border: 1px solid <?php echo $riskColor; ?>40;">
                                        <?php echo $row['risk_level']; ?>
                                    </span>
                                </td>
                                <td class="text-right" style="font-family: 'Inter Tight', sans-serif; font-weight: 800; font-size: 16px;">
                                    <?php echo $row['credit_score']; ?>
                                </td>
                                <td class="text-center">
                                    <button onclick="viewCustomerDetails('<?php echo addslashes($row['customer_name']); ?>')" class="btn-view">More Info</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
