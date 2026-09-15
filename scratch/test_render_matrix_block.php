<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);
$currency = 'LKR ';

$matrixData = $reports->getMonthlySalesMatrix('rolling');

ob_start();
?>
<div class="matrix-container" style="display: flex; flex-direction: column; gap: 14px;">
    <!-- Metric cards test -->
    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
        <div class="metric-card">
            <div>Gross: <?php echo htmlspecialchars($currency) . number_format($matrixData['totals']['gross_sales'], 0); ?></div>
        </div>
        <div class="metric-card">
            <div>Avg: <?php echo htmlspecialchars($currency) . number_format($matrixData['averages']['gross_sales'], 0); ?></div>
        </div>
        <div class="metric-card">
            <div>Col Rate: <?php echo number_format($matrixData['totals']['collection_rate'], 1); ?>%</div>
        </div>
        <div class="metric-card">
            <div>Invoices: <?php echo number_format($matrixData['totals']['invoice_count']); ?></div>
        </div>
    </div>

    <!-- Table Test -->
    <table class="rational-table">
        <thead>
            <tr>
                <th>KPI</th>
                <?php foreach ($matrixData['months'] as $m): ?>
                    <th><?php echo $m['short_label']; ?></th>
                <?php endforeach; ?>
                <th>Total</th>
                <th>Avg</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($matrixData['metric_rows'] as $k => $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['label']); ?></td>
                <?php foreach ($matrixData['months'] as $m): ?>
                    <td>
                        <?php 
                        if ($m['is_future']) {
                            echo '-';
                        } else {
                            $v = $m['metrics'][$k];
                            if ($row['format'] === 'currency') echo number_format($v, 0);
                            elseif ($row['format'] === 'integer') echo number_format($v, 0);
                            elseif ($row['format'] === 'percentage') echo number_format($v, 1) . '%';
                            elseif ($row['format'] === 'growth_rate') echo ($v !== null ? sprintf('%+.1f%%', $v) : 'N/A');
                        }
                        ?>
                    </td>
                <?php endforeach; ?>
                <td><?php echo number_format($matrixData['totals'][$k], 0); ?></td>
                <td><?php echo number_format($matrixData['averages'][$k], 0); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$html = ob_get_clean();
echo "Rendered HTML length: " . strlen($html) . " bytes\n";
echo "Syntax check OK!\n";
