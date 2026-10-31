<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=orders.php');
$orders = (new CommerceService($pdo))->history((int) $user['id']);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My orders | SmartStudyPro"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet"></head><body><?php include __DIR__ . '/nav.php'; ?><main class="container py-5"><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Account</p><h1 class="h2 mb-4">Order history</h1><?php if (!$orders): ?><div class="border rounded-4 p-5 text-center"><h2 class="h5">No orders yet</h2><p class="text-muted">Your purchases will appear here.</p><a href="courses.php" class="btn btn-primary">Explore courses</a></div><?php else: ?><div class="table-responsive border rounded-4"><table class="table align-middle mb-0"><thead><tr><th class="p-3">Order</th><th>Status</th><th>Total</th><th>Date</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td class="p-3 fw-semibold"><?= Security::escape($order['public_id']) ?></td><td><span class="badge <?= $order['status'] === 'paid' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= Security::escape(ucfirst($order['status'])) ?></span></td><td>UGX <?= number_format((int) $order['total_ugx']) ?></td><td><?= Security::escape(date('d M Y', strtotime($order['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
