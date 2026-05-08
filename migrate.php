<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "<h1>Manual Migration Tool</h1>";

try {
    echo "Checking columns in 'sales' table...<br>";
    $cols = $db->fetchAll("PRAGMA table_info(sales)");
    $colNames = array_column($cols, 'name');
    
    echo "Existing columns: " . implode(", ", $colNames) . "<br><br>";
    
    $needed = [
        'gross_profit' => "ALTER TABLE sales ADD COLUMN gross_profit DECIMAL(12,2) DEFAULT 0",
        'applied_tax_rate' => "ALTER TABLE sales ADD COLUMN applied_tax_rate DECIMAL(5,4)",
        'product_category' => "ALTER TABLE sales ADD COLUMN product_category TEXT"
    ];
    
    foreach ($needed as $col => $sql) {
        if (!in_array($col, $colNames)) {
            echo "Adding column '$col'... ";
            $db->execute($sql);
            echo "<span style='color: green;'>Success</span><br>";
        } else {
            echo "Column '$col' already exists.<br>";
        }
    }
    
    echo "<br><b style='color: green;'>Migration Complete!</b>";
    echo "<br><a href='index.php'>Go to Dashboard</a>";

} catch (Exception $e) {
    echo "<br><b style='color: red;'>Migration Failed:</b> " . $e->getMessage();
}
