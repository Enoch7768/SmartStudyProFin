<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function attempt(PDO $pdo, string $email, string $password): bool
    {
        Session::start();
        $email = trim($email);
        $emailHash = hash('sha256', strtolower($email));
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $cutoff = gmdate('Y-m-d H:i:s', time() - 900);
        $attempts = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_hash = :email_hash AND ip_hash = :ip_hash AND attempted_at >= :cutoff');
        $attempts->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash, 'cutoff' => $cutoff]);
        if ((int) $attempts->fetchColumn() >= 8) return false;

        $statement = $pdo->prepare('SELECT id, password_hash, status FROM users WHERE email = :email COLLATE NOCASE LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user || $user['status'] !== 'active' || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            $record = $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_hash, attempted_at) VALUES (:email_hash, :ip_hash, :attempted_at)');
            $record->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash, 'attempted_at' => gmdate('Y-m-d H:i:s')]);
            return false;
        }

        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $now = gmdate('Y-m-d H:i:s');
        $update = $pdo->prepare('UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
        $update->execute(['last_login_at' => $now, 'updated_at' => $now, 'id' => $user['id']]);
        $clear = $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = :email_hash AND ip_hash = :ip_hash');
        $clear->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash]);
        return true;
    }

    public static function user(PDO $pdo): ?array
    {
        Session::start();
        $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) return null;
        $statement = $pdo->prepare('SELECT id, name, email, avatar_url, status, email_verified_at FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return $user && $user['status'] === 'active' ? $user : null;
    }

    public static function hasRole(PDO $pdo, int $userId, string $role): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :user_id AND r.slug = :role LIMIT 1');
        $statement->execute(['user_id' => $userId, 'role' => $role]);
        return (bool) $statement->fetchColumn();
    }

    public static function hasPermission(PDO $pdo, int $userId, string $permission): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM user_roles ur INNER JOIN role_permissions rp ON rp.role_id = ur.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = :user_id AND p.slug = :permission LIMIT 1');
        $statement->execute(['user_id' => $userId, 'permission' => $permission]);
        return (bool) $statement->fetchColumn();
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
