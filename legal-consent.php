<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Identity\LegalAcceptanceService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=legal-consent.php');
$service = new LegalAcceptanceService($pdo);
if ($service->hasCurrentAcceptance((int) $user['id'])) Security::redirect('profile.php');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (($_POST['accept_terms'] ?? '') !== '1' || ($_POST['accept_privacy'] ?? '') !== '1') {
        $error = 'You must agree to the Terms of Use and acknowledge the Privacy Policy to continue.';
    } else {
        try {
            $service->acceptCurrent((int) $user['id'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $destination = (string) ($_SESSION['legal_redirect'] ?? 'profile.php');
            unset($_SESSION['legal_redirect']);
            if ($destination === '' || str_starts_with($destination, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $destination) || str_contains($destination, chr(92)) || str_contains($destination, chr(10)) || str_contains($destination, chr(13))) $destination = 'profile.php';
            Security::redirect($destination);
        } catch (Throwable $exception) {
            $error = 'We could not save your acknowledgement. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Review Updated Terms | SmartStudyPro</title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ssp-navy:#0C086B;--ssp-navy-dark:#070443;--ssp-orange:#FF7A00;--ssp-bg-soft:#F8FAFC;--ssp-card-bg:#FFFFFF;--ssp-card-border:#E2E8F0;--ssp-text-main:#1E293B;--ssp-text-muted:#64748B}
[data-theme="dark"]{--ssp-navy:#C7D2FE;--ssp-navy-dark:#0B0F17;--ssp-bg-soft:#111827;--ssp-card-bg:#151C2C;--ssp-card-border:#2E3A52;--ssp-text-main:#F8FAFC;--ssp-text-muted:#CBD5E1}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--ssp-bg-soft);color:var(--ssp-text-main)}
h1,h2{font-family:Outfit,sans-serif}
.consent-wrap{max-width:760px;margin:0 auto;padding:64px 18px}
.consent-card{background:var(--ssp-card-bg);border:1px solid var(--ssp-card-border);border-radius:18px;padding:clamp(24px,4vw,44px);box-shadow:0 12px 34px rgba(7,4,67,.07)}
.consent-card h1{color:var(--ssp-navy);font-weight:800}
.consent-card p,.consent-card li{color:var(--ssp-text-muted);line-height:1.8}
.consent-card a{color:var(--ssp-orange);font-weight:700}
.consent-submit{background:var(--ssp-orange);color:#fff;border:0;border-radius:10px;padding:13px 22px;font-weight:700}
.legal-meta{font-size:.85rem;color:var(--ssp-text-muted)}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="consent-wrap">
    <section class="consent-card">
        <div class="small fw-bold text-uppercase mb-2" style="color:var(--ssp-orange)">Account notice</div>
        <h1 class="h2 mb-3">Please review our current legal documents</h1>
        <p>We have updated the Terms of Use and Privacy Policy. Please review both documents before continuing to your account.</p>
        <p class="legal-meta">Terms version 1.0.0 · Privacy version 1.0.0</p>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= Security::escape($error) ?></div><?php endif; ?>
        <ul>
            <li><a href="terms.php" target="_blank" rel="noopener noreferrer">Read the Terms of Use</a></li>
            <li><a href="privacy.php" target="_blank" rel="noopener noreferrer">Read the Privacy Policy</a></li>
        </ul>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="accept-terms" name="accept_terms" value="1" required>
                <label class="form-check-label" for="accept-terms">I agree to the current Terms of Use.</label>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="accept-privacy" name="accept_privacy" value="1" required>
                <label class="form-check-label" for="accept-privacy">I have read and acknowledge the current Privacy Policy.</label>
            </div>
            <button class="consent-submit" type="submit">Accept and continue</button>
        </form>
    </section>
</main>
<footer class="text-center py-4 small" style="background:var(--ssp-navy-dark);color:#CBD5E1">
    <a href="privacy.php" class="text-white text-decoration-none mx-2">Privacy</a>
    <a href="terms.php" class="text-white text-decoration-none mx-2">Terms</a>
    <a href="cookies.php" class="text-white text-decoration-none mx-2">Cookies</a>
</footer>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
