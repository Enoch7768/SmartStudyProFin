<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Release\BackupService;

$temporary = sys_get_temp_dir() . '/ssp-operations-test-' . bin2hex(random_bytes(8));
$storage = $temporary . '/private';
if (!mkdir($storage . '/media/2026/10', 0700, true)) throw new RuntimeException('Could not create private test storage.');

try {
    $databasePath = $storage . '/smartstudypro.sqlite';
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE sample_records (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sample_records (value) VALUES ('preserved data')");
    $key = bin2hex(random_bytes(32));
    file_put_contents($storage . '/app.key', $key);
    file_put_contents($storage . '/installed.lock', gmdate('c'));
    file_put_contents($storage . '/media/2026/10/lesson.pdf', 'private lesson media');
    chmod($storage . '/app.key', 0600);
    chmod($storage . '/installed.lock', 0600);
    chmod($storage . '/media/2026/10/lesson.pdf', 0600);

    $service = new BackupService($pdo, $storage, $databasePath, $key, '0.1.0');
    $created = $service->create();
    if (!preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}$/', $created['id'])) throw new RuntimeException('Backup ID format is invalid.');
    if ((int) $created['file_count'] !== 4) throw new RuntimeException('Backup did not include the database, key, lock and media file.');
    $verified = $service->verify($created['id']);
    if (!$verified['valid'] || (int) $verified['file_count'] !== 4) throw new RuntimeException('A complete backup did not verify.');
    if (count($service->all()) !== 1) throw new RuntimeException('Backup listing did not return the created backup.');

    $backupMedia = $storage . '/backups/' . $created['id'] . '/media/2026/10/lesson.pdf';
    file_put_contents($backupMedia, 'tampered media');
    $tampered = $service->verify($created['id']);
    if ($tampered['valid']) throw new RuntimeException('A modified backup passed checksum verification.');
    if ($service->verify('../outside')['valid']) throw new RuntimeException('A path traversal backup identifier was accepted.');

    echo "PASS consistent private SQLite backup\nPASS private media, application key and lock included\nPASS signed manifest and file checksum verification\nPASS tampering and unsafe identifiers rejected\n";
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) $remove($entry->getPathname());
        @rmdir($path);
    };
    $remove($temporary);
}
