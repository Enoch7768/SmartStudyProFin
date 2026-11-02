<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Session;
use App\Identity\GoogleIdentityService;
use App\Identity\LegalAcceptanceService;

$pdo = Database::connect();
$service = new GoogleIdentityService($pdo);
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$failureMode = '';

try {
    if ($action === 'login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') $service->start('login');
    if ($action === 'register' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) throw new RuntimeException('Your session expired. Refresh the page and try again.');
        if (($_POST['accept_terms'] ?? '') !== '1' || ($_POST['accept_privacy'] ?? '') !== '1') throw new RuntimeException('Accept the Terms of Use and acknowledge the Privacy Policy before creating an account.');
        $service->start('register');
    }
    if ($action === 'link' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) throw new RuntimeException('Your session expired. Refresh the page and try again.');
        $user = Auth::user($pdo);
        if (!$user) Security::redirect('login.php?redirect=profile.php');
        $service->start('link', (int) $user['id']);
    }
    if ($action === 'callback' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $failureMode = (string) ($_SESSION['google_oauth']['mode'] ?? '');
        if (isset($_GET['error'])) throw new RuntimeException('Google sign-in was cancelled or could not be completed.');
        $result = $service->complete((string) ($_GET['code'] ?? ''), (string) ($_GET['state'] ?? ''));
        if ($result['mode'] === 'link') Security::redirect('profile.php?google_linked=1');
        $statement = $pdo->prepare("SELECT auth_version FROM users WHERE id = :id AND status = 'active' LIMIT 1");
        $statement->execute(['id' => $result['user_id']]);
        $authVersion = $statement->fetchColumn();
        if ($authVersion === false) throw new RuntimeException('This SmartStudyPro account is not currently active.');
        Session::regenerate();
        unset($_SESSION['_csrf']);
        $_SESSION['user_id'] = (int) $result['user_id'];
        $_SESSION['auth_version'] = (int) $authVersion;
        $legal = new LegalAcceptanceService($pdo);
        if (!$legal->hasCurrentAcceptance((int) $result['user_id'])) {
            $_SESSION['legal_redirect'] = Auth::hasRole($pdo, (int) $result['user_id'], 'administrator') ? 'admin/cms.php' : 'profile.php';
            Security::redirect('legal-consent.php');
        }
        Security::redirect(Auth::hasRole($pdo, (int) $result['user_id'], 'administrator') ? 'admin/cms.php' : 'profile.php');
    }
    http_response_code(405);
    header('Allow: GET, POST');
    exit('This Google sign-in request is not supported.');
} catch (Throwable $exception) {
    $pending = $_SESSION['google_oauth'] ?? null;
    unset($_SESSION['google_oauth']);
    $mode = $failureMode !== '' ? $failureMode : (is_array($pending) ? (string) ($pending['mode'] ?? '') : '');
    if ($mode === 'link' || $action === 'link') Security::redirect('profile.php?google_error=1');
    Security::redirect(($mode === 'register' || $action === 'register') ? 'register.php?google_error=1' : 'login.php?google_error=1');
}
