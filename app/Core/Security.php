<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
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
        if ($path === '' || str_starts_with($path, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || preg_match('/[\r\n\\\\]/', $path)) $path = '/';
        header('Location: ' . $path, true, 303);
        exit;
    }
}
