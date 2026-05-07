<?php
// Simple POST test - visit this page and submit the form
// If this works, the issue is in login.php logic
// If this also gets 403, the issue is server-level

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST worked! Data received:</h2>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    echo "<p style='color:green'>POST requests are NOT blocked by the server.</p>";
} else {
    echo '<h2>POST Test</h2>';
    echo '<form method="POST">';
    echo '<input name="test_field" value="hello">';
    echo '<button type="submit">Submit POST</button>';
    echo '</form>';
}
?>
