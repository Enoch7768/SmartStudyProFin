<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(?string $path = null): PDO
    {
        $path ??= (string) Config::get('database.path');
        if ($path === '') throw new RuntimeException('The database path is not configured.');
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('The private storage directory could not be created.');
        if (!is_writable($directory)) throw new RuntimeException('The private storage directory is not writable.');
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = DELETE');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        if (is_file($path)) chmod($path, 0600);
        return $pdo;
    }
}
