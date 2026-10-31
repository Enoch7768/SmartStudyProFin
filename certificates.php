<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Learning\RecognitionService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=certificates.php');
$service = new RecognitionService($pdo);
$service->sync((int) $user['id']);
$items = $service->certificates((int) $user['id']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Certificates | SmartStudyPro</title>
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);font-family:"Plus Jakarta Sans",sans-serif;color:#1E293B}h1,h2{font-family:Outfit,sans-serif}.certificate{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px}.muted{color:var(--muted);font-size:13px}.code{font:600 13px ui-monospace,monospace;letter-spacing:.08em;color:var(--navy);overflow-wrap:anywhere}@media print{header,.no-print{display:none!important}body{background:#fff}.certificate{border:2px solid #0C086B;box-shadow:none;break-inside:avoid}}</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="container py-5" style="max-width:950px"><div class="mb-4"><div class="small fw-bold text-uppercase mb-1" style="color:var(--orange)">Your milestones</div><h1 class="h2 mb-1">Certificates</h1><p class="muted">Certificates are issued when every published lesson in a course is completed.</p></div>
<div class="row g-3"><?php foreach ($items as $item): ?><div class="col-12"><article class="certificate"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><div class="small fw-bold text-uppercase" style="color:var(--orange)">Certificate of completion</div><h2 class="h4 mt-2 mb-1"><?= Security::escape($item['course_title']) ?></h2><p class="muted mb-2">Awarded to <?= Security::escape($user['name']) ?> for completing all published lessons.</p><div class="code">ID <?= Security::escape($item['verification_code']) ?></div><div class="muted mt-2">Issued <?= Security::escape(date('d F Y', strtotime($item['issued_at']))) ?></div></div><div class="d-flex gap-2 no-print"><button class="btn btn-outline-primary" type="button" onclick="window.print()">Print / Save PDF</button><a class="btn btn-outline-secondary" href="verify-certificate.php?code=<?= rawurlencode($item['verification_code']) ?>">Verify</a></div></div></article></div><?php endforeach; ?><?php if (!$items): ?><div class="col-12"><div class="certificate text-center py-5"><i class="bi bi-award fs-1" style="color:var(--orange)"></i><h2 class="h5 mt-3">Your first certificate is waiting</h2><p class="muted">Complete every published lesson in a course to earn a certificate.</p><a href="learning.php" class="btn btn-primary">Continue learning</a></div></div><?php endif; ?></div></main><script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script><script src="assets/js/main.js"></script>
</body></html>
