<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Commerce\ShippingService;
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
    $checkoutToken = (string) ($_POST['_checkout_token'] ?? '');
    if (empty($_SESSION['_checkout_token']) || !hash_equals((string) $_SESSION['_checkout_token'], $checkoutToken)) {
        $error = 'This checkout request has already been used. Refresh the page to try again.';
    } else {
        unset($_SESSION['_checkout_token']);
    }
    try {
        if ($error !== null) throw new RuntimeException($error);
        $cart = array_values($_SESSION['ssp_cart'] ?? []);
        $shipping = ['method_id' => (int) ($_POST['shipping_method_id'] ?? 0), 'name' => (string) ($_POST['shipping_name'] ?? ''), 'phone' => (string) ($_POST['shipping_phone'] ?? ''), 'address' => (string) ($_POST['shipping_address'] ?? ''), 'city' => (string) ($_POST['shipping_city'] ?? ''), 'region' => (string) ($_POST['shipping_region'] ?? ''), 'country' => (string) ($_POST['shipping_country'] ?? 'UG')];
        $order = (new CommerceService($pdo))->createOrder((int) $user['id'], $cart, $shipping);
        unset($_SESSION['ssp_cart']);
        $_SESSION['ssp_last_order_id'] = (int) $order['id'];
        Security::redirect('payment-choice.php?order=' . (int) $order['id']);
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Checkout could not be completed.';
        $_SESSION['_checkout_token'] = bin2hex(random_bytes(24));
    }
}
if (empty($_SESSION['_checkout_token'])) $_SESSION['_checkout_token'] = bin2hex(random_bytes(24));
$items = [];
$total = 0;
foreach ($_SESSION['ssp_cart'] ?? [] as $entry) {
    $type = $entry['type'] ?? '';
    $id = (int) ($entry['id'] ?? 0);
    if (!in_array($type, ['course', 'product'], true) || $id < 1) continue;
    $table = $type === 'course' ? 'cms_courses' : 'cms_products';
    $offeringId = (int) ($entry['offering_id'] ?? 0);
    if ($type === 'course' && $offeringId > 0) {
        $query = $pdo->prepare("SELECT c.title || ' · ' || o.title AS title,o.price_ugx FROM course_offerings o INNER JOIN cms_courses c ON c.id=o.course_id WHERE o.id=:offering AND o.course_id=:id AND o.is_published=1 AND c.status='published'");
        $query->execute(['offering'=>$offeringId,'id'=>$id]);
    } else {
        $query = $pdo->prepare("SELECT title, price_ugx" . ($type === 'product' ? ', product_type, read_price_ugx, download_price_ugx' : '') . " FROM {$table} WHERE id = :id AND status = 'published'");
        $query->execute(['id' => $id]);
    }
    $item = $query->fetch();
    if (!$item) continue;
    $quantity = $type === 'course' ? 1 : max(1, min(20, (int) ($entry['quantity'] ?? 1)));
    $entitlement = (string) ($entry['entitlement'] ?? '');
    $price = $type === 'product' && ($item['product_type'] ?? '') === 'online'
        ? (int) ($entitlement === 'read' ? $item['read_price_ugx'] : $item['download_price_ugx'])
        : (int) $item['price_ugx'];
    $line = $price * $quantity;
    $total += $line;
    $items[] = ['title' => $item['title'], 'line' => $line, 'entitlement' => $entitlement, 'physical' => $type === 'product' && ($item['product_type'] ?? '') === 'physical'];
}
$hasPhysical = count(array_filter($items, static fn (array $item): bool => $item['physical'])) > 0;
$shippingMethods = $hasPhysical ? (new ShippingService($pdo))->methods() : [];
?>
<!DOCTYPE html>
<html lang="en"><head>
  <base href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">
<link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout | SmartStudyPro</title><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head><body><?php include __DIR__ . '/nav.php'; ?><main id="main-content" class="container py-5"><div class="mx-auto border rounded-4 p-4 p-lg-5 shadow-sm" style="max-width:760px"><p class="text-uppercase fw-bold small mb-1" style="color:#FF7A00">Secure checkout</p><h1 class="h2 mb-3">Review your order</h1><?php if ($error): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?><?php if (!$items): ?><p class="text-muted">Your cart is empty or the selected items are no longer available.</p><a href="<?= htmlspecialchars(\App\Core\Url::to('/cart'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">Return to cart</a><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="_checkout_token" value="<?= Security::escape($_SESSION["_checkout_token"]) ?>"><div class="list-group list-group-flush mb-3"><?php foreach ($items as $item): ?><div class="list-group-item px-0 d-flex justify-content-between"><span><?= Security::escape($item['title']) ?><?= $item['physical'] ? ' · Physical delivery' : ($item['entitlement'] !== '' ? ' · ' . Security::escape(ucfirst($item['entitlement'])) . ' access' : '') ?></span><strong>UGX <?= number_format($item['line']) ?></strong></div><?php endforeach; ?></div><div class="d-flex justify-content-between fs-5 mb-4"><strong>Items subtotal</strong><strong>UGX <?= number_format($total) ?></strong></div><?php if ($hasPhysical): ?><section class="border rounded-3 p-3 mb-4"><h2 class="h5">Delivery details</h2><p class="small text-muted">Delivery is calculated on the server from your region and selected method. Digital items are not charged delivery.</p><div class="mb-3"><label class="form-label" for="shipping_name">Recipient name</label><input class="form-control" id="shipping_name" name="shipping_name" maxlength="120" required></div><div class="mb-3"><label class="form-label" for="shipping_phone">Phone number</label><input class="form-control" id="shipping_phone" name="shipping_phone" maxlength="40" required></div><div class="mb-3"><label class="form-label" for="shipping_address">Street / delivery address</label><textarea class="form-control" id="shipping_address" name="shipping_address" maxlength="240" required></textarea></div><div class="row g-3"><div class="col-md-6"><label class="form-label" for="shipping_city">City / town</label><input class="form-control" id="shipping_city" name="shipping_city" maxlength="100" required></div><div class="col-md-6"><label class="form-label" for="shipping_region">District / region</label><input class="form-control" id="shipping_region" name="shipping_region" maxlength="100" required></div></div><input type="hidden" name="shipping_country" value="UG"><div class="mt-3"><label class="form-label" for="shipping_method_id">Delivery method</label><select class="form-select" id="shipping_method_id" name="shipping_method_id" required><option value="">Choose a method</option><?php foreach ($shippingMethods as $method): ?><option value="<?= (int) $method['id'] ?>"><?= Security::escape($method['zone_name'] . ' · ' . $method['name'] . ' · UGX ' . number_format((int) $method['fee_ugx']) . ' · ' . (int) $method['estimated_days_min'] . '-' . (int) $method['estimated_days_max'] . ' days') ?></option><?php endforeach; ?></select><?php if (!$shippingMethods): ?><p class="text-danger small mt-2">Delivery is not configured yet. Please contact the store before placing this order.</p><?php endif; ?></div></section><?php endif; ?><p class="small text-muted">Your account: <?= Security::escape($user['email']) ?>. You will choose PayPal or DPO after the order is securely created.</p><button class="btn btn-primary btn-lg w-100" <?= $hasPhysical && !$shippingMethods ? 'disabled' : '' ?>>Create order and choose payment</button></form><?php endif; ?></div></main><script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script></body></html>
