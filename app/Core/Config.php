<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $values = [];

    public static function load(string $root): void
    {
        $env = [];
        $file = $root . '/.env';
        if (is_file($file) && is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim(trim($value), "\"'");
            }
        }
        $local = [];
        $localFile = $root . '/config/local.php';
        if (is_file($localFile)) {
            $loaded = require $localFile;
            $local = is_array($loaded) ? $loaded : [];
        }
        $read = static function (string $key, mixed $default = null) use ($env): mixed {
            $value = getenv($key);
            return $value !== false ? $value : ($env[$key] ?? $default);
        };
        $storage = (string) $read('APP_STORAGE_PATH', $local['storage_path'] ?? dirname($root) . '/smartstudypro-storage');
        $database = (string) $read('DB_PATH', $local['database_path'] ?? $storage . '/smartstudypro.sqlite');
        $keyFile = $storage . '/app.key';
        $storedKey = is_file($keyFile) && is_readable($keyFile) ? trim((string) file_get_contents($keyFile)) : '';
        self::$values = [
            'root' => $root,
            'app' => [
                'name' => (string) $read('APP_NAME', 'SmartStudyPro'),
                'url' => rtrim((string) $read('APP_URL', ''), '/'),
                'timezone' => (string) $read('APP_TIMEZONE', 'Africa/Kampala'),
                'key' => (string) $read('APP_KEY', $local['app_key'] ?? $storedKey),
                'environment' => (string) $read('APP_ENV', 'production'),
                'debug' => filter_var($read('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
            ],
            'mail' => ['from' => (string) $read('APP_MAIL_FROM', 'no-reply@smartstudypro.com')],
            'ai' => ['gemini_api_key' => (string) $read('GEMINI_API_KEY', ''), 'gemini_model' => (string) $read('GEMINI_MODEL', 'gemini-2.5-flash')],
            'payments' => [
                'paypal_mode' => (string) $read('PAYPAL_MODE', 'sandbox'),
                'paypal_client_id' => (string) $read('PAYPAL_CLIENT_ID', ''),
                'paypal_secret' => (string) $read('PAYPAL_SECRET', ''),
                'paypal_ugx_per_usd' => max(1, (int) $read('PAYPAL_UGX_PER_USD', 3700)),
                'paypal_webhook_id' => (string) $read('PAYPAL_WEBHOOK_ID', ''),
                'dpo_company_token' => (string) $read('DPO_COMPANY_TOKEN', ''),
                'dpo_endpoint' => (string) $read('DPO_ENDPOINT', 'https://secure.3gdirectpay.com/API/v6/'),
                'dpo_checkout_url' => (string) $read('DPO_CHECKOUT_URL', 'https://secure.3gdirectpay.com/payv2.php'),
                'dpo_service_type' => (string) $read('DPO_SERVICE_TYPE', '5525'),
                'demo_allowed' => filter_var($read('DEMO_PAYMENTS_ALLOWED', 'false'), FILTER_VALIDATE_BOOL),
            ],
            'storage' => ['path' => $storage],
            'database' => ['path' => $database],
        ];
        date_default_timezone_set(self::$values['app']['timezone']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value;
    }

    public static function all(): array
    {
        return self::$values;
    }
}
