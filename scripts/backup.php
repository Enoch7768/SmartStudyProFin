<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$application = require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\Database;
use App\Release\BackupService;

$action = (string) ($argv[1] ?? 'help');
$root = dirname(__DIR__);
$version = is_file($root . '/VERSION') ? trim((string) file_get_contents($root . '/VERSION')) : 'unknown';
$service = new BackupService(Database::connect(), (string) Config::get('storage.path'), (string) Config::get('database.path'), (string) Config::get('app.key'), $version);

try {
    if ($action === 'create') {
        $backup = $service->create();
        $verification = $service->verify($backup['id']);
        if (!$verification['valid']) throw new RuntimeException('Backup verification failed: ' . $verification['message']);
        echo json_encode(['backup' => $backup, 'verification' => $verification], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($action === 'list') {
        echo json_encode($service->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($action === 'verify' && isset($argv[2])) {
        $verification = $service->verify((string) $argv[2]);
        echo json_encode($verification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (!$verification['valid']) exit(1);
    } else {
        echo "SmartStudyPro backup utility\n";
        echo "php scripts/backup.php create\n";
        echo "php scripts/backup.php list\n";
        echo "php scripts/backup.php verify BACKUP_ID\n";
        exit($action === 'help' ? 0 : 2);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
