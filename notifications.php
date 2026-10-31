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
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);font-family:"Plus Jakarta Sans",sans-serif;color:#1E293B}h1,h2{font-family:Outfit,sans-serif}.panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px}.notice{border:1px solid var(--line);border-radius:13px;padding:17px;background:#fff}.notice.unread{border-left:4px solid var(--orange);background:#fffaf5}.muted{color:var(--muted);font-size:13px}.btn-main{background:var(--orange);color:#fff;border:0;border-radius:9px;padding:9px 14px;font-weight:700}.btn-link{color:var(--navy);font-weight:700;text-decoration:none}</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main class="container py-5" style="max-width:900px">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1" style="color:var(--orange)">Your updates</div><h1 class="h2 mb-1">Notifications</h1><p class="muted mb-0"><?= $unread ?> unread notification<?= $unread === 1 ? '' : 's' ?></p></div><?php if ($unread > 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="read_all"><button class="btn-main" type="submit">Mark all as read</button></form><?php endif; ?></div>
<?php if ($error): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?>
<div class="panel"><div class="d-grid gap-3">
<?php foreach ($items as $item): ?><article class="notice <?= $item['read_at'] === null ? 'unread' : '' ?>"><div class="d-flex justify-content-between align-items-start gap-3"><div class="flex-grow-1"><div class="d-flex align-items-center gap-2 mb-1"><strong><?= Security::escape($item['title']) ?></strong><?php if ($item['read_at'] === null): ?><span class="badge text-bg-warning">New</span><?php endif; ?></div><p class="mb-2"><?= Security::escape($item['message']) ?></p><div class="muted"><?= Security::escape(date('d M Y · H:i', strtotime($item['created_at']))) ?></div><?php if ($item['target_url'] !== ''): ?><a class="btn-link d-inline-block mt-2" href="<?= Security::escape($item['target_url']) ?>">View details <i class="bi bi-arrow-right"></i></a><?php endif; ?></div><?php if ($item['read_at'] === null): ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit">Mark read</button></form><?php endif; ?></div></article><?php endforeach; ?>
<?php if (!$items): ?><div class="text-center py-5"><i class="bi bi-bell fs-1" style="color:var(--orange)"></i><h2 class="h5 mt-3">You're all caught up</h2><p class="muted mb-0">Course milestones, achievements and certificates will appear here.</p></div><?php endif; ?>
</div></div>
</main><script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script><script src="assets/js/main.js"></script>
</body></html>
