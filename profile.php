<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Identity\AccountService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=profile.php');
$service = new AccountService($pdo);
$error = null;
$success = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'profile');
            if ($action === 'profile') {
                $service->updateProfile((int) $user['id'], (string) ($_POST['name'] ?? ''), (string) ($_POST['phone'] ?? ''), (string) ($_POST['location'] ?? ''), (string) ($_POST['bio'] ?? ''), (string) ($_POST['preferred_theme'] ?? 'system'));
                $success = 'Your profile has been updated.';
            } elseif ($action === 'password') {
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $service->changePassword((int) $user['id'], (string) ($_POST['current_password'] ?? ''), $newPassword);
                if (!Auth::attempt($pdo, (string) $user['email'], $newPassword)) throw new RuntimeException('Your password changed. Please sign in again.');
                $success = 'Your password has been changed. Other password reset links have been invalidated.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$profile = $service->profile((int) $user['id']);
$isAdmin = Auth::hasRole($pdo, (int) $user['id'], 'administrator');
$isStudent = Auth::hasRole($pdo, (int) $user['id'], 'student');
$initials = strtoupper(substr((string) ($profile['name'] ?? 'S'), 0, 1));
$phone = '+256 704 416250';
$emailContact = 'smartstudypro36@gmail.com';
$address = 'Kampala, Uganda';
$seoTitle = 'My Account | SmartStudyPro';
$seoDescription = 'Manage your SmartStudyPro account and student profile.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Security::escape($seoTitle) ?></title>
<meta name="description" content="<?= Security::escape($seoDescription) ?>">
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
<style>
:root{--ssp-navy:#0C086B;--ssp-deep:#070443;--ssp-orange:#FF7A00;--ssp-bg:#F6F7FC;--ssp-card:#fff;--ssp-ink:#1E293B;--ssp-muted:#64748B;--ssp-line:#E2E8F0}body{background:var(--ssp-bg);color:var(--ssp-ink);font-family:"Plus Jakarta Sans",sans-serif}h1,h2,h3,h4{font-family:Outfit,sans-serif}.account-hero{background:linear-gradient(125deg,var(--ssp-deep),var(--ssp-navy));color:#fff;border-radius:22px;padding:34px;position:relative;overflow:hidden}.account-hero:after{content:"";position:absolute;width:240px;height:240px;border:1px solid rgba(255,255,255,.15);border-radius:50%;right:-70px;top:-120px;box-shadow:0 0 0 35px rgba(255,255,255,.035),0 0 0 70px rgba(255,255,255,.025)}.account-card{background:var(--ssp-card);border:1px solid var(--ssp-line);border-radius:18px;box-shadow:0 10px 28px rgba(7,4,67,.045)}.account-card .form-control,.account-card .form-select{min-height:46px;border-radius:10px}.account-card textarea.form-control{min-height:110px}.btn-ssp{background:var(--ssp-orange);border:0;color:#fff;font-weight:700;border-radius:10px;padding:12px 20px}.btn-ssp:hover{background:#E06B00;color:#fff}.profile-avatar{width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.15);display:grid;place-items:center;font:700 28px Outfit,sans-serif}.eyebrow{font-size:11px;letter-spacing:1.5px;text-transform:uppercase;font-weight:800;color:var(--ssp-orange)}.meta{color:var(--ssp-muted);font-size:13px}@media(max-width:575px){.account-hero{padding:24px}}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="container py-5">
<section class="account-hero mb-4">
<div class="d-flex flex-wrap align-items-center gap-3 position-relative" style="z-index:1">
<div class="profile-avatar"><?= Security::escape($initials) ?></div>
<div class="flex-grow-1">
<div class="eyebrow mb-1">SmartStudyPro account</div>
<h1 class="h2 mb-1">Welcome, <?= Security::escape($profile['name'] ?? 'Student') ?></h1>
<p class="mb-0 text-white-50"><?= Security::escape($profile['email'] ?? '') ?></p>
</div>
<div class="d-flex flex-wrap gap-2">
<?php if ($isStudent): ?><span class="badge rounded-pill text-bg-light px-3 py-2">Student account</span><?php endif; ?>
<?php if ($isAdmin): ?><a class="btn btn-light btn-sm fw-bold rounded-pill px-3 py-2" href="admin/cms.php">Content manager</a><?php endif; ?>
</div>
</div>
</section>

<?php if ($error !== null): ?><div class="alert alert-danger rounded-3"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if ($success !== null): ?><div class="alert alert-success rounded-3"><?= Security::escape($success) ?></div><?php endif; ?>

<div class="row g-4">
<div class="col-lg-7">
<section class="account-card p-4 p-lg-5 h-100">
<div class="d-flex justify-content-between align-items-start mb-4"><div><div class="eyebrow">Personal details</div><h2 class="h4 mb-1">Your profile</h2><p class="meta mb-0">Keep your contact and account information up to date.</p></div><i class="bi bi-person-badge fs-3" style="color:var(--ssp-navy)"></i></div>
<form method="post">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<input type="hidden" name="action" value="profile">
<div class="mb-3"><label class="form-label fw-semibold" for="name">Full name</label><input class="form-control" id="name" name="name" maxlength="120" value="<?= Security::escape($profile['name'] ?? '') ?>" required></div>
<div class="mb-3"><label class="form-label fw-semibold" for="email">Email address</label><input class="form-control" id="email" value="<?= Security::escape($profile['email'] ?? '') ?>" readonly><div class="form-text">Email address changes and verification will be added with the email verification workflow.</div></div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label fw-semibold" for="phone">Phone number</label><input class="form-control" id="phone" name="phone" maxlength="40" value="<?= Security::escape($profile['phone'] ?? '') ?>" placeholder="+256..."></div>
<div class="col-md-6"><label class="form-label fw-semibold" for="location">Location</label><input class="form-control" id="location" name="location" maxlength="120" value="<?= Security::escape($profile['location'] ?? '') ?>" placeholder="Kampala, Uganda"></div>
</div>
<div class="my-3"><label class="form-label fw-semibold" for="bio">About you</label><textarea class="form-control" id="bio" name="bio" maxlength="500" rows="4" placeholder="A little about your learning goals..."><?= Security::escape($profile['bio'] ?? '') ?></textarea></div>
<div class="mb-4"><label class="form-label fw-semibold" for="preferred_theme">Theme preference</label><select class="form-select" id="preferred_theme" name="preferred_theme"><option value="system"<?= ($profile['preferred_theme'] ?? 'system') === 'system' ? ' selected' : '' ?>>Use device setting</option><option value="light"<?= ($profile['preferred_theme'] ?? '') === 'light' ? ' selected' : '' ?>>Light</option><option value="dark"<?= ($profile['preferred_theme'] ?? '') === 'dark' ? ' selected' : '' ?>>Dark</option></select></div>
<button class="btn btn-ssp" type="submit">Save profile</button>
</form>
</section>
</div>

<div class="col-lg-5">
<section class="account-card p-4 p-lg-5 mb-4">
<div class="eyebrow">Account security</div><h2 class="h4 mb-1">Change password</h2><p class="meta mb-4">Use at least 12 characters. Your password is stored as a secure hash.</p>
<form method="post">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<input type="hidden" name="action" value="password">
<div class="mb-3"><label class="form-label fw-semibold" for="current_password">Current password</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="mb-4"><label class="form-label fw-semibold" for="new_password">New password</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required></div>
<button class="btn btn-ssp w-100" type="submit">Update password</button>
</form>
</section>

<section class="account-card p-4">
<div class="eyebrow">Your learning</div><h2 class="h4 mb-2">Student portal</h2>
<p class="meta mb-3">Review your purchases, track course access and continue learning from your account.</p>
<div class="d-flex flex-wrap gap-2"><a href="orders.php" class="btn btn-outline-primary rounded-pill fw-semibold px-3">Order history</a><a href="learning.php" class="btn btn-outline-primary rounded-pill fw-semibold px-3">Open my learning <i class="bi bi-arrow-right ms-1"></i></a><a href="courses.php" class="btn btn-outline-secondary rounded-pill fw-semibold px-3">Explore courses</a></div>
</section>

<section class="d-flex justify-content-between align-items-center mt-4 px-2">
<span class="meta">Member since <?= Security::escape(date('M Y', strtotime((string) ($profile['created_at'] ?? 'now')))) ?></span>
<form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><button type="submit" class="btn btn-link text-danger text-decoration-none fw-semibold">Sign out</button></form>
</section>
</div>
</div>
</main>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
