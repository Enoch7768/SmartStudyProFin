<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Commerce\CommerceService;
use App\Commerce\PaymentGateway;
use App\Core\Database;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit;
}
$token = (string) ($_GET['TransactionToken'] ?? $_GET['transToken'] ?? $_POST['TransactionToken'] ?? $_POST['transToken'] ?? '');
$companyRef = (string) ($_GET['CompanyRef'] ?? $_POST['CompanyRef'] ?? '');
if ($token === '' || $companyRef === '') {
    http_response_code(400);
    exit('Missing transaction reference.');
}
try {
    $service = new CommerceService(Database::connect());
    $payment = $service->providerPayment('dpo', $companyRef);
    if (!$payment || !hash_equals((string) $payment['provider_order_id'], $token)) {
        http_response_code(404);
        exit('Unknown transaction.');
    }
    if ($payment['status'] === 'paid') {
        http_response_code(200);
        exit('OK');
    }
    $verified = (new PaymentGateway())->verifyDpo($token, $companyRef);
    $amount = (int) round((float) $verified['amount']);
    if ($verified['result'] !== '000' || !in_array($verified['fraud_alert'], ['000', '001'], true) || $verified['company_ref'] !== $companyRef || $verified['currency'] !== 'UGX' || $amount !== (int) $payment['total_ugx'] || $verified['approval'] === '') {
        http_response_code(409);
        exit('Payment not verified.');
    }
    $service->confirmPayment((int) $payment['id'], 'dpo', $token, $verified['approval'], $amount, 'UGX', 'COMPLETED', 'dpo-' . $token);
    http_response_code(200);
    echo 'OK';
} catch (Throwable $exception) {
    error_log('DPO callback verification failed: ' . $exception->getMessage());
    http_response_code(500);
}
