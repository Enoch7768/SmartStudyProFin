<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Commerce\RefundService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Frefunds.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('Administrator access is required.');
}
$service = new RefundService($pdo);
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            if (($_POST['action'] ?? '') === 'request') {
                $service->request((int) ($_POST['order_item_id'] ?? 0), (int) $user['id'], (int) ($_POST['amount_ugx'] ?? 0), (string) ($_POST['reason'] ?? ''));
            } elseif (($_POST['action'] ?? '') === 'resolve') {
                $service->resolve((int) ($_POST['refund_id'] ?? 0), (int) $user['id'], (string) ($_POST['status'] ?? ''), (string) ($_POST['reference'] ?? ''), (string) ($_POST['note'] ?? ''));
            } else {
                throw new RuntimeException('Choose a valid refund action.');
            }
            Security::redirect('refunds.php?saved=1');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
$refundItems = $pdo->query("SELECT oi.id, oi.item_title, oi.item_type, oi.product_type, oi.line_total_ugx, o.public_id, o.customer_name, COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = oi.id AND r.status IN ('pending', 'succeeded')), 0) AS reserved_ugx FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.status IN ('paid', 'partially_refunded') AND (oi.item_type = 'course' OR (oi.item_type = 'product' AND oi.product_type = 'online')) AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status = 'paid') ORDER BY o.id DESC, oi.id LIMIT 300")->fetchAll();
$refundItems = array_values(array_filter($refundItems, static fn (array $item): bool => (int) $item['line_total_ugx'] > (int) $item['reserved_ugx']));
$refunds = $service->all();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Refund requests | SmartStudyPro</title>
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<link href="../assets/css/device-responsive.css" rel="stylesheet">
<link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
<main class="container-fluid py-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><p class="small text-uppercase fw-bold mb-1" style="color:#FF7A00">Administration</p><h1 class="h2 mb-0">Refund requests</h1></div><div class="d-flex gap-2"><a href="orders.php" class="btn btn-outline-secondary">Back to orders</a><button type="button" class="btn btn-outline-secondary" data-admin-theme-toggle aria-label="Toggle dark mode" data-theme-endpoint="../theme-preference.php" data-csrf="<?= Security::escape(Security::csrfToken()) ?>">Dark mode</button></div></div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Refund record updated.</div><?php endif; ?>
<section class="border rounded-4 p-3 p-md-4 mb-4">
<h2 class="h5">Record a refund request</h2>
<p class="text-muted small">Select one eligible course or digital product. Physical products and delivery are excluded. This creates an auditable request only; process the refund in the payment provider first, then record its reference.</p>
<form method="post" class="row g-3">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="request">
<div class="col-lg-5"><label class="form-label" for="order_item_id">Eligible course or digital item</label><select class="form-select" id="order_item_id" name="order_item_id" required><option value="">Select an order item</option><?php foreach ($refundItems as $item): $remaining = (int) $item['line_total_ugx'] - (int) $item['reserved_ugx']; ?><option value="<?= (int) $item['id'] ?>"><?= Security::escape($item['public_id'] . ' · ' . $item['item_title'] . ' · remaining UGX ' . number_format($remaining)) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-3"><label class="form-label" for="amount_ugx">Refund amount (UGX)</label><input class="form-control" id="amount_ugx" name="amount_ugx" type="number" min="1" max="1000000000" required></div>
<div class="col-lg-4"><label class="form-label" for="reason">Reason</label><input class="form-control" id="reason" name="reason" maxlength="500" required></div>
<div class="col-12"><button class="btn btn-primary">Create refund request</button></div>
</form>
</section>
<section class="border rounded-4 p-3 p-md-4">
<h2 class="h5 mb-3">Refund ledger and audit trail</h2>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Item</th><th>Customer</th><th>Amount</th><th>Status</th><th>Reason</th><th>Requested by</th><th>Resolution</th><th>Action</th></tr></thead><tbody>
<?php foreach ($refunds as $refund): ?>
<tr><td><?= Security::escape($refund['public_id']) ?><br><small class="text-muted">#<?= (int) $refund['id'] ?></small></td><td><?= Security::escape($refund['refunded_item_title'] ?? '—') ?></td><td><?= Security::escape($refund['customer_name']) ?></td><td>UGX <?= number_format((int) $refund['amount_ugx']) ?></td><td><?= Security::escape(ucfirst($refund['status'])) ?></td><td><?= Security::escape($refund['reason']) ?></td><td><?= Security::escape($refund['requested_by_name'] ?? '—') ?></td><td><?= Security::escape($refund['provider_refund_id'] ?? '—') ?><br><small class="text-muted"><?= Security::escape($refund['resolution_reference'] ?? ($refund['resolved_by_name'] ?? '')) ?></small></td><td><?php if ($refund['status'] === 'pending'): ?><form method="post" class="d-grid gap-2"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="refund_id" value="<?= (int) $refund['id'] ?>"><select class="form-select form-select-sm" name="status"><option value="succeeded">Provider confirmed refund</option><option value="failed">Provider rejected refund</option></select><input class="form-control form-control-sm" name="reference" maxlength="190" placeholder="Provider refund reference"><input class="form-control form-control-sm" name="note" maxlength="500" placeholder="Resolution note" required><button class="btn btn-sm btn-outline-primary">Record resolution</button></form><?php else: ?><span class="text-muted small"><?= Security::escape($refund['resolved_at'] ?? '') ?></span><?php endif; ?></td></tr>
<?php $events = $service->auditTrail((int) $refund['id']); ?>
<tr><td colspan="9"><details><summary class="small fw-semibold">View <?= count($events) ?> audit events</summary><ul class="small mb-0 mt-2"><?php foreach ($events as $event): ?><li><?= Security::escape($event['created_at'] . ' · ' . $event['actor_name'] . ' · ' . $event['action'] . ' · UGX ' . number_format((int) $event['amount_ugx']) . ' · ' . $event['reason'] . ($event['reference'] !== '' ? ' · ' . $event['reference'] : '')) ?></li><?php endforeach; ?></ul></details></td></tr>
<?php endforeach; ?>
<?php if (!$refunds): ?><tr><td colspan="8" class="text-center text-muted p-4">No refund requests recorded.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</main>
<script src="../assets/js/theme.js" defer></script>
</body>
</html>
