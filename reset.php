<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Identity\AccountService;
use App\Identity\PasswordResetMailer;

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$pdo = Database::connect();
$service = new AccountService($pdo);
$error = null;
$success = null;
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$mode = $token !== '' ? 'reset' : 'request';
if ($mode === 'request' && Auth::user($pdo)) Security::redirect('profile.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($mode === 'request') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $service->allowResetRequest($email, (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
            $resetToken = $service->createResetToken($email);
            if ($resetToken !== null) {
                $statement = $pdo->prepare("SELECT name FROM users WHERE email = :email COLLATE NOCASE LIMIT 1");
                $statement->execute(['email' => $email]);
                $name = (string) ($statement->fetchColumn() ?: 'Student');
                PasswordResetMailer::send($email, $name, $resetToken);
            }
        }
        $success = 'If an active account matches that email address, password reset instructions will be sent shortly.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (!hash_equals($password, $confirmation)) {
            $error = 'The passwords do not match.';
        } elseif (!$service->resetPassword($token, $password)) {
            $error = 'This reset link is invalid, expired, or has already been used. Request a new one.';
        } else {
            $success = 'Your password has been updated. You can now sign in.';
            $mode = 'complete';
        }
    }
}

$seoTitle = 'Password Recovery | SmartStudyPro';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title><?= Security::escape($seoTitle) ?></title>
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--navy:#0C086B;--deep:#070443;--orange:#FF7A00;--ink:#1E293B;--muted:#64748B;--line:#E2E8F0;--surface:#fff;--bg:#F5F6FB}*{box-sizing:border-box}body{min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg);color:var(--ink);font-family:"Plus Jakarta Sans",sans-serif}.card-shell{width:min(100%,460px);padding:36px;background:var(--surface);border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 55px rgba(7,4,67,.1)}h1{font:700 29px Outfit,sans-serif;color:var(--navy)}.brand{font:800 24px Outfit,sans-serif;color:var(--deep)}.brand span{color:var(--orange)}.form-control{min-height:48px;border-radius:10px}.btn-primary{background:var(--orange);border:0;font-weight:700;min-height:48px}.btn-primary:hover{background:#E06B00}.muted{color:var(--muted)}@media(max-width:480px){.card-shell{padding:26px 20px}}
</style>
</head>
<body>
<main class="card-shell">
<div class="text-center mb-4"><a href="index.php" class="brand text-decoration-none">SmartStudy<span>Pro</span></a></div>
<h1 class="text-center mb-2"><?= $mode === 'reset' ? 'Choose a new password' : ($mode === 'complete' ? 'Password updated' : 'Reset your password') ?></h1>
<p class="muted text-center mb-4"><?= $mode === 'reset' ? 'Choose a strong password you have not used before.' : 'We will send a secure reset link if an active account matches your email.' ?></p>
<?php if ($error !== null): ?><div class="alert alert-danger small"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if ($success !== null): ?><div class="alert alert-success small"><?= Security::escape($success) ?></div><?php endif; ?>
<?php if ($mode === 'request'): ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<div class="mb-3"><label class="form-label fw-semibold" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required></div>
<button class="btn btn-primary w-100" type="submit">Send reset instructions</button>
</form>
<?php elseif ($mode === 'reset'): ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<input type="hidden" name="token" value="<?= Security::escape($token) ?>">
<div class="mb-3"><label class="form-label fw-semibold" for="password">New password</label><input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
<div class="mb-3"><label class="form-label fw-semibold" for="password_confirmation">Confirm new password</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
<button class="btn btn-primary w-100" type="submit">Update password</button>
</form>
<?php endif; ?>
<div class="text-center mt-4 small"><a href="login.php" class="text-decoration-none fw-semibold" style="color:var(--orange)">Back to sign in</a></div>
</main>
</body>
</html>
