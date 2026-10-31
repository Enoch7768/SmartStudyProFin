<?php

declare(strict_types=1);

namespace App\\Identity;

use PDO;
use RuntimeException;

final class AccountService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function register(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || strlen($name) > 120) throw new RuntimeException('Enter a name between 1 and 120 characters.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new RuntimeException('Enter a valid email address.');
        if (strlen($password) < 12 || strlen($password) > 1024) throw new RuntimeException('Use a password with at least 12 characters.');

        $this->pdo->beginTransaction();
        try {
            $now = gmdate('Y-m-d H:i:s');
            $insert = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, 'active', :created_at, :updated_at)");
            $insert->execute(['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'created_at' => $now, 'updated_at' => $now]);
            $userId = (int) $this->pdo->lastInsertId();
            $role = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) SELECT :user_id, id, :assigned_at FROM roles WHERE slug = 'student'");
            $role->execute(['user_id' => $userId, 'assigned_at' => $now]);
            if ($role->rowCount() !== 1) throw new RuntimeException('The student role is not available.');
            $profile = $this->pdo->prepare('INSERT INTO student_profiles (user_id, created_at, updated_at) VALUES (:user_id, :created_at, :updated_at)');
            $profile->execute(['user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            $this->pdo->commit();
            return $userId;
        } catch (\\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof \\PDOException && str_contains(strtolower($exception->getMessage()), 'unique')) throw new RuntimeException('That email address is already registered.');
            throw $exception;
        }
    }

    public function profile(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT u.id, u.name, u.email, u.avatar_url, u.status, u.email_verified_at, u.created_at, p.phone, p.location, p.bio, p.preferred_theme FROM users u LEFT JOIN student_profiles p ON p.user_id = u.id WHERE u.id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        return $statement->fetch() ?: [];
    }

    public function updateProfile(int $userId, string $name, string $phone, string $location, string $bio, string $theme): void
    {
        $name = trim($name);
        $phone = trim($phone);
        $location = trim($location);
        $bio = trim($bio);
        if ($name === '' || strlen($name) > 120) throw new RuntimeException('Enter a name between 1 and 120 characters.');
        if (strlen($phone) > 40 || strlen($location) > 120 || strlen($bio) > 500) throw new RuntimeException('One or more profile fields are too long.');
        if (!in_array($theme, ['system', 'light', 'dark'], true)) throw new RuntimeException('Choose a valid theme preference.');
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare('UPDATE users SET name = :name, updated_at = :updated_at WHERE id = :id AND status = :status');
            $user->execute(['name' => $name, 'updated_at' => $now, 'id' => $userId, 'status' => 'active']);
            if ($user->rowCount() < 0) throw new RuntimeException('The profile could not be updated.');
            $profile = $this->pdo->prepare("INSERT INTO student_profiles (user_id, phone, location, bio, preferred_theme, created_at, updated_at) VALUES (:user_id, :phone, :location, :bio, :theme, :created_at, :updated_at) ON CONFLICT(user_id) DO UPDATE SET phone = excluded.phone, location = excluded.location, bio = excluded.bio, preferred_theme = excluded.preferred_theme, updated_at = excluded.updated_at");
            $profile->execute(['user_id' => $userId, 'phone' => $phone, 'location' => $location, 'bio' => $bio, 'theme' => $theme, 'created_at' => $now, 'updated_at' => $now]);
            $this->pdo->commit();
        } catch (\\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 12 || strlen($newPassword) > 1024) throw new RuntimeException('Use a new password with at least 12 characters.');
        $statement = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = :id AND status = 'active' LIMIT 1");
        $statement->execute(['id' => $userId]);
        $hash = $statement->fetchColumn();
        if (!is_string($hash) || $hash === '' || !password_verify($currentPassword, $hash)) throw new RuntimeException('Your current password is incorrect.');
        $update = $this->pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id');
        $update->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $userId]);
        $tokens = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL');
        $tokens->execute(['used_at' => gmdate('Y-m-d H:i:s'), 'user_id' => $userId]);
    }

    public function allowResetRequest(string $email, string $ip): bool
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $ipHash = hash('sha256', $ip);
        $cutoff = gmdate('Y-m-d H:i:s', time() - 3600);
        $delete = $this->pdo->prepare('DELETE FROM password_reset_requests WHERE requested_at < :cutoff');
        $delete->execute(['cutoff' => gmdate('Y-m-d H:i:s', time() - 86400)]);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM password_reset_requests WHERE email_hash = :email_hash AND ip_hash = :ip_hash AND requested_at >= :cutoff');
        $count->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash, 'cutoff' => $cutoff]);
        if ((int) $count->fetchColumn() >= 3) return false;
        $insert = $this->pdo->prepare('INSERT INTO password_reset_requests (email_hash, ip_hash, requested_at) VALUES (:email_hash, :ip_hash, :requested_at)');
        $insert->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash, 'requested_at' => gmdate('Y-m-d H:i:s')]);
        return true;
    }

    public function createResetToken(string $email): ?string
    {
        $statement = $this->pdo->prepare("SELECT id FROM users WHERE email = :email COLLATE NOCASE AND status = 'active' LIMIT 1");
        $statement->execute(['email' => strtolower(trim($email))]);
        $userId = $statement->fetchColumn();
        if (!$userId) return null;
        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $invalidate = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL');
            $invalidate->execute(['used_at' => $now, 'user_id' => $userId]);
            $insert = $this->pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :token_hash, :expires_at, :created_at)');
            $insert->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800), 'created_at' => $now]);
            $this->pdo->commit();
            return $token;
        } catch (\\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function resetPassword(string $token, string $password): bool
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token) || strlen($password) < 12 || strlen($password) > 1024) return false;
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare("SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now LIMIT 1");
            $find->execute(['token_hash' => hash('sha256', $token), 'now' => $now]);
            $record = $find->fetch();
            if (!$record) {
                $this->pdo->rollBack();
                return false;
            }
            $update = $this->pdo->prepare("UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :user_id AND status = 'active'");
            $update->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => $now, 'user_id' => $record['user_id']]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }
            $used = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL');
            $used->execute(['used_at' => $now, 'user_id' => $record['user_id']]);
            $this->pdo->commit();
            return true;
        } catch (\\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
