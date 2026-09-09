<?php
$baseUrl = 'https://act.active.lk';
$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Fetch login page & CSRF token if any
$ch = curl_init("$baseUrl/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrf = $matches[1] ?? '';

// 2. Perform Login
$ch = curl_init("$baseUrl/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'username' => 'admin',
        'password' => 'admin123',
        'csrf_token' => $csrf
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$loginResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login status: HTTP $httpCode\n";

// 3. Test invoices report page
$ch = curl_init("$baseUrl/reports.php?type=invoices");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$page = curl_exec($ch);
curl_close($ch);

if (strpos($page, 'Commercial Invoices Summary') !== false) {
    echo "SUCCESS: Live production reports.php?type=invoices loaded with 'Commercial Invoices Summary'!\n";
} else {
    echo "FAILED to find expected string on live reports page. Output length: " . strlen($page) . "\n";
}

// 4. Test live AJAX details
$ch = curl_init("$baseUrl/reports.php?ajax_invoice_details=AS000102");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$json = curl_exec($ch);
curl_close($ch);

$data = json_decode($json, true);
if ($data && !empty($data['success'])) {
    echo "SUCCESS: Live AJAX invoice details returned for AS000102!\n";
    echo "  Customer: " . $data['header']['customer_name'] . "\n";
    echo "  Total Lines: " . count($data['lines']) . "\n";
} else {
    echo "FAILED: Live AJAX details returned: " . substr($json, 0, 200) . "\n";
}

if (file_exists($cookieFile)) unlink($cookieFile);
