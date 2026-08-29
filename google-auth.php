<?php
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['credential'])) {
    echo json_encode(['success' => false, 'message' => 'Missing credential.']);
    exit;
}

$credential = $_POST['credential'];
$redirect = $_POST['redirect'] ?? 'profile.php';

$verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential);
$context = stream_context_create(['http' => ['timeout' => 5]]);
$response = @file_get_contents($verifyUrl, false, $context);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'Could not verify Google sign-in. Please try again.']);
    exit;
}

$payload = json_decode($response, true);

if (!$payload || empty($payload['sub']) || empty($payload['email'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Google token.']);
    exit;
}

if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    echo json_encode(['success' => false, 'message' => 'Token was not issued for this application.']);
    exit;
}

if (empty($payload['email_verified']) || $payload['email_verified'] === 'false') {
    echo json_encode(['success' => false, 'message' => 'Please use a verified Google email address.']);
    exit;
}

$googleId = $payload['sub'];
$email    = $payload['email'];
$name     = $payload['name'] ?? explode('@', $email)[0];
$avatar   = $payload['picture'] ?? null;

$db = auth_db();

$stmt = $db->prepare("SELECT * FROM users WHERE google_id = :gid OR email = :email LIMIT 1");
$stmt->execute([':gid' => $googleId, ':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    if (empty($user['google_id'])) {
        $upd = $db->prepare("UPDATE users SET google_id = :gid, avatar = :avatar WHERE id = :id");
        $upd->execute([':gid' => $googleId, ':avatar' => $avatar, ':id' => $user['id']]);
    }
} else {
    $ins = $db->prepare("INSERT INTO users (name, email, google_id, avatar) VALUES (:name, :email, :gid, :avatar)");
    $ins->execute([':name' => $name, ':email' => $email, ':gid' => $googleId, ':avatar' => $avatar]);
    $user = ['id' => $db->lastInsertId()];
}

login_user($user);

echo json_encode(['success' => true, 'redirect' => $redirect]);