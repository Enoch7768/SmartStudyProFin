<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

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
$orders = $pdo->query('SELECT o.id, o.public_id, o.status, o.total_ugx, o.customer_name, o.customer_email, o.created_at, o.paid_at, u.id AS user_id, p.provider, p.provider_status, p.provider_transaction_id FROM orders o INNER JOIN users u ON u.id = o.user_id LEFT JOIN payments p ON p.order_id = o.id ORDER BY o.id DESC LIMIT 200')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Orders and payments | SmartStudyPro</title><link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet"></head><body><main class="container-fluid py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Administration</p><h1 class="h2">Orders and payments</h1></div><a class="btn btn-outline-secondary" href="cms.php">Back to CMS</a></div><div class="table-responsive border rounded-4"><table class="table align-middle mb-0"><thead><tr><th class="p-3">Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Provider</th><th>Provider reference</th><th>Date</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td class="p-3 fw-semibold"><?= Security::escape($order['public_id']) ?></td><td><?= Security::escape($order['customer_name']) ?><br><small class="text-muted"><?= Security::escape($order['customer_email']) ?></small></td><td>UGX <?= number_format((int) $order['total_ugx']) ?></td><td><?= Security::escape(ucfirst($order['status'])) ?></td><td><?= Security::escape(strtoupper((string) $order['provider'])) ?></td><td><?= Security::escape((string) ($order['provider_transaction_id'] ?? $order['provider_status'] ?? '—')) ?></td><td><?= Security::escape(date('d M Y H:i', strtotime($order['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="7" class="p-4 text-center text-muted">No orders have been placed.</td></tr><?php endif; ?></tbody></table></div></main></body></html>
