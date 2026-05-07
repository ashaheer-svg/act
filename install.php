<?php
/**
 * Database Installation Script
 * Run this once to initialize the SQLite database
 */
require_once 'config.php';
require_once 'classes/Database.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";

try {
    // Create connection
    $db = new Database(DATABASE_PATH);
    
    // Run initialization
    echo "<h3>🛠 Initializing Database...</h3>";
    $db->initialize();
    
    echo "<h3>⚙️ Setting up Defaults...</h3>";
    $db->initializeSettings();
    
    echo "<h2 style='color: #2e7d32;'>✅ Success!</h2>";
    echo "<p>The database has been initialized successfully.</p>";
    echo "<hr>";
    echo "<h4>Default Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Username:</strong> admin</li>";
    echo "<li><strong>Password:</strong> admin123</li>";
    echo "</ul>";
    echo "<p style='color: #c33;'><strong>Security Note:</strong> Please delete this file (<code>install.php</code>) after logging in.</p>";
    echo "<p><a href='login.php' style='display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
} catch (Exception $e) {
    echo "<h2 style='color: #c33;'>❌ Installation Failed</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please ensure the <code>data/</code> directory is writable.</p>";
}

echo "</div>";
