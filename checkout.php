<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Session;

Session::start();
$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=cart.php');
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Your session expired. Refresh and try again.');
    }
    try {
        $cart = array_values($_SESSION['ssp_cart'] ?? []);
        $order = (new CommerceService($pdo))->createOrder((int) $user['id'], $cart);
        $_SESSION['ssp_last_order_id'] = (int) $order['id'];
        Security::redirect('payment-choice.php?order=' . (int) $order['id']);
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Checkout could not be completed.';
    }
}
$items = [];
$total = 0;
foreach ($_SESSION['ssp_cart'] ?? [] as $entry) {
    $type = $entry['type'] ?? '';
    $id = (int) ($entry['id'] ?? 0);
    if (!in_array($type, ['course', 'product'], true) || $id < 1) continue;
    $table = $type === 'course' ? 'cms_courses' : 'cms_products';
    $query = $pdo->prepare("SELECT title, price_ugx FROM {$table} WHERE id = :id AND status = 'published'");
    $query->execute(['id' => $id]);
    $item = $query->fetch();
    if (!$item) continue;
    $quantity = $type === 'course' ? 1 : max(1, min(20, (int) ($entry['quantity'] ?? 1)));
    $line = (int) $item['price_ugx'] * $quantity;
    $total += $line;
    $items[] = ['title' => $item['title'], 'line' => $line];
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout | SmartStudyPro"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet"></head><body><?php include __DIR__ . '/nav.php'; ?><main class="container py-5"><div class="mx-auto border rounded-4 p-4 p-lg-5 shadow-sm" style="max-width:760px"><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Secure checkout</p><h1 class="h2 mb-3">Review your order</h1><?php if ($error): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?><?php if (!$items): ?><p class="text-muted">Your cart is empty or the selected items are no longer available.</p><a href="cart.php" class="btn btn-outline-secondary">Return to cart</a><?php else: ?><div class="list-group list-group-flush mb-3"><?php foreach ($items as $item): ?><div class="list-group-item px-0 d-flex justify-content-between"><span><?= Security::escape($item['title']) ?></span><strong>UGX <?= number_format($item['line']) ?></strong></div><?php endforeach; ?></div><div class="d-flex justify-content-between fs-5 mb-4"><strong>Total</strong><strong>UGX <?= number_format($total) ?></strong></div><p class="small text-muted">Your account: <?= Security::escape($user['email']) ?>. You will choose PayPal or DPO after the order is securely created.</p><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><button class="btn btn-primary btn-lg w-100">Create order and choose payment</button></form><?php endif; ?></div></main><script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script></body></html>
