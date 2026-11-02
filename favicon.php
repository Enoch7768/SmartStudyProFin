<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\Database;

$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif'
];

$path = null;
$mimeType = null;

try {
    $pdo = Database::connect();
    $setting = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key = 'site.favicon_media_id' LIMIT 1");
    $setting->execute();
    $faviconId = filter_var($setting->fetchColumn(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($faviconId !== false) {
        $statement = $pdo->prepare("SELECT storage_path, mime_type FROM media_assets WHERE id = :id AND visibility = 'public' LIMIT 1");
        $statement->execute(['id' => $faviconId]);
        $asset = $statement->fetch();

        if ($asset && in_array($asset['mime_type'], $allowedTypes, true)) {
            $storage = realpath((string) Config::get('storage.path'));
            $candidate = $storage ? realpath($storage . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim((string) $asset['storage_path'], '/\\'))) : false;
            $prefix = $storage ? rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';

            if ($candidate && str_starts_with($candidate, $prefix) && is_file($candidate)) {
                $path = $candidate;
                $mimeType = $asset['mime_type'];
            }
        }
    }
} catch (Throwable) {
}

if ($path === null) {
    $path = __DIR__ . '/Smart_Study_Logo_Fin-removebg-preview.png';
    $mimeType = 'image/png';
}

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');
header('Content-Disposition: inline; filename="favicon"');

readfile($path);
