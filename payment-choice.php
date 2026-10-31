<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=cart.php');
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$commerce = new CommerceService($pdo);
$order = $orderId ? $commerce->order((int) $orderId, (int) $user['id']) : null;
$demoEnabled = (new App\Commerce\DemoPaymentService($pdo))->enabled();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Choose payment | SmartStudyPro"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet"></head><body><?php include __DIR__ . '/nav.php'; ?><main class="container py-5"><div class="mx-auto border rounded-4 p-4 p-lg-5 shadow-sm" style="max-width:620px"><h1 class="h2">Choose how to pay</h1><p class="text-muted">Order <?= Security::escape($order['public_id']) ?> · UGX <?= number_format((int) $order['total_ugx']) ?></p><form method="post" action="payment-start.php"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><button name="provider" value="paypal" class="btn btn-outline-primary btn-lg w-100 mb-3">Continue with PayPal</button><button name="provider" value="dpo" class="btn btn-primary btn-lg w-100">Continue with DPO</button></form><?php if ($demoEnabled): ?><section class="mt-4 p-3 rounded-3 border border-warning-subtle bg-warning-subtle" aria-labelledby="demo-payment-title"><p class="small fw-bold text-uppercase mb-1">Demo environment only</p><h2 id="demo-payment-title" class="h5">Simulate a payment</h2><p class="small mb-3">No money is charged and no PayPal or DPO request is made. Choose an outcome to test checkout.</p><form method="post" action="payment-demo.php"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><div class="d-grid gap-2"><button class="btn btn-success" name="scenario" value="success">Simulate successful payment</button><button class="btn btn-outline-danger" name="scenario" value="failed">Simulate failed payment</button><button class="btn btn-outline-secondary" name="scenario" value="cancelled">Simulate cancelled payment</button></div></form></section><?php endif; ?><p class="small text-muted mt-3 mb-0">Live payment confirmation is checked directly with the provider before any course or download access is granted.</p></div></main></body></html>
