<?php
$f1 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102531.csv';
$f2 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102565.csv';

function inspectCSV($path, $label) {
    if (!file_exists($path)) {
        echo "File not found: $path\n";
        return;
    }
    $fp = fopen($path, 'r');
    $header = fgetcsv($fp);
    $rows = 0;
    $withVat = 0;
    $withTin = 0;
    $withAddressVat = 0;
    
    while (($data = fgetcsv($fp)) !== false) {
        if (empty($data[0])) continue;
        $rows++;
        $row = array_combine($header, $data);
        if (!empty($row['VatNumber'])) $withVat++;
        if (!empty($row['TinNumber'])) $withTin++;
        
        $text = ($row['ResaleNumber'] ?? '') . ' ' . ($row['BillAddress'] ?? '') . ' ' . ($row['BillCity'] ?? '') . ' ' . ($row['BillState'] ?? '') . ' ' . ($row['BillZip'] ?? '') . ' ' . ($row['CompanyName'] ?? '') . ' ' . ($row['Notes'] ?? '');
        if (preg_match('/(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{3,4})?|[0-9A-Z\-\/]{7,})/i', $text) || preg_match('/\b([0-9]{9}-7000?)\b/', $text)) {
            $withAddressVat++;
        }
    }
    fclose($fp);
    echo "$label: $rows customers. Explicit VatNumber: $withVat, Explicit TinNumber: $withTin, Address/Notes VAT: $withAddressVat\n";
}

inspectCSV($f1, "File 1 (Old System Extractions)");
inspectCSV($f2, "File 2 (New System Extractions)");
