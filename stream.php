<?php
require_once 'auth.php';
require_login();

$user = current_user();

$relativePath = $_GET['path'] ?? '';
$exp = (int) ($_GET['exp'] ?? 0);
$sig = $_GET['sig'] ?? '';

if ($relativePath === '' || $sig === '' || !verify_video_token($relativePath, $user['id'], $exp, $sig)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Access denied: this link is invalid or has expired. Go back to the study page and try again.";
    exit;
}

$baseDir = realpath(__DIR__ . "/cms/storage/uploads");
$candidate = $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace('\\', '/', $relativePath), '/');
$fullPath = realpath($candidate);

$isSafe = $baseDir !== false
    && $fullPath !== false
    && strncmp($fullPath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) === 0;

if (!$isSafe || !is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Video not found.";
    exit;
}

$size = filesize($fullPath);
$mime = 'video/mp4';

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="lesson.mp4"');

$start = 0;
$end = $size - 1;
$isRangeRequest = false;

if (!empty($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $isRangeRequest = true;
        if ($m[1] !== '') $start = (int) $m[1];
        if ($m[2] !== '') $end = (int) $m[2];
        if ($end > $size - 1) $end = $size - 1;
    }
}

$length = $end - $start + 1;

if ($isRangeRequest) {
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
} else {
    http_response_code(200);
}
header('Content-Length: ' . $length);

$fp = fopen($fullPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}

fseek($fp, $start);
$bufferSize = 8192;
$bytesRemaining = $length;

while ($bytesRemaining > 0 && !feof($fp)) {
    $readSize = min($bufferSize, $bytesRemaining);
    echo fread($fp, $readSize);
    $bytesRemaining -= $readSize;
    flush();
}
fclose($fp);
exit;