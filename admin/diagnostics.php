<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Security;
use App\Release\DiagnosticsService;

$pdo = App\Core\Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Fdiagnostics.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('You do not have permission to view diagnostics.');
}
$report = (new DiagnosticsService(dirname(__DIR__)))->report();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Diagnostics | SmartStudyPro</title>
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);color:#1E293B;font-family:"Plus Jakarta Sans",sans-serif}.panel{background:#fff;border:1px solid var(--line);border-radius:15px;padding:22px}.check{display:flex;justify-content:space-between;gap:16px;padding:13px 0;border-bottom:1px solid var(--line)}.check:last-child{border:0}.muted{color:var(--muted);font-size:13px}</style>
</head>
<body><main class="container py-5" style="max-width:850px"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1" style="color:var(--orange)">Administrator operations</div><h1 class="h2 mb-1">System diagnostics</h1><p class="muted mb-0">Sanitized health checks for the production installation.</p></div><div class="d-flex gap-2"><a href="cms.php" class="btn btn-outline-primary">CMS</a><a href="backups.php" class="btn btn-outline-secondary">Backups</a></div></div>
<div class="panel"><div class="d-flex justify-content-between align-items-center mb-3"><div><strong>Application <?= Security::escape($report['version']) ?></strong><div class="muted"><?= (int)$report['passed'] ?> of <?= (int)$report['total'] ?> checks passed</div></div><span class="badge <?= $report['healthy'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $report['healthy'] ? 'Healthy' : 'Needs attention' ?></span></div><?php foreach ($report['checks'] as $label => $passed): ?><div class="check"><span><?= Security::escape($label) ?></span><strong class="<?= $passed ? 'text-success' : 'text-danger' ?>"><?= $passed ? 'PASS' : 'FAIL' ?></strong></div><?php endforeach; ?><div class="mt-3 muted">Pending database migrations: <?= count($report['pending_migrations']) ?></div><?php if ($report['pending_migrations']): ?><div class="small mt-1"><?= Security::escape(implode(', ', $report['pending_migrations'])) ?></div><?php endif; ?><?php if ($report['free_disk_bytes'] !== null): ?><div class="muted mt-2">Free storage space: <?= number_format($report['free_disk_bytes']/1073741824,2) ?> GB</div><?php endif; ?></div></main></body></html>
