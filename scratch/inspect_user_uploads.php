<?php
$f1 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102531.csv';
$f2 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102565.csv';

function inspectCsv($path, $name) {
    echo "=== Inspecting $name ===\n";
    if (!file_exists($path)) {
        echo "File does not exist: $path\n";
        return;
    }
    $h = fopen($path, 'r');
    $header = fgetcsv($h);
    echo "Header: " . implode(', ', array_slice($header, 0, 15)) . "\n";
    $rowCount = 0;
    $minDate = '9999-99-99';
    $maxDate = '0000-00-00';
    $dates = [];
    $sampleRows = [];
    
    // find date column index
    $dateIdx = -1;
    foreach ($header as $idx => $col) {
        if (stripos($col, 'date') !== false || stripos($col, 'txn') !== false) {
            $dateIdx = $idx;
            echo "Date column found at idx $idx: $col\n";
            break;
        }
    }
    
    while (($row = fgetcsv($h)) !== false) {
        $rowCount++;
        if ($rowCount <= 3) {
            $sampleRows[] = array_slice($row, 0, 10);
        }
        if ($dateIdx !== -1 && isset($row[$dateIdx])) {
            $d = trim($row[$dateIdx]);
            if (strlen($d) >= 8) {
                if ($d < $minDate) $minDate = $d;
                if ($d > $maxDate) $maxDate = $d;
                $yr = substr($d, 0, 4);
                $dates[$yr] = ($dates[$yr] ?? 0) + 1;
            }
        }
    }
    fclose($h);
    
    echo "Total Rows: $rowCount\n";
    echo "Min Date: $minDate, Max Date: $maxDate\n";
    ksort($dates);
    echo "Year Distribution:\n";
    print_r($dates);
    echo "Sample Rows:\n";
    print_r($sampleRows);
    echo "\n";
}

inspectCsv($f1, "media_1788952102531.csv");
inspectCsv($f2, "media_1788952102565.csv");
