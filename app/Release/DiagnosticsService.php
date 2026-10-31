<?php

declare(strict_types=1);

namespace App\Release;

use App\Core\Config;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Migrator;
use Throwable;

final class DiagnosticsService
{
    public function __construct(private readonly string $root)
    {
    }

    public function report(): array
    {
        $storage = (string) Config::get('storage.path');
        $databasePath = (string) Config::get('database.path');
        $checks = Installer::requirements();
        $checks['Private storage configured'] = $storage !== '';
        $checks['Storage outside public web root'] = Installer::storageIsPrivate($this->root);
        $checks['Database inside private storage'] = Installer::databaseIsPrivate();
        $checks['Storage writable'] = $storage !== '' && (is_dir($storage) ? is_writable($storage) : is_writable(dirname($storage)));
        $checks['Installation completed'] = Installer::isInstalled();
        $checks['Application key valid'] = Installer::applicationKeyIsValid();
        $checks['Database file exists'] = $databasePath !== '' && is_file($databasePath);
        $migrationStatus = ['applied' => [], 'pending' => []];
        $databaseIntegrity = false;
        if ($checks['PDO SQLite'] && $checks['Database file exists'] && $checks['Database inside private storage']) {
            try {
                $pdo = Database::connect();
                $databaseIntegrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok';
                $migrationStatus = Migrator::status($pdo, $this->root . '/database/migrations');
                $checks['Database connection'] = true;
                $checks['Database integrity'] = $databaseIntegrity;
                $checks['Migrations current'] = count($migrationStatus['pending']) === 0;
            } catch (Throwable) {
                $checks['Database connection'] = false;
                $checks['Database integrity'] = false;
                $checks['Migrations current'] = false;
            }
        } else {
            $checks['Database connection'] = false;
            $checks['Database integrity'] = false;
            $checks['Migrations current'] = false;
        }
        $backupPath = $storage !== '' ? $storage . '/backups' : '';
        $checks['Backup directory writable'] = $backupPath !== '' && (is_dir($backupPath) ? is_writable($backupPath) : ($checks['Storage writable'] ?? false));
        $free = $storage !== '' && is_dir($storage) ? @disk_free_space($storage) : false;
        $versionPath = $this->root . '/VERSION';
        $version = is_file($versionPath) ? trim((string) file_get_contents($versionPath)) : 'unknown';
        $failed = in_array(false, $checks, true);
        return [
            'version' => $version,
            'checks' => $checks,
            'passed' => count(array_filter($checks)),
            'total' => count($checks),
            'healthy' => !$failed,
            'pending_migrations' => $migrationStatus['pending'],
            'free_disk_bytes' => is_float($free) || is_int($free) ? (int) $free : null,
        ];
    }
}
