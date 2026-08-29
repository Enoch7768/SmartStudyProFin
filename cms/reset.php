<?php
// Load Cockpit
require(__DIR__.'/bootstrap.php');

// 1. Get all users to find the correct one
$users = $app->helper('auth')->getUsers();

if (empty($users)) {
    die("No users found in the database.");
}

// 2. Select the first user (usually the admin)
$user = $users[0];
$username = $user['user'];

// 3. Update the password
$newPassword = 'NewPassword123!'; // <--- CHANGE THIS
$user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);

// 4. Save the user back to the database
$app->helper('auth')->saveUser($user);

echo "Success! User <b>{$username}</b> password has been reset to: <b>{$newPassword}</b><br>";
echo "Delete this file (reset.php) immediately!";
