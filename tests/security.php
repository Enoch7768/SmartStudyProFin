<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rules = (string) file_get_contents($root . '/.htaccess');
$avatarRules = (string) file_get_contents($root . '/uploads/avatars/.htaccess');
$security = (string) file_get_contents($root . '/app/Core/Security.php');
$bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
$auth = (string) file_get_contents($root . '/app/Core/Auth.php');
$gateway = (string) file_get_contents($root . '/app/Commerce/PaymentGateway.php');

foreach (['app', 'bootstrap', 'config', 'database', 'scripts', 'tests', 'storage', 'cms', 'docs'] as $directory) {
    if (!str_contains($rules, $directory)) throw new RuntimeException('The web server rules do not protect ' . $directory . '.');
}
foreach (['sqlite', 'sqlite3', 'db', 'sql', 'bak', 'backup', 'zip', 'tar', 'gz'] as $extension) {
    if (!str_contains($rules, $extension)) throw new RuntimeException('The web server rules do not deny .' . $extension . ' files.');
}
if (str_contains($rules, 'test.php') || is_file($root . '/test.php') || is_file($root . '/cms-init.php')) throw new RuntimeException('A legacy CMS diagnostic endpoint is still present.');
if (!str_contains($avatarRules, 'Options -Indexes -ExecCGI') || !str_contains($avatarRules, 'phtml|phar')) throw new RuntimeException('Avatar uploads are not protected against script execution.');
foreach (['X-Content-Type-Options: nosniff', 'X-Frame-Options: DENY', 'Referrer-Policy:', 'Permissions-Policy:'] as $header) {
    if (!str_contains($security, $header)) throw new RuntimeException('A global security header is missing: ' . $header);
}
if (!str_contains($bootstrap, 'Security::sendHeaders();')) throw new RuntimeException('Global security headers are not applied by the application bootstrap.');
if (!str_contains($auth, 'DUMMY_PASSWORD_HASH') || !str_contains($auth, 'unset(') && str_contains($auth, '_csrf')) throw new RuntimeException('Authentication timing or post-login CSRF rotation hardening is missing.');
if (!str_contains($gateway, 'LIBXML_NONET') || !str_contains($gateway, 'CURLOPT_FOLLOWLOCATION => false') || !str_contains($gateway, 'www.sandbox.paypal.com')) throw new RuntimeException('Payment provider transport hardening is incomplete.');

echo "PASS web root and legacy endpoint protections\nPASS upload script execution protection\nPASS global response security headers\nPASS authentication timing and CSRF rotation controls\nPASS payment transport and XML parser hardening\n";
