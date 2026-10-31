<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;
use App\Release\BackupService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Fbackups.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('Administrator access required.');
}
$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));
$service = new BackupService($pdo, (string) Config::get('storage.path'), (string) Config::get('database.path'), (string) Config::get('app.key'), $version);
$message = '';
$error = '';
$result = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                $created = $service->create();
                $result = $service->verify($created['id']);
                if (!$result['valid']) throw new RuntimeException('Backup verification failed: ' . $result['message']);
                $message = 'Backup created and verified: ' . $created['id'];
            } elseif ($action === 'verify') {
                $result = $service->verify((string) ($_POST['backup_id'] ?? ''));
                if ($result['valid']) $message = 'Backup verification passed.';
                else $error = $result['message'];
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
$items = $service->all();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Backups | SmartStudyPro</title>
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet">
<style>:root{--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);color:#1E293B;font-family:"Plus Jakarta Sans",sans-serif}.panel{background:#fff;border:1px solid var(--line);border-radius:15px;padding:22px}.muted{color:var(--muted);font-size:13px}.btn-main{background:var(--orange);color:#fff;border:0;border-radius:9px;padding:10px 16px;font-weight:700}</style>
</head>
<body><main class="container py-5"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2">Backups</h1><p class="muted">Private database and media recovery points.</p></div><a href="cms.php" class="btn btn-outline-primary">CMS</a></div>
<?php if ($message !== ''): ?><div class="alert alert-success"><?= Security::escape($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?>
<section class="panel mb-4"><p class="muted">Creates a consistent SQLite snapshot, copies private media and the application key, and signs a checksum manifest. Backup files remain outside the public web root.</p><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="create"><button class="btn-main" type="submit">Create and verify backup</button></form></section>
<section class="panel"><h2 class="h5">Available backups</h2><div class="table-responsive"><table class="table"><thead><tr><th>Backup ID</th><th>Created (UTC)</th><th>Version</th><th>Files</th><th>Size</th><th></th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><code><?= Security::escape($item['id']) ?></code></td><td><?= Security::escape($item['created_at']) ?></td><td><?= Security::escape($item['version']) ?></td><td><?= (int)$item['file_count'] ?></td><td><?= number_format((int)$item['total_bytes']/1048576,2) ?> MB</td><td><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="verify"><input type="hidden" name="backup_id" value="<?= Security::escape($item['id']) ?>"><button class="btn btn-sm btn-outline-primary">Verify</button></form></td></tr><?php endforeach; ?><?php if (!$items): ?><tr><td colspan="6" class="text-center text-secondary">No backups created yet.</td></tr><?php endif; ?></tbody></table></div><p class="muted mb-0">Backups cannot be downloaded through the public application. Copy them through your hosting control panel or secure server access.</p></section>
</main></body></html>
