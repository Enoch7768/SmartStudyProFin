<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;
use App\Core\Security;

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $name = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $name) . '.php';
    if (is_file($path)) require $path;
});
Config::load($root);
$vendorAutoload = rtrim((string) Config::get('storage.path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorAutoload)) require_once $vendorAutoload;
Security::sendHeaders();
Session::start();

if (PHP_SAPI !== 'cli' && !App\Core\Installer::isInstalled()) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $basePath = str_ends_with($scriptName, '/install/index.php')
        ? rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/')
        : rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
    $installPath = $basePath . '/install';
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (!in_array($requestPath, [$installPath, $installPath . '/', $installPath . '/index.php'], true)) {
        header('Location: ' . $installPath, true, 303);
        exit;
    }
}

return ['root' => $root, 'config' => Config::all()];
