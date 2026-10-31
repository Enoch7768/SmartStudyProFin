<?php

declare(strict_types=1);

$application = require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Migrator;

$checks = Installer::requirements();
$storage = (string) Config::get('storage.path');
$checks['Storage path configured'] = $storage !== '';
$checks['Storage outside public web root'] = Installer::storageIsPrivate($application['root']);
$checks['Storage directory writable'] = is_dir($storage) ? is_writable($storage) : is_writable(dirname($storage));
$checks['Installation lock present'] = Installer::isInstalled();
if ($checks['PDO SQLite'] && $checks['Storage path configured'] && $checks['Storage outside public web root'] && $checks['Storage directory writable']) {
    try {
        $pdo = Database::connect();
        $checks['Database connection'] = true;
        Migrator::run($pdo, $application['root'] . '/database/migrations');
        $checks['Database migrations'] = true;
    } catch (Throwable $exception) {
        $checks['Database connection and migrations'] = false;
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    }
}
$failed = false;
foreach ($checks as $label => $passed) {
    printf("[%s] %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
