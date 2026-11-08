<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$name = trim((string) getenv('SSP_ADMIN_NAME'));
$email = trim((string) getenv('SSP_ADMIN_EMAIL'));
$password = (string) getenv('SSP_ADMIN_PASSWORD');

if ($name === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Set SSP_ADMIN_NAME, SSP_ADMIN_EMAIL and SSP_ADMIN_PASSWORD in the process environment, then run this command again.\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

try {
    App\Core\Installer::install($root, $name, $email, $password);
    fwrite(STDOUT, "Database and administrator provisioning completed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    unset($password);
}
