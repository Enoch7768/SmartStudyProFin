<?php
// 1. Include your existing connection file
require_once __DIR__ . '/cms-init.php'; // <--- CHANGE TO YOUR ACTUAL FILE NAME

// 2. Ensure Cockpit is loaded
if (!is_cms_connected()) {
    die("Cockpit is not connected. Check your paths.");
}

// 3. Get the App instance
$app = cockpit();

// 4. Access the database directly via dataStorage (most reliable in v2)
$user = $app->dataStorage->findOne('system/users', []);

if (!$user) {
    die("No users found in the database.");
}

$username = $user['user'];
$newPassword = 'SmartStudyPro123'; // You can generate a random password here if you want

// 5. Manually update the password hash
$user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);

// 6. Save back to the system users collection
$app->dataStorage->save('system/users', $user);

echo "<h2>Reset Successful</h2>";
echo "Username Found: <b>{$username}</b><br>";
echo "Temporary Password: <b>{$newPassword}</b><br><br>";
echo "<span style='color:red;'><b>DELETE THIS FILE (fix.php) IMMEDIATELY!</b></span>";
