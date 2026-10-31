<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\DemoPaymentService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=cart.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Invalid demo payment request.');
}
$service = new DemoPaymentService($pdo);
if (!$service->enabled()) {
    http_response_code(404);
    exit('Demo payments are not available.');
}
$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
try {
    if (!$orderId) throw new RuntimeException('Choose a valid order.');
    $result = $service->simulate((int) $orderId, (int) $user['id'], (string) ($_POST['scenario'] ?? ''));
    Security::redirect('orders.php?demo=' . rawurlencode($result['scenario']));
} catch (Throwable $exception) {
    http_response_code(400);
    exit(Security::escape($exception instanceof RuntimeException ? $exception->getMessage() : 'The demo payment could not be completed.'));
}
