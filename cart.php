<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Session;

Session::start();
$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=cart.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Your session expired. Refresh and try again.');
    }
    $key = (string) ($_POST['key'] ?? '');
    if (isset($_SESSION['ssp_cart'][$key])) unset($_SESSION['ssp_cart'][$key]);
    Security::redirect('cart.php');
}
$items = [];
$total = 0;
foreach ($_SESSION['ssp_cart'] ?? [] as $key => $entry) {
    $type = $entry['type'] ?? '';
    $id = (int) ($entry['id'] ?? 0);
    if (!in_array($type, ['course', 'product'], true) || $id < 1) continue;
    $table = $type === 'course' ? 'cms_courses' : 'cms_products';
    $query = $pdo->prepare("SELECT id, title, price_ugx FROM {$table} WHERE id = :id AND status = 'published'");
    $query->execute(['id' => $id]);
    $record = $query->fetch();
    if (!$record) {
        unset($_SESSION['ssp_cart'][$key]);
        continue;
    }
    $quantity = $type === 'course' ? 1 : max(1, min(20, (int) ($entry['quantity'] ?? 1)));
    $line = (int) $record['price_ugx'] * $quantity;
    $total += $line;
    $items[] = ['key' => $key, 'type' => $type, 'title' => $record['title'], 'price' => (int) $record['price_ugx'], 'quantity' => $quantity, 'line' => $line];
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your cart | SmartStudyPro"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet"></head><body><?php include __DIR__ . '/nav.php'; ?><main class="container py-5"><div class="d-flex justify-content-between align-items-center mb-4"><div><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Shopping cart</p><h1 class="h2 mb-0">Your selected items</h1></div><a href="courses.php" class="btn btn-outline-secondary">Continue browsing</a></div><?php if (!$items): ?><div class="border rounded-4 p-5 text-center"><i class="bi bi-bag-x display-3 text-muted"></i><h2 class="h4 mt-3">Your cart is empty</h2><p class="text-muted">Explore courses and study products to get started.</p><a class="btn btn-primary" href="courses.php">Browse courses</a></div><?php else: ?><div class="row g-4"><div class="col-lg-8"><div class="border rounded-4 overflow-hidden"><?php foreach ($items as $item): ?><div class="p-3 p-md-4 border-bottom d-flex justify-content-between gap-3"><div><span class="badge text-bg-light"><?= Security::escape(ucfirst($item['type'])) ?></span><h2 class="h5 mt-2 mb-1"><?= Security::escape($item['title']) ?></h2><p class="text-muted mb-0">UGX <?= number_format($item['price']) ?> × <?= $item['quantity'] ?></p></div><div class="text-end"><strong>UGX <?= number_format($item['line']) ?></strong><form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="key" value="<?= Security::escape($item['key']) ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></div></div><?php endforeach; ?></div></div><div class="col-lg-4"><div class="border rounded-4 p-4 shadow-sm"><h2 class="h5">Order summary</h2><div class="d-flex justify-content-between my-3"><span>Subtotal</span><strong>UGX <?= number_format($total) ?></strong></div><p class="small text-muted">Prices are confirmed again from the catalogue when your order is created.</p><form method="post" action="checkout.php"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><button class="btn btn-primary w-100">Proceed to checkout</button></form></div></div></div><?php endif; ?></main><script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script></body></html>
