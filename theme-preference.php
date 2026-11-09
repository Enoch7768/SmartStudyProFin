<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $pdo = Database::connect();
    $user = Auth::user($pdo);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Sign in to load your saved theme preference.']);
        exit;
    }

    $statement = $pdo->prepare('SELECT preferred_theme FROM student_profiles WHERE user_id = :user_id LIMIT 1');
    $statement->execute(['user_id' => (int) $user['id']]);
    $theme = $statement->fetchColumn();
    if (!in_array($theme, ['system', 'light', 'dark'], true)) $theme = 'system';

    echo json_encode(['theme' => $theme], JSON_THROW_ON_ERROR);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!Security::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(419);
    echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$theme = is_array($payload) ? (string) ($payload['theme'] ?? '') : '';
if (!in_array($theme, ['light', 'dark', 'system'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Choose a valid theme preference.']);
    exit;
}

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in to sync your theme preference.']);
    exit;
}

$now = gmdate('Y-m-d H:i:s');
$statement = $pdo->prepare('INSERT INTO student_profiles (user_id, preferred_theme, created_at, updated_at) VALUES (:user_id, :theme, :created_at, :updated_at) ON CONFLICT(user_id) DO UPDATE SET preferred_theme = excluded.preferred_theme, updated_at = excluded.updated_at');
$statement->execute(['user_id' => (int) $user['id'], 'theme' => $theme, 'created_at' => $now, 'updated_at' => $now]);

echo json_encode(['success' => true, 'theme' => $theme], JSON_THROW_ON_ERROR);
