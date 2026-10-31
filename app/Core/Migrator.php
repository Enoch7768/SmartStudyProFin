<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Migrator
{
    public static function status(PDO $pdo, string $directory): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $exists = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'")->fetchColumn();
        $applied = $exists ? array_fill_keys($pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true) : [];
        $pending = [];
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (!isset($applied[$version])) $pending[] = $version;
        }
        return ['applied' => array_keys($applied), 'pending' => $pending];
    }

    public static function run(PDO $pdo, string $directory): array
    {
        if (!is_dir($directory)) throw new RuntimeException('The migration directory is missing.');
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $applied = array_fill_keys($pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true);
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $ran = [];
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (isset($applied[$version])) continue;
            $migration = require $file;
            if (!is_callable($migration)) throw new RuntimeException('Invalid migration: ' . $version);
            $pdo->beginTransaction();
            try {
                $migration($pdo);
                $statement = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)');
                $statement->execute(['version' => $version, 'applied_at' => gmdate('Y-m-d H:i:s')]);
                $pdo->commit();
                $ran[] = $version;
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
        }
        return $ran;
    }
}
