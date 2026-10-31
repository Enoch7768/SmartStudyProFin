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
    exit('Sign in to access your purchases.');
}
$itemId = filter_var($_GET['item'] ?? null, FILTER_VALIDATE_INT);
$file = $itemId ? (new CommerceService($pdo))->purchasedFile((int) $user['id'], (int) $itemId) : null;
if (!$file) {
    http_response_code(404);
    exit('Purchased file not found.');
}
$storage = realpath((string) Config::get('storage.path'));
$path = $storage !== false ? realpath($storage . DIRECTORY_SEPARATOR . ltrim((string) $file['storage_path'], '/\\')) : false;
if ($storage === false || $path === false || !str_starts_with($path, $storage . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Purchased file not found.');
}
$handle = fopen($path, 'rb');
if (!$handle) {
    http_response_code(404);
    exit('Purchased file not found.');
}
$name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['original_name'])) ?: 'download';
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
fpassthru($handle);
fclose($handle);
exit;
