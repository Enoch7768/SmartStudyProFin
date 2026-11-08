<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) {
    http_response_code(401);
    exit('Sign in to view purchased files.');
}
$assetId = filter_var($_GET['asset'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$itemId = filter_var($_GET['item'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$commerce = new CommerceService($pdo);
$file = $assetId > 0 ? $commerce->purchasedReadableAsset((int) $user['id'], $assetId) : ($itemId > 0 ? $commerce->purchasedReadableFile((int) $user['id'], $itemId) : null);
$inlineTypes = ['application/pdf', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!$file || !in_array($file['mime_type'], $inlineTypes, true)) {
    http_response_code(404);
    exit('Purchased preview not found.');
}
$storage = realpath((string) Config::get('storage.path'));
$path = $storage !== false ? realpath($storage . DIRECTORY_SEPARATOR . ltrim((string) $file['storage_path'], '/\\')) : false;
if ($storage === false || $path === false || !str_starts_with($path, $storage . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Purchased preview not found.');
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['original_name'])) ?: 'preview';
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; sandbox');
header('Cache-Control: private, no-store');
if ($method !== 'HEAD') readfile($path);
exit;
