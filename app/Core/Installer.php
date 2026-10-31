<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Installer
{
    public static function isInstalled(): bool
    {
        $path = (string) Config::get('storage.path');
        return $path !== '' && is_file($path . '/installed.lock');
    }

    public static function storageIsPrivate(string $root): bool
    {
        $storage = (string) Config::get('storage.path');
        $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $documentRoot = realpath($documentRoot !== '' ? $documentRoot : $root) ?: realpath($root);
        if ($storage === '' || $documentRoot === false) return false;
        $candidate = $storage;
        while (!file_exists($candidate) && dirname($candidate) !== $candidate) $candidate = dirname($candidate);
        $candidate = realpath($candidate);
        if ($candidate === false) return false;
        $documentRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
        return $candidate !== $documentRoot && !str_starts_with($candidate, $documentRoot . DIRECTORY_SEPARATOR);
    }

    public static function databaseIsPrivate(): bool
    {
        $storage = rtrim(str_replace('\\', '/', (string) Config::get('storage.path')), '/');
        $database = str_replace('\\', '/', (string) Config::get('database.path'));
        if ($storage === '' || !str_starts_with($database, $storage . '/')) return false;
        $storageReal = realpath($storage);
        $databaseDirectory = realpath(dirname($database));
        if ($storageReal !== false && $databaseDirectory !== false) {
            $storageReal = rtrim($storageReal, DIRECTORY_SEPARATOR);
            $directoryIsPrivate = $databaseDirectory === $storageReal || str_starts_with($databaseDirectory, $storageReal . DIRECTORY_SEPARATOR);
            if (!$directoryIsPrivate || is_link($database)) return false;
            if (file_exists($database)) {
                $databaseReal = realpath($database);
                return $databaseReal !== false && str_starts_with($databaseReal, $storageReal . DIRECTORY_SEPARATOR);
            }
            return true;
        }
        return true;
    }

    public static function applicationKeyIsValid(): bool
    {
        return preg_match('/^[a-f0-9]{64}$/i', (string) Config::get('app.key')) === 1;
    }

    public static function requirements(): array
    {
        return [
            'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO SQLite' => extension_loaded('pdo_sqlite'),
            'OpenSSL' => extension_loaded('openssl'),
            'JSON' => extension_loaded('json'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'Secure random generator' => function_exists('random_bytes'),
        ];
    }

    public static function install(string $root, string $name, string $email, string $password): void
    {
        if (self::isInstalled()) throw new RuntimeException('This installation has already been completed.');
        foreach (self::requirements() as $label => $passed) if (!$passed) throw new RuntimeException('Server requirement failed: ' . $label);
        $name = trim($name);
        $email = trim($email);
        if ($name === '' || strlen($name) > 480 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid administrator name and email address.');
        if (strlen($password) < 12 || strlen($password) > 1024) throw new RuntimeException('The administrator password must contain at least 12 characters.');
        $storage = (string) Config::get('storage.path');
        if ($storage === '' || !self::storageIsPrivate($root)) throw new RuntimeException('Set APP_STORAGE_PATH to a writable directory outside the public web root.');
        if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) throw new RuntimeException('The private storage directory could not be created.');
        chmod($storage, 0700);
        if (!self::databaseIsPrivate()) throw new RuntimeException('Set DB_PATH to a SQLite file inside APP_STORAGE_PATH.');
        $keyPath = $storage . '/app.key';
        if (is_link($keyPath)) throw new RuntimeException('The application key cannot be a symbolic link.');
        if (is_file($keyPath)) {
            $storedKey = trim((string) file_get_contents($keyPath));
            if (preg_match('/^[a-f0-9]{64}$/i', $storedKey) !== 1) throw new RuntimeException('The stored application key is invalid.');
            $configuredKey = (string) Config::get('app.key');
            if ($configuredKey !== '' && !hash_equals($storedKey, $configuredKey)) throw new RuntimeException('APP_KEY does not match the stored application key.');
            chmod($keyPath, 0600);
        } else {
            $configuredKey = (string) Config::get('app.key');
            if ($configuredKey !== '' && preg_match('/^[a-f0-9]{64}$/i', $configuredKey) !== 1) throw new RuntimeException('APP_KEY must contain exactly 64 hexadecimal characters.');
            $temporary = $keyPath . '.tmp-' . bin2hex(random_bytes(4));
            $newKey = $configuredKey !== '' ? $configuredKey : bin2hex(random_bytes(32));
            if (file_put_contents($temporary, $newKey, LOCK_EX) === false || !rename($temporary, $keyPath)) {
                @unlink($temporary);
                throw new RuntimeException('The application key could not be saved.');
            }
            chmod($keyPath, 0600);
        }
        $pdo = Database::connect();
        Migrator::run($pdo, $root . '/database/migrations');
        $existingAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'administrator'")->fetchColumn();
        if ($existingAdmin > 0) {
            self::writeLock($storage);
            return;
        }
        $pdo->beginTransaction();
        try {
            $now = gmdate('Y-m-d H:i:s');
            $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, status, email_verified_at, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, :verified_at, :created_at, :updated_at)');
            $statement->execute(['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $userId = (int) $pdo->lastInsertId();
            $roleId = $pdo->query("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1")->fetchColumn();
            if (!$roleId) throw new RuntimeException('The administrator role was not installed.');
            $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (:user_id, :role_id, :assigned_at)');
            $assign->execute(['user_id' => $userId, 'role_id' => $roleId, 'assigned_at' => $now]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        self::writeLock($storage);
    }

    private static function writeLock(string $storage): void
    {
        $path = $storage . '/installed.lock';
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, gmdate('c'), LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Installation finished but the lock could not be written. Keep the site offline and check private storage permissions.');
        }
        chmod($path, 0600);
    }
}
