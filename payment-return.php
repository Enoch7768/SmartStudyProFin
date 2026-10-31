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
if (!$user) Security::redirect('login.php?redirect=profile.php');
$provider = (string) ($_GET['provider'] ?? '');
$service = new CommerceService($pdo);
$success = false;
$message = 'We could not verify this payment yet. Your order remains pending until the provider confirms it.';
try {
    if ($provider === 'paypal') {
        $reference = (string) ($_GET['token'] ?? '');
        $payment = $reference !== '' ? $service->providerPayment('paypal', $reference) : null;
        if (!$payment || (int) $payment['user_id'] !== (int) $user['id']) throw new RuntimeException('This PayPal order could not be matched to your account.');
        if ($payment['status'] === 'paid') {
            $success = true;
        } else {
        $capture = (new PaymentGateway())->capturePayPal($reference);
        $rate = max(1, (int) Config::get('payments.paypal_ugx_per_usd', 3700));
        $expectedUsd = number_format(((int) $payment['total_ugx']) / $rate, 2, '.', '');
        if ($capture['order_status'] !== 'COMPLETED' || $capture['capture_status'] !== 'COMPLETED' || $capture['currency'] !== 'USD' || $capture['amount'] !== $expectedUsd || $capture['capture_id'] === '') throw new RuntimeException('PayPal has not confirmed the exact expected amount.');
        $service->confirmPayment((int) $payment['id'], 'paypal', $reference, $capture['capture_id'], (int) $payment['total_ugx'], 'UGX', 'COMPLETED', 'capture-' . $capture['capture_id']);
        $success = ($service->providerPayment('paypal', $reference)['status'] ?? '') === 'paid';
        }
    } elseif ($provider === 'dpo') {
        $token = (string) ($_GET['TransactionToken'] ?? $_GET['transToken'] ?? '');
        $companyRef = (string) ($_GET['CompanyRef'] ?? '');
        if ($token === '' || $companyRef === '') throw new RuntimeException('DPO did not return the required transaction reference.');
        $payment = $service->providerPayment('dpo', $companyRef);
        if (!$payment || (int) $payment['user_id'] !== (int) $user['id'] || !hash_equals((string) $payment['provider_order_id'], $token)) throw new RuntimeException('This DPO transaction could not be matched to your account.');
        if ($payment['status'] === 'paid') {
            $success = true;
        } else {
        $verified = (new PaymentGateway())->verifyDpo($token, $companyRef);
        $amount = (int) round((float) $verified['amount']);
        if ($verified['result'] !== '000' || !in_array($verified['fraud_alert'], ['000', '001'], true) || $verified['company_ref'] !== $companyRef || $verified['currency'] !== 'UGX' || $amount !== (int) $payment['total_ugx'] || $verified['approval'] === '') throw new RuntimeException('DPO did not verify the exact order amount and currency.');
        $service->confirmPayment((int) $payment['id'], 'dpo', $token, $verified['approval'], $amount, 'UGX', 'COMPLETED', 'dpo-' . $token);
        $success = ($service->providerPayment('dpo', $companyRef)['status'] ?? '') === 'paid';
        }
    } elseif ($provider === 'cancel') {
        $message = 'Payment was cancelled. Your order has not been paid and no course access has been granted.';
    } else {
        throw new RuntimeException('Unknown payment return.');
    }
    if ($success) {
        \App\Core\Session::start();
        unset($_SESSION['ssp_cart']);
        $message = 'Payment confirmed. Your purchase is now available in your account.';
    }
} catch (Throwable $exception) {
    error_log('Payment return verification failed: ' . $exception->getMessage());
    if ($exception instanceof RuntimeException) $message = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment status | SmartStudyPro"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet"></head><body><?php include __DIR__ . '/nav.php'; ?><main id="main-content" class="container py-5"><div class="mx-auto p-4 p-lg-5 rounded-4 shadow-sm border text-center" style="max-width:680px"><i class="bi <?= $success ? 'bi-check-circle-fill text-success' : 'bi-hourglass-split text-warning' ?> display-3"></i><h1 class="h2 mt-3">Payment status</h1><p class="mb-4"><?= Security::escape($message) ?></p><a class="btn btn-primary me-2" href="profile.php">My account</a><a class="btn btn-outline-secondary" href="learning.php">My learning</a></div></main></body></html>
