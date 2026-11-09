<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Learning\RecognitionService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=notifications.php');
$service = new RecognitionService($pdo);
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        if (($_POST['action'] ?? '') === 'read_all') $service->markAllRead((int) $user['id']);
        if (($_POST['action'] ?? '') === 'read') $service->markRead((int) $user['id'], (int) ($_POST['notification_id'] ?? 0));
        Security::redirect('notifications.php');
    }
}
$items = $service->notifications((int) $user['id']);
$unread = $service->unreadCount((int) $user['id']);
$seoTitle = 'Notifications | SmartStudyPro';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= Security::escape($seoTitle) ?></title>
<link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/portal-theme.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="container py-5 ssp-notifications-page">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1 eyebrow">Your updates</div><h1 class="h2 mb-1">Notifications</h1><p class="muted mb-0"><?= $unread ?> unread notification<?= $unread === 1 ? '' : 's' ?></p></div><?php if ($unread > 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="read_all"><button class="btn-main" type="submit">Mark all as read</button></form><?php endif; ?></div>
<?php if ($error): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?>
<div class="panel"><div class="d-grid gap-3">
<?php foreach ($items as $item): ?><article class="notice <?= $item['read_at'] === null ? 'unread' : '' ?>"><div class="d-flex justify-content-between align-items-start gap-3"><div class="flex-grow-1"><div class="d-flex align-items-center gap-2 mb-1"><strong><?= Security::escape($item['title']) ?></strong><?php if ($item['read_at'] === null): ?><span class="badge text-bg-warning">New</span><?php endif; ?></div><p class="mb-2"><?= Security::escape($item['message']) ?></p><div class="muted"><?= Security::escape(date('d M Y · H:i', strtotime($item['created_at']))) ?></div><?php if ($item['target_url'] !== ''): ?><a class="btn-link d-inline-block mt-2" href="<?= Security::escape($item['target_url']) ?>">View details <i class="bi bi-arrow-right"></i></a><?php endif; ?></div><?php if ($item['read_at'] === null): ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit">Mark read</button></form><?php endif; ?></div></article><?php endforeach; ?>
<?php if (!$items): ?><div class="empty-state text-center py-5" role="status"><i class="bi bi-bell fs-1 ssp-state-icon"></i><h2 class="h5 mt-3">You're all caught up</h2><p class="muted mb-0">Course milestones, achievements and certificates will appear here.</p></div><?php endif; ?>
</div></div>
</main><script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script><script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body></html>
