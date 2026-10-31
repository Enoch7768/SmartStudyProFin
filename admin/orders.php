<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Commerce\DemoPaymentService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Forders.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('You do not have permission to view orders.');
}
$demo = new DemoPaymentService($pdo);
$demoConfigurable = $demo->canConfigure();
$demoEnabled = $demo->enabled();
$orders = $pdo->query('SELECT o.id, o.public_id, o.status, o.total_ugx, o.customer_name, o.customer_email, o.created_at, o.paid_at, u.id AS user_id, p.provider, p.provider_status, p.provider_transaction_id, d.scenario AS demo_scenario, d.reference AS demo_reference FROM orders o INNER JOIN users u ON u.id = o.user_id LEFT JOIN payments p ON p.order_id = o.id LEFT JOIN (SELECT e.order_id, e.scenario, e.reference FROM demo_payment_events e INNER JOIN (SELECT order_id, MAX(id) AS latest_id FROM demo_payment_events GROUP BY order_id) latest ON latest.latest_id = e.id) d ON d.order_id = o.id ORDER BY o.id DESC LIMIT 200')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Orders and payments | SmartStudyPro</title><link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet"></head><body><main class="container-fluid py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Administration</p><h1 class="h2">Orders and payments</h1></div><a class="btn btn-outline-secondary" href="cms.php">Back to CMS</a></div><?php if ($demoConfigurable): ?><section class="border rounded-4 p-3 p-md-4 mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">Demo payment mode</h2><p class="small text-muted mb-0">Only available when the server explicitly allows local, development or staging. Production is always blocked.</p><p class="small mb-0">Current state: <strong><?= $demoEnabled ? 'Enabled' : 'Disabled' ?></strong></p></div><form method="post" action="payment-demo-settings.php"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="enabled" value="<?= $demoEnabled ? '0' : '1' ?>"><button class="btn <?= $demoEnabled ? 'btn-outline-danger' : 'btn-outline-primary' ?>"><?= $demoEnabled ? 'Disable demo payments' : 'Enable demo payments' ?></button></form></div></section><?php endif; ?><?php if (isset($_GET['demo_settings']) && in_array($_GET['demo_settings'], ['enabled', 'disabled'], true)): ?><div class="alert alert-success">Demo payment mode <?= Security::escape($_GET['demo_settings']) ?>.</div><?php endif; ?><div class="table-responsive border rounded-4"><table class="table align-middle mb-0"><thead><tr><th class="p-3">Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Provider</th><th>Provider reference</th><th>Date</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td class="p-3 fw-semibold"><?= Security::escape($order['public_id']) ?></td><td><?= Security::escape($order['customer_name']) ?><br><small class="text-muted"><?= Security::escape($order['customer_email']) ?></small></td><td>UGX <?= number_format((int) $order['total_ugx']) ?></td><td><?= Security::escape(ucfirst($order['status'])) ?></td><td><?= Security::escape($order['demo_reference'] ? 'DEMO' : strtoupper((string) $order['provider'])) ?></td><td><?= Security::escape((string) ($order['demo_reference'] ?? $order['provider_transaction_id'] ?? $order['provider_status'] ?? '—')) ?><?php if (!empty($order['demo_scenario'])): ?><br><small class="text-warning">Simulated: <?= Security::escape($order['demo_scenario']) ?></small><?php endif; ?></td><td><?= Security::escape(date('d M Y H:i', strtotime($order['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="7" class="p-4 text-center text-muted">No orders have been placed.</td></tr><?php endif; ?></tbody></table></div></main></body></html>
