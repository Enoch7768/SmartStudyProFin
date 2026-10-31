<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rules = (string) file_get_contents($root . '/.htaccess');
$avatarRules = (string) file_get_contents($root . '/uploads/avatars/.htaccess');
$security = (string) file_get_contents($root . '/app/Core/Security.php');
$bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
$auth = (string) file_get_contents($root . '/app/Core/Auth.php');
    $login = (string) file_get_contents($root . '/login.php');
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
if (!str_contains($bootstrap, 'use App\\Core\\Security;') || !str_contains($bootstrap, 'Security::sendHeaders();')) throw new RuntimeException('Global security headers are not correctly imported and applied by the application bootstrap.');
if (!str_contains($rules, 'well-known(?:/|$)) - [F,L,NC]') || !str_contains($rules, 'RewriteRule ^privacy/?$ privacy.php') || !str_contains($rules, 'RewriteRule ^eula/?$ eula.php')) throw new RuntimeException('Dotfile or legal document routing protections are incomplete.');
if (!is_file($root . '/legal-consent.php') || !str_contains($login, 'LegalAcceptanceService') || !str_contains($login, 'legal-consent.php')) throw new RuntimeException('Existing accounts are not prompted for current legal terms.');
if (!str_contains((string) file_get_contents($root . '/LICENSE'), 'PROPRIETARY SOFTWARE') || !str_contains((string) file_get_contents($root . '/EULA.md'), 'retains all right')) throw new RuntimeException('The proprietary software licence notices are missing.');
if (!preg_match("/DUMMY_PASSWORD_HASH = '([^']+)'/", $auth, $dummyHash) || !password_verify('smartstudypro-dummy-password', $dummyHash[1]) || !str_contains($auth, 'unset(') || !str_contains($auth, '_csrf') || !str_contains($auth, 'Cache-Control: private, no-store')) throw new RuntimeException('Authentication timing, session caching or post-login CSRF rotation hardening is missing.');
if (!str_contains($gateway, 'LIBXML_NONET') || !str_contains($gateway, 'CURLOPT_FOLLOWLOCATION => false') || !str_contains($gateway, 'www.sandbox.paypal.com')) throw new RuntimeException('Payment provider transport hardening is incomplete.');

echo "PASS web root and legacy endpoint protections\nPASS upload script execution protection\nPASS global response security headers\nPASS authentication timing and CSRF rotation controls\nPASS payment transport and XML parser hardening\n";
