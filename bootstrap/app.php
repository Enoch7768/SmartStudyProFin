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

// URL generation is explicit through Url::to() and Url::resolve().
// HTML page templates provide a root-relative base element where relative
// legacy links remain in use; the bootstrap intentionally emits no markup.

return ['root' => $root, 'config' => Config::all()];
