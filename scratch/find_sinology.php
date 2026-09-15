<?php
require_once 'config.php';
$db = new PDO('sqlite:' . DATABASE_PATH);

echo "=== SEARCHING FOR 'Sinology' in SQLite database ===\n";

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    if (strpos($table, 'sqlite_') === 0) continue;
    $cols = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    $textCols = [];
    foreach ($cols as $col) {
        $textCols[] = $col['name'];
    }
    
    foreach ($textCols as $colName) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM \"$table\" WHERE \"$colName\" LIKE '%Sinology%'");
        try {
            $stmt->execute();
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                echo "Table: $table | Column: $colName | Matches: $count\n";
                // Show samples
                $sampleStmt = $db->prepare("SELECT rowid, \"$colName\" FROM \"$table\" WHERE \"$colName\" LIKE '%Sinology%' LIMIT 5");
                $sampleStmt->execute();
                $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($samples as $s) {
                    echo "   - RowID " . $s['rowid'] . ": " . substr($s[$colName], 0, 150) . "\n";
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}

echo "=== SEARCH COMPLETED ===\n";
