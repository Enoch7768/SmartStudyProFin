<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;
use App\Core\Security;
use App\Core\Url;

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

// NOTE: Automatic <base href> injection was removed.
// Url::to() already emits root-relative paths (e.g. /courses/testing).
// A <base> tag was causing browsers to resolve links as http://courses/ on localhost.

return ['root' => $root, 'config' => Config::all()];
