<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Commerce\PaymentGateway;
use App\Core\Database;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit;
}
$raw = file_get_contents('php://input');
$event = json_decode((string) $raw, true);
if (!is_array($event) || empty($event['id'])) {
    http_response_code(400);
    exit;
}
$headers = [];
foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $key) $headers[$key] = (string) ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] ?? '');
try {
    if (!(new PaymentGateway())->verifyPayPalWebhook($headers, $event)) {
        http_response_code(400);
        exit;
    }
    if (($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED') {
        $resource = $event['resource'] ?? [];
        $providerOrderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
        $captureId = (string) ($resource['id'] ?? '');
        $service = new CommerceService(Database::connect());
        $payment = $providerOrderId !== '' ? $service->providerPayment('paypal', $providerOrderId) : null;
        if ($payment && ($resource['amount']['currency_code'] ?? '') === 'USD') {
            $rate = max(1, (int) App\Core\Config::get('payments.paypal_ugx_per_usd', 3700));
            $expected = number_format(((int) $payment['total_ugx']) / $rate, 2, '.', '');
            if (($resource['amount']['value'] ?? '') === $expected && ($resource['status'] ?? '') === 'COMPLETED') {
                $service->confirmPayment((int) $payment['id'], 'paypal', $providerOrderId, $captureId, (int) $payment['total_ugx'], 'UGX', 'COMPLETED', (string) $event['id']);
            }
        }
    }
    http_response_code(200);
    echo 'OK';
} catch (Throwable $exception) {
    error_log('PayPal webhook processing failed: ' . $exception->getMessage());
    http_response_code(500);
}
