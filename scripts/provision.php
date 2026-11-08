<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php scripts/provision.php \"Administrator Name\" admin@example.com \"Strong unique password\"\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

try {
    App\Core\Installer::install($root, (string) $argv[1], (string) $argv[2], (string) $argv[3]);
    fwrite(STDOUT, "Database and administrator provisioning completed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
