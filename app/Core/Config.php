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
            'mail' => ['from' => (string) $read('APP_MAIL_FROM', 'no-reply@smartstudypro.com')],
                'debug' => filter_var($read('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
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
