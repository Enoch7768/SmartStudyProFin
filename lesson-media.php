<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;
use App\Learning\LearningService;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) {
    http_response_code(401);
    exit('Sign in to access this learning resource.');
}
$asset = (new LearningService($pdo))->media((int) $user['id'], (int) ($_GET['id'] ?? 0));
if (!$asset || !in_array($asset['mime_type'], ['video/mp4', 'application/pdf', 'audio/mpeg'], true)) {
    http_response_code(404);
    exit('Resource not found.');
}
$storage = rtrim((string) Config::get('storage.path'), DIRECTORY_SEPARATOR);
$base = realpath($storage);
$file = realpath($storage . DIRECTORY_SEPARATOR . (string) $asset['storage_path']);
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Resource not found.');
}
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
$size = filesize($file);
$start = 0;
$end = $size - 1;
$status = 200;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches)) {
    if ($matches[1] === '' && $matches[2] !== '') {
        $length = min((int) $matches[2], $size);
        if ($length < 1) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        $start = $size - $length;
    } else {
        $start = $matches[1] === '' ? 0 : (int) $matches[1];
        $end = $matches[2] === '' ? $end : min((int) $matches[2], $end);
    }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}
http_response_code($status);
header('Content-Type: ' . $asset['mime_type']);
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($end - $start + 1));
header('Content-Disposition: inline; filename="lesson-resource"');
if ($status === 206) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
$handle = fopen($file, 'rb');
if ($handle === false) {
    http_response_code(404);
    exit;
}
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($handle);
if ($method === 'HEAD') exit;
