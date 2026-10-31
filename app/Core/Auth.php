<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    public static function attempt(PDO $pdo, string $email, string $password): bool
    {
        Session::start();
        $email = trim($email);
        $emailHash = hash('sha256', strtolower($email));
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $cutoff = gmdate('Y-m-d H:i:s', time() - 900);
        $prune = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :prune_before');
        $prune->execute(['prune_before' => gmdate('Y-m-d H:i:s', time() - 86400)]);
        $attempts = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_hash = :email_hash AND ip_hash = :ip_hash AND attempted_at >= :cutoff');
        $attempts->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash, 'cutoff' => $cutoff]);
        if ((int) $attempts->fetchColumn() >= 8) return false;

        $statement = $pdo->prepare('SELECT id, password_hash, status, auth_version FROM users WHERE email = :email COLLATE NOCASE LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $passwordHash = is_array($user) && is_string($user['password_hash'] ?? null) && $user['password_hash'] !== '' ? $user['password_hash'] : self::DUMMY_PASSWORD_HASH;
        $passwordValid = password_verify($password, $passwordHash);
        if (!$user || $user['status'] !== 'active' || empty($user['password_hash']) || !$passwordValid) {
            $record = $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_hash, attempted_at) VALUES (:email_hash, :ip_hash, :attempted_at)');
            $record->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash, 'attempted_at' => gmdate('Y-m-d H:i:s')]);
            return false;
        }

        Session::regenerate();
        unset($_SESSION['_csrf']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['auth_version'] = (int) $user['auth_version'];
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
        $statement = $pdo->prepare('SELECT id, name, email, avatar_url, status, email_verified_at, auth_version FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        if (!$user || $user['status'] !== 'active') {
            Session::destroy();
            return null;
        }
        if (!isset($_SESSION['auth_version']) || (int) $_SESSION['auth_version'] !== (int) $user['auth_version']) {
            Session::destroy();
            return null;
        }
        return $user;
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
