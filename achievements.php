<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Learning\RecognitionService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=achievements.php');
$service = new RecognitionService($pdo);
$service->sync((int) $user['id']);
$items = $service->achievements((int) $user['id']);
$earned = count(array_filter($items, static fn (array $item): bool => $item['earned_at'] !== null));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Achievements | SmartStudyPro</title>
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);font-family:"Plus Jakarta Sans",sans-serif;color:#1E293B}h1,h2,h3{font-family:Outfit,sans-serif}.achievement{height:100%;background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px}.achievement.locked{opacity:.58}.achievement-icon{width:52px;height:52px;border-radius:14px;background:#fff2e6;color:var(--orange);display:grid;place-items:center;font-size:24px}.muted{color:var(--muted);font-size:13px}</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="container py-5"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1" style="color:var(--orange)">Your progress</div><h1 class="h2 mb-1">Achievements</h1><p class="muted mb-0">Milestones earned through your actual course activity.</p></div><div class="text-end"><strong class="fs-3" style="color:var(--navy)"><?= $earned ?>/<?= count($items) ?></strong><div class="muted">unlocked</div></div></div>
<div class="row g-3"><?php foreach ($items as $item): ?><div class="col-md-6 col-lg-4"><article class="achievement <?= $item['earned_at'] === null ? 'locked' : '' ?>"><div class="d-flex justify-content-between align-items-start"><div class="achievement-icon"><i class="<?= Security::escape($item['icon']) ?>"></i></div><span class="badge <?= $item['earned_at'] === null ? 'text-bg-light' : 'text-bg-warning' ?>"><?= $item['earned_at'] === null ? 'Locked' : 'Unlocked' ?></span></div><h2 class="h5 mt-3 mb-1"><?= Security::escape($item['name']) ?></h2><p class="muted mb-2"><?= Security::escape($item['description']) ?></p><?php if ($item['earned_at'] !== null): ?><small class="muted">Earned <?= Security::escape(date('d M Y', strtotime($item['earned_at']))) ?></small><?php endif; ?></article></div><?php endforeach; ?></div>
<div class="mt-4"><a class="btn btn-outline-primary" href="certificates.php">View certificates</a></div></main><script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script><script src="assets/js/main.js"></script>
</body></html>
