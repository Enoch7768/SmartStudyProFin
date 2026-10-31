<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $name = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $name) . '.php';
    if (is_file($path)) require $path;
});
Config::load($root);
Security::sendHeaders();
Session::start();
return ['root' => $root, 'config' => Config::all()];
