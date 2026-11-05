<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=orders.php');
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$query = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1');
$query->execute(['id' => $orderId, 'user_id' => (int) $user['id']]);
$order = $query->fetch();
if (!$order) {
    http_response_code(404);
    exit('Receipt not found.');
}
$items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id');
$items->execute(['order_id' => $orderId]);
$payments = $pdo->prepare('SELECT provider, provider_order_id, provider_transaction_id, amount_ugx, currency, status, provider_status, paid_at FROM payments WHERE order_id = :order_id ORDER BY id DESC');
$payments->execute(['order_id' => $orderId]);
$paymentRows = $payments->fetchAll();
$refunds = $pdo->prepare("SELECT SUM(amount_ugx) FROM refunds r INNER JOIN payments p ON p.id = r.payment_id WHERE p.order_id = :order_id AND r.status = 'succeeded'");
$refunds->execute(['order_id' => $orderId]);
$refunded = (int) $refunds->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Receipt <?= Security::escape($order['public_id']) ?> | SmartStudyPro</title><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"></head>
<body><main class="container py-5"><div class="d-flex justify-content-between align-items-center mb-4"><a href="<?= htmlspecialchars(\App\Core\Url::to('/orders'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">Back to orders</a><button type="button" class="btn btn-primary" onclick="window.print()">Print receipt</button></div><article class="mx-auto border rounded-4 p-4 p-md-5" style="max-width:820px"><div class="d-flex justify-content-between flex-wrap gap-3 mb-4"><div><p class="small text-uppercase fw-bold mb-1" style="color:#FF7A00">SmartStudyPro</p><h1 class="h2 mb-1">Purchase receipt</h1><p class="text-muted mb-0">Order <?= Security::escape($order['public_id']) ?></p></div><div class="text-md-end"><strong><?= Security::escape(strtoupper($order['status'])) ?></strong><div class="small text-muted"><?= Security::escape($order['created_at']) ?></div></div></div><div class="row g-3 mb-4"><div class="col-md-6"><strong>Billed to</strong><div><?= Security::escape($order['customer_name']) ?></div><div><?= Security::escape($order['customer_email']) ?></div></div><div class="col-md-6"><strong>Payment status</strong><?php foreach ($paymentRows as $payment): ?><div><?= Security::escape(strtoupper($payment['provider']) . ' · ' . strtoupper($payment['status'])) ?></div><?php if ($payment['provider_transaction_id']): ?><small class="text-muted">Transaction: <?= Security::escape($payment['provider_transaction_id']) ?></small><?php endif; ?><?php endforeach; ?><?php if (!$paymentRows): ?><div class="text-muted">No payment attempt recorded</div><?php endif; ?></div></div><div class="table-responsive"><table class="table"><thead><tr><th>Item</th><th>Qty</th><th class="text-end">Amount</th></tr></thead><tbody><?php foreach ($items->fetchAll() as $item): ?><tr><td><?= Security::escape($item['item_title']) ?></td><td><?= (int) $item['quantity'] ?></td><td class="text-end">UGX <?= number_format((int) $item['line_total_ugx']) ?></td></tr><?php endforeach; ?></tbody></table></div><div class="ms-auto" style="max-width:320px"><div class="d-flex justify-content-between py-2"><span>Order total</span><strong>UGX <?= number_format((int) $order['total_ugx']) ?></strong></div><div class="d-flex justify-content-between py-2"><span>Refunded</span><strong>UGX <?= number_format($refunded) ?></strong></div><div class="d-flex justify-content-between border-top pt-3"><span>Net amount</span><strong>UGX <?= number_format(max(0, (int) $order['total_ugx'] - $refunded)) ?></strong></div></div><p class="small text-muted mt-4 mb-0">This receipt records the order and payment status held by SmartStudyPro. A pending order is not proof of successful payment.</p></article></main></body></html>
