<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Commerce\PaymentGateway;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=cart.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Invalid checkout request.');
}
$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
$provider = (string) ($_POST['provider'] ?? '');
$service = new CommerceService($pdo);
$order = $orderId ? $service->order((int) $orderId, (int) $user['id']) : null;
if (!$order || $order['status'] !== 'pending') {
    http_response_code(404);
    exit('The order could not be found.');
}
$base = rtrim((string) Config::get('app.url'), '/');
if (!str_starts_with($base, 'https://')) {
    http_response_code(503);
    exit('Secure HTTPS application URL configuration is required before accepting payments.');
}
try {
    $payment = $service->createPayment((int) $order['id'], (int) $user['id'], $provider);
    $gateway = new PaymentGateway();
    $result = $provider === 'paypal'
        ? $gateway->startPayPal($payment, $order, $base . '/payment-return.php?provider=paypal', $base . '/payment-return.php?provider=cancel')
        : ($provider === 'dpo'
            ? $gateway->startDpo($payment, $order, $base . '/dpo-webhook.php', $base . '/payment-return.php?provider=dpo')
            : throw new RuntimeException('Choose PayPal or DPO.'));
    $service->updateProviderReference((int) $payment['id'], $result['id'], $result['status'], $result['metadata']);
    $approval = parse_url((string) $result['approve_url']);
    $allowedHosts = $provider === 'paypal' ? ['www.paypal.com', 'www.sandbox.paypal.com'] : ['secure.3gdirectpay.com'];
    if (!is_array($approval) || strtolower((string) ($approval['scheme'] ?? '')) !== 'https' || !in_array(strtolower((string) ($approval['host'] ?? '')), $allowedHosts, true)) throw new RuntimeException('The payment provider returned an untrusted approval URL.');
    header('Location: ' . $result['approve_url'], true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Payment start failed: ' . $exception->getMessage());
    http_response_code(503);
    exit(Security::escape($exception instanceof RuntimeException ? $exception->getMessage() : 'The payment provider is temporarily unavailable.'));
}
