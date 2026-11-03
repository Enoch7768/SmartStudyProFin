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
$commerce = new CommerceService($pdo);
$orders = $commerce->history((int) $user['id']);
$demoLookup = $pdo->prepare('SELECT scenario FROM demo_payment_events WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
foreach ($orders as &$order) {
    $demoLookup->execute(['order_id' => (int) $order['id']]);
    $order['demo_scenario'] = $demoLookup->fetchColumn() ?: null;
}
unset($order);
$lineQuery = $pdo->prepare("SELECT oi.id, oi.order_id, oi.item_title, oi.item_type, oi.product_type FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.user_id = :user_id AND o.status IN ('paid', 'partially_refunded') ORDER BY oi.id");
$lineQuery->execute(['user_id' => (int) $user['id']]);
$purchaseItems = $lineQuery->fetchAll();
$itemsByOrder = [];
$filesByItem = [];
foreach ($purchaseItems as $item) {
    $itemsByOrder[(int) $item['order_id']][] = $item;
    if ($item['item_type'] === 'product' && $item['product_type'] === 'online') $filesByItem[(int) $item['id']] = $commerce->purchasedFiles((int) $user['id'], (int) $item['id']);
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<link href="<?= htmlspecialchars(rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/favicon.php', ENT_QUOTES, 'UTF-8') ?>" rel="icon"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My orders | SmartStudyPro</title><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet"><link href="assets/css/device-responsive.css" rel="stylesheet">
</head><body><?php include __DIR__ . '/nav.php'; ?><main id="main-content" class="container py-5"><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Account</p><h1 class="h2 mb-4">Order history</h1><?php if (isset($_GET['demo']) && in_array($_GET['demo'], ['success', 'failed', 'cancelled'], true)): ?><div class="alert alert-info" role="status"><strong>Demo payment:</strong> <?= Security::escape(ucfirst((string) $_GET['demo'])) ?>. This was simulated; no money moved.</div><?php endif; ?><?php if (!$orders): ?><div class="border rounded-4 p-5 text-center"><h2 class="h5">No orders yet</h2><p class="text-muted">Your purchases will appear here.</p><a href="courses.php" class="btn btn-primary">Explore courses</a></div><?php else: ?><div class="table-responsive border rounded-4"><table class="table align-middle mb-0"><thead><tr><th class="p-3">Order</th><th>Status</th><th>Payment</th><th>Total</th><th>Purchased items</th><th>Date</th><th>Action</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td class="p-3 fw-semibold"><a href="receipt.php?order=<?= (int) $order['id'] ?>"><?= Security::escape($order['public_id']) ?></a></td><td><span class="badge <?= $order['status'] === 'paid' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= Security::escape(ucfirst($order['status'])) ?></span></td><td><?php if ($order['demo_scenario']): ?><span class="badge text-bg-warning">DEMO · <?= Security::escape(strtoupper($order['demo_scenario'])) ?></span><?php else: ?><span class="text-muted">Provider</span><?php endif; ?></td><td>UGX <?= number_format((int) $order['total_ugx']) ?></td><td><?php foreach ($itemsByOrder[(int) $order['id']] ?? [] as $item): ?><div class="small mb-1"><?= Security::escape($item['item_title']) ?><?php if ($item['item_type'] === 'product' && $item['product_type'] === 'online'): ?><?php foreach ($filesByItem[(int) $item['id']] ?? [] as $file): ?><?php if (in_array($file['mime_type'], ['application/pdf', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)): ?> · <a href="<?= $file['asset_id'] ? 'view.php?asset=' . (int) $file['asset_id'] : 'view.php?item=' . (int) $item['id'] ?>" target="_blank" rel="noopener">View</a><?php endif; ?> <a href="<?= $file['asset_id'] ? 'download.php?asset=' . (int) $file['asset_id'] : 'download.php?item=' . (int) $item['id'] ?>">Download <?= Security::escape($file['original_name']) ?></a><?php endforeach; ?><?php endif; ?></div><?php endforeach; ?></td><td><?= Security::escape(date('d M Y', strtotime($order['created_at']))) ?></td><td><?php if ($order['status'] === 'pending'): ?><a class="btn btn-sm btn-primary text-nowrap" href="<?= \\App\\Core\\Url::to('/payment-choice?order=' . (int) $order['id']) ?>">Continue payment</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
