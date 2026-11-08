<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Commerce\ShippingService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Url;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect(Url::to('/login?redirect=admin%2Fshipping'));
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('Administrator access is required.');
}
$shipping = new ShippingService($pdo);
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Your session expired. Refresh and try again.');
    }
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'zone') {
            $shipping->saveZone(null, (string) ($_POST['name'] ?? ''), (string) ($_POST['country_code'] ?? 'UG'), (string) ($_POST['region_name'] ?? ''), isset($_POST['is_active']));
        } elseif ($action === 'method') {
            $shipping->saveMethod(null, (int) ($_POST['zone_id'] ?? 0), (string) ($_POST['name'] ?? ''), (string) ($_POST['description'] ?? ''), (int) ($_POST['fee_ugx'] ?? -1), (int) ($_POST['estimated_days_min'] ?? 1), (int) ($_POST['estimated_days_max'] ?? 3), isset($_POST['is_active']));
        } elseif ($action === 'fulfillment') {
            $shipping->updateFulfillment((int) ($_POST['order_id'] ?? 0), (int) $user['id'], (string) ($_POST['status'] ?? ''), (string) ($_POST['carrier_name'] ?? ''), (string) ($_POST['tracking_reference'] ?? ''));
        } else {
            throw new RuntimeException('Unknown shipping action.');
        }
        Security::redirect(Url::to('/admin/shipping?saved=1'));
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$zones = $shipping->zones();
$methods = $shipping->methods();
$orders = $pdo->query("SELECT id,public_id,customer_name,shipping_name,shipping_phone,shipping_address,shipping_city,shipping_region,shipping_method_name,delivery_fee_ugx,fulfillment_status,carrier_name,tracking_reference,status FROM orders WHERE fulfillment_status != 'not_required' ORDER BY id DESC LIMIT 200")->fetchAll();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Shipping and fulfilment · SmartStudyPro</title><link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet"><link href="../assets/css/admin-theme.css" rel="stylesheet"></head>
<body><main class="container-fluid py-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><p class="small text-uppercase fw-bold mb-1" style="color:#ff7a00">Administration</p><h1 class="h2 mb-0">Shipping and fulfilment</h1></div><a class="btn btn-outline-secondary" href="orders.php">Back to orders</a></div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php elseif (isset($_GET['saved'])): ?><div class="alert alert-success">Shipping settings or fulfilment status saved.</div><?php endif; ?>
<div class="row g-4"><section class="col-xl-5"><div class="card p-3 p-lg-4 mb-4"><h2 class="h5">Create delivery zone</h2><p class="small text-muted">Use a country-wide zone by leaving the region blank, or create a district-specific zone.</p><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="zone"><label class="form-label">Zone name</label><input class="form-control mb-3" name="name" maxlength="100" required><label class="form-label">Country code</label><input class="form-control mb-3" name="country_code" value="UG" maxlength="2" required><label class="form-label">District / region (optional)</label><input class="form-control mb-3" name="region_name" maxlength="100"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" checked id="zone-active"><label class="form-check-label" for="zone-active">Active</label></div><button class="btn btn-primary">Save zone</button></form></div>
<div class="card p-3 p-lg-4"><h2 class="h5">Create delivery method</h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="method"><label class="form-label">Zone</label><select class="form-select mb-3" name="zone_id" required><option value="">Choose zone</option><?php foreach ($zones as $zone): ?><option value="<?= (int) $zone['id'] ?>"><?= Security::escape($zone['name'] . ' · ' . $zone['country_code'] . ($zone['region_name'] !== '' ? ' · ' . $zone['region_name'] : ' · All regions')) ?></option><?php endforeach; ?></select><label class="form-label">Method name</label><input class="form-control mb-3" name="name" maxlength="100" placeholder="Standard delivery" required><label class="form-label">Description</label><input class="form-control mb-3" name="description" maxlength="300"><label class="form-label">Delivery price (UGX)</label><input class="form-control mb-3" type="number" min="0" max="100000000" name="fee_ugx" required><div class="row g-3"><div class="col"><label class="form-label">Minimum days</label><input class="form-control" type="number" min="0" max="90" name="estimated_days_min" value="1" required></div><div class="col"><label class="form-label">Maximum days</label><input class="form-control" type="number" min="0" max="90" name="estimated_days_max" value="3" required></div></div><div class="form-check my-3"><input class="form-check-input" type="checkbox" name="is_active" checked id="method-active"><label class="form-check-label" for="method-active">Active</label></div><button class="btn btn-primary" <?= !$zones ? 'disabled' : '' ?>>Save delivery method</button></form></div></section>
<section class="col-xl-7"><div class="card p-3 p-lg-4 mb-4"><h2 class="h5">Configured delivery methods</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Zone</th><th>Method</th><th>Fee</th><th>ETA</th><th>Status</th></tr></thead><tbody><?php foreach ($methods as $method): ?><tr><td><?= Security::escape($method['zone_name'] . ' · ' . $method['country_code'] . ($method['region_name'] !== '' ? ' · ' . $method['region_name'] : '')) ?></td><td><?= Security::escape($method['name']) ?></td><td>UGX <?= number_format((int) $method['fee_ugx']) ?></td><td><?= (int) $method['estimated_days_min'] ?>–<?= (int) $method['estimated_days_max'] ?> days</td><td><?= $method['is_active'] ? 'Active' : 'Disabled' ?></td></tr><?php endforeach; ?><?php if (!$methods): ?><tr><td colspan="5" class="text-muted">No delivery methods configured yet.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card p-3 p-lg-4"><h2 class="h5">Physical orders</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order / recipient</th><th>Delivery</th><th>Fulfilment</th><th>Update</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><strong><?= Security::escape($order['public_id']) ?></strong><br><?= Security::escape($order['shipping_name'] ?: $order['customer_name']) ?><br><small><?= Security::escape($order['shipping_phone']) ?></small><br><small><?= Security::escape(trim($order['shipping_address'] . ', ' . $order['shipping_city'] . ', ' . $order['shipping_region'], ', ')) ?></small></td><td><?= Security::escape($order['shipping_method_name']) ?><br>UGX <?= number_format((int) $order['delivery_fee_ugx']) ?></td><td><?= Security::escape(ucfirst($order['fulfillment_status'])) ?><?php if ($order['tracking_reference'] !== ''): ?><br><small><?= Security::escape($order['carrier_name'] . ' · ' . $order['tracking_reference']) ?></small><?php endif; ?></td><td><?php if (in_array($order['status'], ['paid','partially_refunded'], true) && !in_array($order['fulfillment_status'], ['delivered','cancelled'], true)): ?><form method="post" class="d-grid gap-2"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="fulfillment"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><select class="form-select form-select-sm" name="status"><option value="processing">Processing</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option><option value="cancelled">Cancelled</option></select><input class="form-control form-control-sm" name="carrier_name" maxlength="100" placeholder="Carrier"><input class="form-control form-control-sm" name="tracking_reference" maxlength="120" placeholder="Tracking reference"><button class="btn btn-sm btn-primary">Update</button></form><?php else: ?><small class="text-muted">No action available</small><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="4" class="text-muted">No physical orders yet.</td></tr><?php endif; ?></tbody></table></div></div></section></div></main></body></html>
