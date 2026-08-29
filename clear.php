<?php
/**
 * MASTER DATABASE RESET - SmartStudyPro
 * This script wipes all data from all tables and resets ID counters.
 */

try {
    $db_file = __DIR__ . "/database/bookings.db";
    if(!file_exists($db_file)) die("Database file does not exist.");

    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Get a list of all tables in the database
    $tables = ['bookings', 'product_orders', 'messages'];

    // 2. Loop through and clear each one
    foreach ($tables as $table) {
        // Check if table exists before trying to clear it
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'")->fetch();
        
        if ($check) {
            // Delete all rows
            $db->exec("DELETE FROM $table");
            
            // Reset the Auto-Increment counter in SQLite's internal tracker
            $db->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
            
            echo "Successfully cleared table: **$table** <br>";
        }
    }

    echo "<h3>Full Reset Complete!</h3>";
    echo "<a href='index.php'>Return to Site</a> | <a href='admin.php'>Go to Admin</a>";

} catch (Exception $e) {
    echo "Error resetting database: " . $e->getMessage();
}
?>