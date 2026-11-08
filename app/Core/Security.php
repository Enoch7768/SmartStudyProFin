<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function sendHeaders(): void
    {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        header("Content-Security-Policy-Report-Only: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self' https:; img-src 'self' data: blob: https:; font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' https:; media-src 'self' blob: https:; frame-src 'self' https:");
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        if (self::secureRequest()) header('Strict-Transport-Security: max-age=31536000');
    }

    public static function csrfToken(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        Session::start();
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals((string) $_SESSION['_csrf'], $token);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function redirect(string $path): never
    {
        $path = trim($path);
        $path = Url::resolve($path);
        header('Location: ' . $path, true, 303);
        exit;
    }

    private static function secureRequest(): bool
    {
        $appUrl = strtolower((string) Config::get('app.url', ''));
        return str_starts_with($appUrl, 'https://') || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    }
}
