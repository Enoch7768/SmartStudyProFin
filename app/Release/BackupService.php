<?php

declare(strict_types=1);

namespace App\Release;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;

final class BackupService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $storagePath,
        private readonly string $databasePath,
        private readonly string $applicationKey,
        private readonly string $version
    ) {
    }

    public function create(): array
    {
        $storage = realpath($this->storagePath);
        if ($storage === false || !is_dir($storage) || is_link($this->storagePath) || !is_writable($storage)) throw new RuntimeException('Private storage is not available for backups.');
        if (preg_match('/^[a-f0-9]{64}$/i', $this->applicationKey) !== 1) throw new RuntimeException('The application key is not valid.');
        if (is_link($this->databasePath)) throw new RuntimeException('The database cannot be a symbolic link.');
        $database = realpath($this->databasePath);
        if ($database === false || !is_file($database) || !str_starts_with($database, $storage . DIRECTORY_SEPARATOR)) throw new RuntimeException('The database is not inside private storage.');
        $keyPath = $storage . '/app.key';
        $lockPath = $storage . '/installed.lock';
        if (!is_file($keyPath) || is_link($keyPath) || !is_file($lockPath) || is_link($lockPath)) throw new RuntimeException('The application key or installation lock is missing.');
        $storedKey = trim((string) file_get_contents($keyPath));
        if (!hash_equals($storedKey, $this->applicationKey)) throw new RuntimeException('The configured application key does not match private storage.');

        $backupRoot = $storage . '/backups';
        if (is_link($backupRoot)) throw new RuntimeException('The backup directory cannot be a symbolic link.');
        if (!is_dir($backupRoot) && !mkdir($backupRoot, 0700, true) && !is_dir($backupRoot)) throw new RuntimeException('The private backup directory could not be created.');
        $backupRootReal = realpath($backupRoot);
        if ($backupRootReal === false || !str_starts_with($backupRootReal, $storage . DIRECTORY_SEPARATOR)) throw new RuntimeException('The backup directory is not private.');

        $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $directory = $backupRootReal . DIRECTORY_SEPARATOR . $id;
        if (!mkdir($directory, 0700)) throw new RuntimeException('The backup directory could not be created.');

        try {
            $databaseBackup = $directory . '/database.sqlite';
            $quotedPath = $this->pdo->quote($databaseBackup);
            if (!is_string($quotedPath)) throw new RuntimeException('The database backup path could not be prepared.');
            $this->pdo->exec('VACUUM INTO ' . $quotedPath);
            chmod($databaseBackup, 0600);
            if (!copy($keyPath, $directory . '/app.key') || !copy($lockPath, $directory . '/installed.lock')) throw new RuntimeException('The private application files could not be copied.');
            chmod($directory . '/app.key', 0600);
            chmod($directory . '/installed.lock', 0600);
            $media = $storage . '/media';
            if (is_dir($media)) $this->copyDirectory($media, $directory . '/media');
            $files = $this->fileManifest($directory);
            $payload = [
                'backup_id' => $id,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'application_version' => $this->version,
                'files' => $files,
            ];
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $payload['signature'] = hash_hmac('sha256', $encoded, $this->applicationKey);
            $manifest = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $manifestPath = $directory . '/manifest.json';
            if (file_put_contents($manifestPath, $manifest, LOCK_EX) === false) throw new RuntimeException('The backup manifest could not be written.');
            chmod($manifestPath, 0600);
            return ['id' => $id, 'created_at' => $payload['created_at'], 'version' => $this->version, 'file_count' => count($files), 'total_bytes' => array_sum(array_column($files, 'size'))];
        } catch (\Throwable $exception) {
            $this->removeDirectory($directory);
            throw $exception;
        }
    }

    public function verify(string $id): array
    {
        if (!preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}$/', $id)) return ['valid' => false, 'message' => 'The backup identifier is invalid.'];
        try {
            $directory = $this->backupDirectory($id);
            $manifestPath = $directory . '/manifest.json';
            if (!is_file($manifestPath) || is_link($manifestPath)) throw new RuntimeException('The backup manifest is missing.');
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['backup_id'] ?? '') !== $id || !is_array($manifest['files'] ?? null)) throw new RuntimeException('The backup manifest is invalid.');
            $signature = (string) ($manifest['signature'] ?? '');
            unset($manifest['signature']);
            $encoded = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $expected = hash_hmac('sha256', $encoded, $this->applicationKey);
            if ($signature === '' || !hash_equals($expected, $signature)) throw new RuntimeException('The backup manifest signature does not match.');
            $verified = 0;
            foreach ($manifest['files'] as $file) {
                $relative = (string) ($file['path'] ?? '');
                if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\') || preg_match('/(^|\/)\.\.?($|\/)/', $relative) || preg_match('/^[a-zA-Z0-9._\/-]+$/', $relative) !== 1) throw new RuntimeException('The backup contains an unsafe file path.');
                $path = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_link($path) || !is_file($path)) throw new RuntimeException('A backup file is missing or unsafe.');
                $real = realpath($path);
                if ($real === false || !str_starts_with($real, $directory . DIRECTORY_SEPARATOR)) throw new RuntimeException('A backup file resolves outside the backup directory.');
                if ((int) filesize($path) !== (int) ($file['size'] ?? -1) || !hash_equals((string) ($file['sha256'] ?? ''), (string) hash_file('sha256', $path))) throw new RuntimeException('A backup file failed its checksum.');
                $verified++;
            }
            $databasePath = $directory . '/database.sqlite';
            if (!is_file($databasePath) || is_link($databasePath)) throw new RuntimeException('The database backup is missing.');
            $check = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $integrity = (string) $check->query('PRAGMA integrity_check')->fetchColumn();
            if ($integrity !== 'ok') throw new RuntimeException('The SQLite backup did not pass its integrity check.');
            if (!is_file($directory . '/app.key') || !hash_equals($this->applicationKey, trim((string) file_get_contents($directory . '/app.key')))) throw new RuntimeException('The backed-up application key does not match.');
            return ['valid' => true, 'message' => 'Backup integrity and file checksums passed.', 'file_count' => $verified, 'created_at' => (string) ($manifest['created_at'] ?? '')];
        } catch (\Throwable $exception) {
            return ['valid' => false, 'message' => $exception->getMessage()];
        }
    }

    public function all(): array
    {
        $root = $this->backupRoot();
        if (!is_dir($root)) return [];
        $items = [];
        foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!$entry->isDir() || $entry->isLink() || !preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}$/', $entry->getFilename())) continue;
            $manifestPath = $entry->getPathname() . '/manifest.json';
            if (!is_file($manifestPath) || is_link($manifestPath)) continue;
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) continue;
            $items[] = [
                'id' => $entry->getFilename(),
                'created_at' => (string) ($manifest['created_at'] ?? ''),
                'version' => (string) ($manifest['application_version'] ?? ''),
                'file_count' => count($manifest['files'] ?? []),
                'total_bytes' => array_sum(array_map(static fn (array $file): int => (int) ($file['size'] ?? 0), is_array($manifest['files'] ?? null) ? $manifest['files'] : [])),
            ];
        }
        usort($items, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));
        return $items;
    }

    private function backupRoot(): string
    {
        $storage = realpath($this->storagePath);
        if ($storage === false || is_link($this->storagePath)) throw new RuntimeException('Private storage is not available.');
        $root = $storage . '/backups';
        if (is_link($root)) throw new RuntimeException('The backup directory cannot be a symbolic link.');
        return $root;
    }

    private function backupDirectory(string $id): string
    {
        $root = realpath($this->backupRoot());
        $path = $this->backupRoot() . DIRECTORY_SEPARATOR . $id;
        $real = realpath($path);
        if ($root === false || $real === false || is_link($path) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) throw new RuntimeException('The requested backup was not found.');
        return $real;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (is_link($source)) throw new RuntimeException('The media directory cannot be a symbolic link.');
        if (!mkdir($destination, 0700, true) && !is_dir($destination)) throw new RuntimeException('The media backup directory could not be created.');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isLink()) throw new RuntimeException('Symbolic links are not allowed in private media.');
            $relative = substr($entry->getPathname(), strlen($source) + 1);
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($entry->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0700, true)) throw new RuntimeException('A media backup directory could not be created.');
            } elseif ($entry->isFile()) {
                if (!copy($entry->getPathname(), $target)) throw new RuntimeException('A private media file could not be copied.');
                chmod($target, 0600);
            }
        }
    }

    private function fileManifest(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()) throw new RuntimeException('Symbolic links are not allowed in backup files.');
            if (!$entry->isFile()) continue;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($directory) + 1));
            if ($relative === 'manifest.json') continue;
            $files[] = ['path' => $relative, 'size' => $entry->getSize(), 'sha256' => hash_file('sha256', $entry->getPathname())];
        }
        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        return $files;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($directory);
    }
}
