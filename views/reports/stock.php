                <!-- Stock Movement & Velocity View -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h2>Stock Movement & Inventory Velocity (FSN)</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Fast, slow, and non-moving stock classification based on dispatch velocity.</p>
                        </div>

                        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="type" value="stock">

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Search SKU / Serial</span>
                                <input type="text" name="search" class="filter-select" placeholder="Search item or serial..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="min-width: 180px;">
                            </div>
                            
                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Brand / Category</span>
                                <select name="brand" class="filter-select" onchange="this.form.submit()">
                                    <option value="">All Brands</option>
                                    <?php foreach($uniqueBrands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['product_category']); ?>" <?php echo ($brand ?? '') === $b['product_category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['product_category']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group" style="margin: 0;">
                                <span class="filter-label">Velocity (FSN)</span>
                                <select name="fsn" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($fsn ?? '') == 'all' ? 'selected' : ''; ?>>All Velocities</option>
                                    <option value="F" <?php echo ($fsn ?? '') == 'F' ? 'selected' : ''; ?>>Fast-Moving (F)</option>
                                    <option value="S" <?php echo ($fsn ?? '') == 'S' ? 'selected' : ''; ?>>Slow-Moving (S)</option>
                                    <option value="N" <?php echo ($fsn ?? '') == 'N' ? 'selected' : ''; ?>>Non-Moving / Dormant (N)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-view" style="padding: 8px 16px;">Filter</button>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product / SKU Description</th>
                                <th>Brand / Category</th>
                                <th class="text-right">Units Dispatched</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Active Months</th>
                                <th>Last Movement</th>
                                <th class="text-right">Days Inactive</th>
                                <th class="text-center">Velocity</th>
                                <th class="text-right">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stockData)): ?>
                                <tr><td colspan="9" style="text-align: center; padding: 50px; color: var(--text-muted);">No stock movement records match the filters.</td></tr>
                            <?php else: ?>
                                <?php foreach($stockData as $row): 
                                    $vColor = '#10b981'; // Fast
                                    if ($row['velocity_code'] === 'S') $vColor = '#f59e0b'; // Slow
                                    if ($row['velocity_code'] === 'N') $vColor = '#64748b'; // Non-moving
                                ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($row['item_description']); ?>
                                        <?php if ($row['is_serialized']): ?>
                                            <span style="font-size: 10px; background: #e0e7ff; color: #4338ca; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 6px;">SERIALIZED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge" style="background: #f1f5f9; color: #334155;"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                    <td class="text-right" style="font-weight: 800; font-family: 'Inter Tight', sans-serif;"><?php echo number_format($row['total_units']); ?></td>
                                    <td class="text-right"><?php echo number_format($row['dispatch_count']); ?></td>
                                    <td class="text-right"><?php echo $row['active_months']; ?> mos</td>
                                    <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('M d, Y', strtotime($row['last_dispatch'])); ?></td>
                                    <td class="text-right" style="font-weight: 700; color: <?php echo $row['days_since_dispatch'] > 180 ? '#ef4444' : ($row['days_since_dispatch'] > 60 ? '#f59e0b' : '#10b981'); ?>;">
                                        <?php echo $row['days_since_dispatch']; ?>d
                                    </td>
                                    <td class="text-center">
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: <?php echo $vColor; ?>20; color: <?php echo $vColor; ?>; border: 1px solid <?php echo $vColor; ?>40;">
                                            <?php echo $row['velocity']; ?>
                                        </span>
                                    </td>
                                    <td class="text-right price-tag">
                                        <?php echo htmlspecialchars($currency) . number_format($row['total_revenue'], 0); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php 
                    $stockBaseUrl = "reports.php?type=stock&brand=" . urlencode($brand ?? '') . "&fsn=" . urlencode($fsn ?? '') . "&search=" . urlencode($search ?? '');
                    echo renderPaginationRail($p, $stockPages, $stockTotal, $limit, $stockBaseUrl, 'products'); 
                    ?>
                </div>

