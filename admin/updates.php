<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Security;
use App\Release\BackupService;
use App\Release\UpdateChecker;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Fupdates.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('You do not have permission to manage updates.');
}
$root = dirname(__DIR__);
$currentVersion = trim((string) file_get_contents($root . '/VERSION'));
$backupService = new BackupService($pdo, (string) Config::get('storage.path'), (string) Config::get('database.path'), (string) Config::get('app.key'), $currentVersion);
$message = '';
$error = '';
$applied = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'apply_migrations') {
        try {
            $backup = $backupService->create();
            $verification = $backupService->verify($backup['id']);
            if (!$verification['valid']) throw new RuntimeException('The automatic pre-update backup failed verification. No migrations were applied.');
            $applied = Migrator::run($pdo, $root . '/database/migrations');
            $message = $applied ? 'Database update completed. Applied: ' . implode(', ', $applied) . '. Backup: ' . $backup['id'] : 'The database is already up to date. A verified backup was created: ' . $backup['id'];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
$migrations = Migrator::status($pdo, $root . '/database/migrations');
$release = (new UpdateChecker($currentVersion))->latest();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Updates | SmartStudyPro</title>
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);color:#1E293B;font-family:"Plus Jakarta Sans",sans-serif}.panel{background:#fff;border:1px solid var(--line);border-radius:15px;padding:22px}.muted{color:var(--muted);font-size:13px}.btn-main{background:var(--orange);color:#fff;border:0;border-radius:9px;padding:10px 16px;font-weight:700}</style>
</head>
<body><main class="container py-5" style="max-width:900px"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1" style="color:var(--orange)">Administrator operations</div><h1 class="h2 mb-1">Releases and updates</h1><p class="muted mb-0">Review releases and apply database migrations safely.</p></div><div class="d-flex gap-2"><a href="cms.php" class="btn btn-outline-primary">CMS</a><a href="diagnostics.php" class="btn btn-outline-secondary">Diagnostics</a></div></div>
<?php if ($message !== ''): ?><div class="alert alert-success"><?= Security::escape($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?>
<section class="panel mb-4"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><div class="small text-uppercase fw-bold" style="color:var(--orange)">Installed version</div><h2 class="h3 mt-1"><?= Security::escape($currentVersion) ?></h2></div><?php if ($release['available']): ?><div><div class="small text-uppercase fw-bold" style="color:var(--orange)">Latest release</div><h2 class="h3 mt-1"><?= Security::escape($release['latest_version']) ?></h2><span class="badge <?= $release['update_available'] ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $release['update_available'] ? 'Update available' : 'Up to date' ?></span></div><?php endif; ?></div>
<?php if (!$release['available']): ?><p class="muted mt-3 mb-0"><?= Security::escape($release['error']) ?></p><?php elseif ($release['update_available']): ?><p class="muted mt-3">Download the signed project release package from GitHub, extract it, and upload the application files while preserving your private storage, .env and user uploads.</p><a href="<?= Security::escape($release['release_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Open release notes and package</a><?php else: ?><p class="muted mt-3 mb-0">This installation matches the latest published release.</p><?php endif; ?>
<?php if ($release['available'] && $release['notes'] !== ''): ?><details class="mt-3"><summary class="fw-bold">Release notes</summary><pre class="mt-2 text-wrap small"><?= Security::escape($release['notes']) ?></pre></details><?php endif; ?></section>
<section class="panel"><h2 class="h5">Database migrations</h2><p class="muted">After uploading a release package, apply its pending migrations. This operation first creates and verifies a fresh private backup. Do not run it until the matching application code has been uploaded.</p><div class="d-flex flex-wrap gap-3 mb-3"><span><strong><?= count($migrations['applied']) ?></strong> applied</span><span><strong><?= count($migrations['pending']) ?></strong> pending</span></div><?php if ($migrations['pending']): ?><div class="alert alert-warning"><strong>Pending:</strong> <?= Security::escape(implode(', ', $migrations['pending'])) ?></div><form method="post" onsubmit="return confirm('Create and verify a backup, then apply the pending database migrations?');"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="apply_migrations"><button class="btn-main" type="submit">Back up and apply migrations</button></form><?php else: ?><div class="text-success fw-bold">Database schema is current.</div><?php endif; ?></section>
</main></body></html>
