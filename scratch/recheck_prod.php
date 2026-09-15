<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'classes/Reports.php',
    'classes/Database.php',
    'classes/DataSorter.php',
    'reports.php',
    'product_mapping.php',
    'includes/sidebar.php'
];

@mkdir('scratch/recheck', 0777, true);
@mkdir('scratch/recheck/classes', 0777, true);
@mkdir('scratch/recheck/includes', 0777, true);

echo sprintf("%-26s | %-7s | %-10s | %-10s | %s\n", "File", "Match?", "Local MD5", "Prod MD5", "Size");
echo str_repeat("-", 75) . "\n";

$allMatched = true;

foreach ($files as $f) {
    $dest = "scratch/recheck/$f";
    $remote = "$ftpBase/$f";
    $cmd = "curl.exe -s --ftp-ssl-control -k --user \"$ftpUser:$ftpPass\" \"$remote\" -o \"$dest\"";
    exec($cmd);
    
    $localMd5 = md5_file($f);
    $prodMd5 = file_exists($dest) ? md5_file($dest) : 'NONE';
    $size = file_exists($dest) ? filesize($dest) : 0;
    
    $match = ($localMd5 === $prodMd5);
    if (!$match) $allMatched = false;
    
    echo sprintf("%-26s | %-7s | %-10s | %-10s | %d bytes\n", $f, $match ? "MATCH" : "MISMATCH", substr($localMd5,0,8), substr($prodMd5,0,8), $size);
}

echo "\nResult: " . ($allMatched ? "ALL FILES 100% IDENTICAL TO LOCAL!" : "SOME FILES MISMATCHED!") . "\n";
