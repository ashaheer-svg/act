                <div class="card">
                    <h2>Customer Matrix - Net Sales (Before VAT)</h2>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">12-month pivot of net revenue performance.</p>
                    
                    <div class="filter-controls">
                        <div class="filter-group">
                            <span class="filter-label">Brand</span>
                            <select class="filter-select" onchange="window.location.href='reports.php?type=matrix&year=<?php echo $year; ?>&customer_type=<?php echo $customer_type; ?>&rep_code=<?php echo $rep_code; ?>&brand='+encodeURIComponent(this.value)">
                                <option value="">All Brands</option>
                                <?php foreach($uniqueBrands as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo $brand === $b['product_category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['product_category']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <span class="filter-label">Customer Category</span>
                            <select class="filter-select" onchange="window.location.href='reports.php?type=matrix&year=<?php echo $year; ?>&brand=<?php echo $brand; ?>&rep_code=<?php echo $rep_code; ?>&customer_type='+encodeURIComponent(this.value)">
                                <option value="">All Customers</option>
                                <option value="Partner" <?php echo $customer_type === 'Partner' ? 'selected' : ''; ?>>Partners Only</option>
                                <option value="End Customer" <?php echo $customer_type === 'End Customer' ? 'selected' : ''; ?>>End Customers Only</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <span class="filter-label">Sales Rep</span>
                            <select class="filter-select" onchange="window.location.href='reports.php?type=matrix&year=<?php echo $year; ?>&brand=<?php echo $brand; ?>&customer_type=<?php echo $customer_type; ?>&rep_code='+encodeURIComponent(this.value)">
                                <option value="">All Reps</option>
                                <?php foreach($salesReps as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['rep_code']); ?>" <?php echo $rep_code === $r['rep_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['rep_name']); ?> (<?php echo htmlspecialchars($r['rep_code']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <table class="table matrix-table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th class="text-right">Total Net</th>
                                <th class="text-right">Vol</th>
                                <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                                <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($customerPivot as $row): ?>
                            <tr>
                                <td title="<?php echo htmlspecialchars($row['customer_name']); ?>">
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </td>
                                <td class="price-tag"><?php echo htmlspecialchars($currency); ?><?php echo number_format($row['total_revenue'], 0); ?></td>
                                <td><span class="badge-vol"><?php echo $row['total_volume']; ?></span></td>
                                <?php for($m=1; $m<=12; $m++): 
                                    $val = $row['month_'.$m];
                                ?>
                                <td class="matrix-val <?php echo $val > 0 ? 'active' : ''; ?>">
                                    <?php echo $val > 0 ? number_format($val, 0) : '-'; ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
