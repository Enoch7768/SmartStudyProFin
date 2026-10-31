<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed in this PHP runtime.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Migrator;

$temporary = sys_get_temp_dir() . '/ssp-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create test storage.');
try {
    $pdo = Database::connect($temporary . '/test.sqlite');
    $migrationPath = dirname(__DIR__) . '/database/migrations';
    if (Migrator::run($pdo, $migrationPath) !== ['001_identity']) throw new RuntimeException('Initial migration did not run.');
    if (Migrator::run($pdo, $migrationPath) !== []) throw new RuntimeException('Migrations are not idempotent.');
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, :created_at, :updated_at)');
    $insert->execute(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password_hash' => password_hash('a-strong-test-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $userId = (int) $pdo->lastInsertId();
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'administrator'")->fetchColumn();
    $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (:user_id, :role_id, :assigned_at)');
    $assign->execute(['user_id' => $userId, 'role_id' => $roleId, 'assigned_at' => $now]);
    if (!Auth::hasRole($pdo, $userId, 'administrator')) throw new RuntimeException('Role assignment failed.');
    if (!Auth::hasPermission($pdo, $userId, 'manage_users')) throw new RuntimeException('Permission assignment failed.');
    for ($attempt = 0; $attempt < 8; $attempt++) {
        if (Auth::attempt($pdo, 'admin@example.test', 'incorrect-password')) throw new RuntimeException('Incorrect password was accepted.');
    }
    if (Auth::attempt($pdo, 'admin@example.test', 'a-strong-test-password')) throw new RuntimeException('Login throttle did not activate.');
    $pdo->exec('DELETE FROM login_attempts');
    if (!Auth::attempt($pdo, 'admin@example.test', 'a-strong-test-password')) throw new RuntimeException('Valid login was rejected.');
    Auth::logout();
    $duplicateRejected = false;
    try {
        $insert->execute(['name' => 'Duplicate', 'email' => 'ADMIN@example.test', 'password_hash' => null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    } catch (PDOException) {
        $duplicateRejected = true;
    }
    if (!$duplicateRejected) throw new RuntimeException('Case-insensitive email uniqueness failed.');
    $pdo->exec("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES ($userId, 'test-hash', '2099-01-01 00:00:00', '$now')");
    $pdo->exec('DELETE FROM users WHERE id = ' . $userId);
    if ((int) $pdo->query("SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash = 'test-hash'")->fetchColumn() !== 0) throw new RuntimeException('Foreign-key cascade failed.');
    echo "PASS SQLite migrations and idempotency\nPASS role assignment\nPASS case-insensitive email uniqueness\nPASS foreign-key cascade\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($temporary);
}
