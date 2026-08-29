<?php


if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}


function auth_db(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $dir = __DIR__ . "/database";
    if (!file_exists($dir)) mkdir($dir, 0777, true);

    $db = new PDO("sqlite:" . $dir . "/bookings.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT,
        google_id TEXT,
        avatar TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    return $db;
}

function ensure_bookings_columns(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT,
        name TEXT,
        email TEXT,
        phone TEXT,
        date TEXT,
        service TEXT,
        message TEXT,
        price TEXT,
        paid INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $existing = array_column($db->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_ASSOC), 'name');

    if (!in_array('file_path', $existing, true)) {
        $db->exec("ALTER TABLE bookings ADD COLUMN file_path TEXT");
    }
    if (!in_array('account_id', $existing, true)) {
        $db->exec("ALTER TABLE bookings ADD COLUMN account_id INTEGER");
    }
    if (!in_array('created_at', $existing, true)) {
        $db->exec("ALTER TABLE bookings ADD COLUMN created_at DATETIME");
    }
}

define('APP_SECRET', 'GRn#jI>dSW(qZk,V/#dN]}/O#H$-8wx6CGUn9JN3g{}p</VpTIGy^?u9I-v>b3ixavA{IEC,?{c8%]xjM.|%63');

function sign_video_token(string $relativePath, int $userId, int $ttlSeconds = 300): array {
    $exp = time() + $ttlSeconds;
    $sig = hash_hmac('sha256', $relativePath . '|' . $userId . '|' . $exp, APP_SECRET);
    return ['exp' => $exp, 'sig' => $sig];
}

function verify_video_token(string $relativePath, int $userId, int $exp, string $sig): bool {
    if ($exp < time()) return false; 
    $expected = hash_hmac('sha256', $relativePath . '|' . $userId . '|' . $exp, APP_SECRET);
    return hash_equals($expected, $sig);
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;

    static $cache = null;
    if ($cache !== null) return $cache ?: null;

    $stmt = auth_db()->prepare("SELECT id, name, email, avatar, google_id FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $cache = $user ?: false;
    return $user ?: null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(): void {
    if (!is_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'profile.php');
        header("Location: login.php?redirect=" . $redirect);
        exit;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

function ensure_user_verification_columns($db) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL");
    } catch (Exception $e) {
    }

    try {
        $db->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {
    }
}
define('GOOGLE_CLIENT_ID', '1027530089710-c3phjdkk43btj44v7gaeaoah6ldi1m43.apps.googleusercontent.com');