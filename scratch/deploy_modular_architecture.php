<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    "settings.php",
    "reports.php",
    "rbac.php",
    "users.php",
    "classes/Auth.php",
    "includes/sidebar.php",
    "includes/header.php"
];

// Add all modules/settings/
foreach (glob("modules/settings/*.php") as $f) {
    $files[] = str_replace('\\', '/', $f);
}

// Add all modules/reports/
foreach (glob("modules/reports/*.php") as $f) {
    $files[] = str_replace('\\', '/', $f);
}

echo "Total files to deploy: " . count($files) . "\n";
$success = 0;
$failed = 0;

foreach ($files as $f) {
    echo "Uploading $f ... ";
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$ftpBase/$f\" 2>&1";
    $out = [];
    $ret = 0;
    exec($cmd, $out, $ret);
    if ($ret === 0) {
        echo "OK\n";
        $success++;
    } else {
        echo "FAILED: " . implode("\n", $out) . "\n";
        $failed++;
    }
}

echo "\nDeployment summary:\n";
echo "Successfully uploaded: $success\n";
echo "Failed: $failed\n";
