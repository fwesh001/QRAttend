<?php
/**
 * One-time script to import the database into Aiven.
 */
require_once __DIR__ . '/../app/config/config.php';

try {
    $db = get_db();
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/../qrattend.sql';
    if (!file_exists($sqlFile)) {
        die("SQL file not found at: " . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL (PDO handles multiple statements if emulate prepares is on, which it is for MySQL by default in many cases)
    // But to be safe, we can just execute it.
    $db->exec($sql);
    
    echo "<h1>Database Imported Successfully!</h1>";
    echo "<p>You can now delete this file (public/import_db.php) for security.</p>";
    
} catch (Exception $e) {
    echo "<h1>Error Importing Database</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
