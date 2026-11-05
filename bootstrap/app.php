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

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    ob_start(static function (string $html): string {
        if (!preg_match('/<head\\b[^>]*>/i', $html) || preg_match('/<base\\b/i', $html)) return $html;

        $baseUrl = htmlspecialchars(Url::to('/'), ENT_QUOTES, 'UTF-8');
        return preg_replace('/(<head\\b[^>]*>)/i', '$1' . PHP_EOL . '<base href="' . $baseUrl . '">', $html, 1) ?? $html;
    });
}

if (PHP_SAPI !== 'cli' && !App\Core\Installer::isInstalled()) {
    $installPath = App\Core\Url::applicationBasePath() . '/install';
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (!in_array($requestPath, [$installPath, $installPath . '/', $installPath . '/index.php'], true)) {
        header('Location: ' . $installPath, true, 303);
        exit;
    }
}

return ['root' => $root, 'config' => Config::all()];
