<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\Database;

$pdo = Database::connect();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit;
}
$statement = $pdo->prepare("SELECT storage_path, mime_type, original_name FROM media_assets WHERE id = :id AND visibility = 'public' LIMIT 1");
$statement->execute(['id' => $id]);
$asset = $statement->fetch();
if (!$asset || !str_starts_with($asset['mime_type'], 'image/')) {
    http_response_code(404);
    exit;
}
$storage = realpath((string) Config::get('storage.path'));
$path = realpath((string) Config::get('storage.path') . '/' . $asset['storage_path']);
if (!$storage || !$path || !str_starts_with($path, rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $asset['mime_type']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($asset['original_name'])) . '"');
readfile($path);