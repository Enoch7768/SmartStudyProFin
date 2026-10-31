<?php

declare(strict_types=1);

namespace App\Commerce;

use App\Core\Config;
use RuntimeException;

final class PaymentGateway
{
    private function request(string $url, string $method, array $headers, ?string $body = null): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for live payment processing.');
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($response) || $status < 200 || $status >= 300) throw new RuntimeException('The payment provider could not complete the request. ' . ($error !== '' ? 'Connection error.' : 'Please try again.'));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['raw' => $response];
    }

    private function paypalToken(): string
    {
        $client = (string) Config::get('payments.paypal_client_id');
        $secret = (string) Config::get('payments.paypal_secret');
        if ($client === '' || $secret === '') throw new RuntimeException('PayPal has not been configured by the administrator.');
        $base = Config::get('payments.paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $result = $this->request($base . '/v1/oauth2/token', 'POST', ['Authorization: Basic ' . base64_encode($client . ':' . $secret), 'Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_credentials');
        if (empty($result['access_token'])) throw new RuntimeException('PayPal authentication failed.');
        return (string) $result['access_token'];
    }

    public function startPayPal(array $payment, array $order, string $returnUrl, string $cancelUrl): array
    {
        $mode = Config::get('payments.paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $token = $this->paypalToken();
        $payload = ['intent' => 'CAPTURE', 'purchase_units' => [['reference_id' => (string) $order['public_id'], 'custom_id' => (string) $payment['id'], 'invoice_id' => (string) $order['public_id'], 'amount' => ['currency_code' => 'USD', 'value' => number_format(((int) $order['total_ugx']) / max(1, (int) Config::get('payments.paypal_ugx_per_usd', 3700)), 2, '.', '')]]], 'payment_source' => ['paypal' => ['experience_context' => ['return_url' => $returnUrl, 'cancel_url' => $cancelUrl, 'user_action' => 'PAY_NOW']]]];
        $result = $this->request($mode . '/v2/checkout/orders', 'POST', ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Prefer: return=representation'], json_encode($payload, JSON_THROW_ON_ERROR));
        $approve = '';
        foreach ($result['links'] ?? [] as $link) if (($link['rel'] ?? '') === 'approve' && filter_var($link['href'] ?? '', FILTER_VALIDATE_URL)) $approve = $link['href'];
        if (($result['status'] ?? '') !== 'CREATED' || empty($result['id']) || $approve === '') throw new RuntimeException('PayPal did not return a valid approval link.');
        return ['id' => (string) $result['id'], 'approve_url' => $approve, 'status' => 'CREATED', 'metadata' => ['currency' => 'USD', 'conversion_rate' => (int) Config::get('payments.paypal_ugx_per_usd', 3700)]];
    }

    public function capturePayPal(string $providerOrderId): array
    {
        $base = Config::get('payments.paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $result = $this->request($base . '/v2/checkout/orders/' . rawurlencode($providerOrderId) . '/capture', 'POST', ['Authorization: Bearer ' . $this->paypalToken(), 'Content-Type: application/json', 'Prefer: return=representation'], '{}');
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];
        return ['order_status' => (string) ($result['status'] ?? ''), 'capture_id' => (string) ($capture['id'] ?? ''), 'capture_status' => (string) ($capture['status'] ?? ''), 'currency' => (string) ($capture['amount']['currency_code'] ?? ''), 'amount' => (string) ($capture['amount']['value'] ?? '')];
    }

    public function verifyPayPalWebhook(array $headers, array $event): bool
    {
        $webhookId = (string) Config::get('payments.paypal_webhook_id');
        if ($webhookId === '') return false;
        $base = Config::get('payments.paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $payload = [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => $event
        ];
        foreach (['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'] as $key) if ($payload[$key] === '') return false;
        $result = $this->request($base . '/v1/notifications/verify-webhook-signature', 'POST', ['Authorization: Bearer ' . $this->paypalToken(), 'Content-Type: application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }

    public function startDpo(array $payment, array $order, string $backUrl, string $redirectUrl): array
    {
        $companyToken = (string) Config::get('payments.dpo_company_token');
        if ($companyToken === '') throw new RuntimeException('DPO has not been configured by the administrator.');
        $base = rtrim((string) Config::get('payments.dpo_endpoint', 'https://secure.3gdirectpay.com/API/v6/'), '/');
        $xml = '<API3G><CompanyToken>' . htmlspecialchars($companyToken, ENT_XML1) . '</CompanyToken><Request>createToken</Request><Transaction><PaymentAmount>' . (int) $order['total_ugx'] . '</PaymentAmount><PaymentCurrency>UGX</PaymentCurrency><CompanyRef>' . htmlspecialchars((string) $order['public_id'], ENT_XML1) . '</CompanyRef><RedirectURL>' . htmlspecialchars($redirectUrl, ENT_XML1) . '</RedirectURL><BackURL>' . htmlspecialchars($backUrl, ENT_XML1) . '</BackURL><CompanyRefUnique>1</CompanyRefUnique><PTL>30</PTL><customerEmail>' . htmlspecialchars((string) $order['customer_email'], ENT_XML1) . '</customerEmail><customerFirstName>' . htmlspecialchars((string) $order['customer_name'], ENT_XML1) . '</customerFirstName><customerLastName>Customer</customerLastName><DefaultPayment>MO</DefaultPayment><Services><Service><ServiceType>' . htmlspecialchars((string) Config::get('payments.dpo_service_type', '5525'), ENT_XML1) . '</ServiceType><ServiceDescription>SmartStudyPro order ' . htmlspecialchars((string) $order['public_id'], ENT_XML1) . '</ServiceDescription><ServiceDate>' . gmdate('Y/m/d H:i') . '</ServiceDate></Service></Services></Transaction></API3G>';
        $response = $this->request($base, 'POST', ['Content-Type: application/xml; charset=utf-8', 'Accept: application/xml'], $xml);
        $raw = (string) ($response['raw'] ?? '');
        $parsed = [];
        if ($raw !== '') {
            $xmlResult = simplexml_load_string($raw);
            if ($xmlResult !== false) $parsed = json_decode(json_encode($xmlResult), true) ?: [];
        }
        $token = (string) ($parsed['TransToken'] ?? '');
        if (($parsed['Result'] ?? '') !== '000' || $token === '') throw new RuntimeException('DPO could not create a payment session.');
        return ['id' => $token, 'approve_url' => rtrim((string) Config::get('payments.dpo_checkout_url', 'https://secure.3gdirectpay.com/payv2.php'), '?') . '?ID=' . rawurlencode($token), 'status' => 'CREATED', 'metadata' => ['company_ref' => $order['public_id']]];
    }

    public function verifyDpo(string $token, string $companyRef): array
    {
        $companyToken = (string) Config::get('payments.dpo_company_token');
        if ($companyToken === '') throw new RuntimeException('DPO has not been configured by the administrator.');
        $base = rtrim((string) Config::get('payments.dpo_endpoint', 'https://secure.3gdirectpay.com/API/v6/'), '/');
        $xml = '<API3G><CompanyToken>' . htmlspecialchars($companyToken, ENT_XML1) . '</CompanyToken><Request>verifyToken</Request><TransactionToken>' . htmlspecialchars($token, ENT_XML1) . '</TransactionToken><CompanyRef>' . htmlspecialchars($companyRef, ENT_XML1) . '</CompanyRef></API3G>';
        $response = $this->request($base, 'POST', ['Content-Type: application/xml; charset=utf-8', 'Accept: application/xml'], $xml);
        $raw = (string) ($response['raw'] ?? '');
        $parsed = [];
        if ($raw !== '') {
            $xmlResult = simplexml_load_string($raw);
            if ($xmlResult !== false) $parsed = json_decode(json_encode($xmlResult), true) ?: [];
        }
        return ['result' => (string) ($parsed['Result'] ?? ''), 'amount' => (string) ($parsed['TransactionAmount'] ?? ''), 'currency' => (string) ($parsed['TransactionCurrency'] ?? ''), 'approval' => (string) ($parsed['TransactionApproval'] ?? ''), 'company_ref' => (string) ($parsed['CompanyRef'] ?? $companyRef), 'fraud_alert' => (string) ($parsed['FraudAlert'] ?? '')];
    }
}