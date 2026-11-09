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
<link href="../assets/css/device-responsive.css" rel="stylesheet">
<script src="../assets/js/theme.js" defer></script><link href="../assets/css/admin-operations.css" rel="stylesheet">
</head>
<body class="ssp-admin-operations"><button type="button" class="ssp-theme-switch" data-theme-toggle data-theme-endpoint="../theme-preference.php" data-csrf="<?= Security::escape(Security::csrfToken()) ?>">Dark mode</button><main class="container py-5 admin-operations-container admin-operations-container--narrow"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1" class="admin-kicker">Administrator operations</div><h1 class="h2 mb-1">System diagnostics</h1><p class="muted mb-0">Sanitized health checks for the production installation.</p></div><div class="d-flex gap-2"><a href="cms.php" class="btn btn-outline-primary">CMS</a><a href="backups.php" class="btn btn-outline-secondary">Backups</a></div></div>
<div class="panel"><div class="d-flex justify-content-between align-items-center mb-3"><div><strong>Application <?= Security::escape($report['version']) ?></strong><div class="muted"><?= (int)$report['passed'] ?> of <?= (int)$report['total'] ?> checks passed</div></div><span class="badge <?= $report['healthy'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $report['healthy'] ? 'Healthy' : 'Needs attention' ?></span></div><?php foreach ($report['checks'] as $label => $passed): ?><div class="check"><span><?= Security::escape($label) ?></span><strong class="<?= $passed ? 'text-success' : 'text-danger' ?>"><?= $passed ? 'PASS' : 'FAIL' ?></strong></div><?php endforeach; ?><div class="mt-3 muted">Pending database migrations: <?= count($report['pending_migrations']) ?></div><?php if ($report['pending_migrations']): ?><div class="small mt-1"><?= Security::escape(implode(', ', $report['pending_migrations'])) ?></div><?php endif; ?><?php if ($report['free_disk_bytes'] !== null): ?><div class="muted mt-2">Free storage space: <?= number_format($report['free_disk_bytes']/1073741824,2) ?> GB</div><?php endif; ?></div></main></body></html>
