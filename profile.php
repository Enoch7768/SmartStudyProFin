<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Url;
use App\Identity\AccountService;
use App\Identity\GoogleIdentityService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect(Url::to('/login?redirect=profile'));
$service = new AccountService($pdo);
$googleIssues = GoogleIdentityService::configurationIssues();
$googleConfigured = $googleIssues === [];
$error = isset($_GET['google_error']) ? 'Google could not be connected. The account may already be linked elsewhere.' : null;
$success = isset($_GET['google_linked']) ? 'Your Google account is now connected.' : null;

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
<script>
(function () {
    try {
        const preferredTheme = <?= json_encode((string) ($profile['preferred_theme'] ?? 'system'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        if (preferredTheme === 'dark' || preferredTheme === 'light') localStorage.setItem('ssp-theme', preferredTheme);
        else localStorage.removeItem('ssp-theme');
        const useDarkTheme = preferredTheme === 'dark' || (preferredTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (useDarkTheme) document.documentElement.setAttribute('data-theme', 'dark');
        else document.documentElement.removeAttribute('data-theme');
    } catch (error) {}
})();
</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Security::escape($seoTitle) ?></title>
<meta name="description" content="<?= Security::escape($seoDescription) ?>">
<link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body class="ssp-account-page">
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="container py-5">
<section class="account-hero mb-4">
<div class="d-flex flex-wrap align-items-center gap-3 position-relative" class="is-layered">
<div class="profile-avatar"><?= Security::escape($initials) ?></div>
<div class="flex-grow-1">
<div class="eyebrow mb-1">SmartStudyPro account</div>
<h1 class="h2 mb-1">Welcome, <?= Security::escape($profile['name'] ?? 'Student') ?></h1>
<p class="mb-0 text-white-50"><?= Security::escape($profile['email'] ?? '') ?></p>
</div>
<div class="d-flex flex-wrap gap-2">
<?php if ($isStudent): ?><span class="badge rounded-pill text-bg-light px-3 py-2">Student account</span><?php endif; ?>
<?php if ($isAdmin): ?><a class="btn btn-light btn-sm fw-bold rounded-pill px-3 py-2" href="<?= htmlspecialchars(\App\Core\Url::to('/admin/cms.php'), ENT_QUOTES, 'UTF-8') ?>">Content manager</a><?php endif; ?>
</div>
</div>
</section>

<?php if ($error !== null): ?><div class="alert alert-danger rounded-3"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if ($success !== null): ?><div class="alert alert-success rounded-3"><?= Security::escape($success) ?></div><?php endif; ?>

<div class="row g-4">
<div class="col-lg-7">
<section class="account-card p-4 p-lg-5 h-100">
<div class="d-flex justify-content-between align-items-start mb-4"><div><div class="eyebrow">Personal details</div><h2 class="h4 mb-1">Your profile</h2><p class="meta mb-0">Keep your contact and account information up to date.</p></div><i class="bi bi-person-badge fs-3" class="ssp-account-heading"></i></div>
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
<div class="eyebrow">Connected accounts</div><h2 class="h4 mb-1">Google sign-in</h2>
<p class="meta mb-3"><?= !empty($profile['google_subject']) ? 'Your Google account is connected. You can use it to sign in securely.' : 'Connect Google to sign in without entering your password.' ?></p>
<?php if (!empty($profile['google_subject'])): ?><span class="badge text-bg-success">Google connected</span>
<?php elseif ($googleConfigured): ?>
<form method="post" action="<?= htmlspecialchars(\App\Core\Url::to('/google-auth.php'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="link"><button class="btn btn-outline-secondary w-100 rounded-3 fw-semibold" type="submit">Connect Google account</button></form>
<?php else: ?>
<div class="meta" role="status"><p class="mb-2">Google account linking is not ready. Check the server configuration:</p><ul class="mb-0 ps-3"><?php foreach ($googleIssues as $issue): ?><li class="mb-1"><?= Security::escape($issue) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
</section>

<section class="account-card p-4 p-lg-5 mb-4">
<div class="eyebrow">Account security</div><h2 class="h4 mb-1">Change password</h2><p class="meta mb-4">Use at least 12 characters. Your password is stored as a secure hash.</p>
<form method="post">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<input type="hidden" name="action" value="password">
<?php if (!empty($profile['has_password'])): ?>
<div class="mb-3"><label class="form-label fw-semibold" for="current_password">Current password</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
<?php else: ?><p class="meta mb-3">This account currently uses Google sign-in. You can set a password here to enable email-and-password sign-in too.</p><input type="hidden" name="current_password" value=""><?php endif; ?>
<div class="mb-4"><label class="form-label fw-semibold" for="new_password">New password</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required></div>
<button class="btn btn-ssp w-100" type="submit">Update password</button>
</form>
</section>

<section class="account-card p-4">
<div class="eyebrow">Your learning</div><h2 class="h4 mb-2">Student portal</h2>
<p class="meta mb-3">Review your purchases, track course access and continue learning from your account.</p>
<div class="d-flex flex-wrap gap-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/orders'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary rounded-pill fw-semibold px-3">Order history</a><a href="<?= htmlspecialchars(\App\Core\Url::to('/learn'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary rounded-pill fw-semibold px-3">Open my learning <i class="bi bi-arrow-right ms-1"></i></a><a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary rounded-pill fw-semibold px-3">Explore courses</a></div>
</section>

<section class="d-flex justify-content-between align-items-center mt-4 px-2">
<span class="meta">Member since <?= Security::escape(date('M Y', strtotime((string) ($profile['created_at'] ?? 'now')))) ?></span>
<form method="post" action="<?= htmlspecialchars(\App\Core\Url::to('/logout'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><button type="submit" class="btn btn-link text-danger text-decoration-none fw-semibold">Sign out</button></form>
</section>
</div>
</div>
</main>
<script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
