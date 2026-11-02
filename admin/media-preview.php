<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user || !Auth::hasRole($pdo, (int) $user['id'], 'administrator') || !Auth::hasPermission($pdo, (int) $user['id'], 'manage_content')) {
    http_response_code(403);
    exit('Administrator access is required.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code($id ? 405 : 404);
    header('Allow: GET, HEAD');
    exit;
}

$query = $pdo->prepare('SELECT storage_path, mime_type, original_name, file_size FROM media_assets WHERE id = :id LIMIT 1');
$query->execute(['id' => $id]);
$asset = $query->fetch();
if (!$asset) {
    http_response_code(404);
    exit('Media item not found.');
}

$storage = realpath((string) Config::get('storage.path'));
$path = $storage !== false ? realpath($storage . DIRECTORY_SEPARATOR . ltrim((string) $asset['storage_path'], '/\\')) : false;
if ($storage === false || $path === false || !str_starts_with($path, $storage . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Media item not found.');
}

$inlineTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/plain', 'text/csv', 'audio/mpeg', 'video/mp4'];
$download = ($_GET['download'] ?? '') === '1' || !in_array($asset['mime_type'], $inlineTypes, true);
$name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $asset['original_name'])) ?: 'media-file';
header('Content-Type: ' . $asset['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Security-Policy: default-src \'none\'; sandbox');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($path);
exit;
