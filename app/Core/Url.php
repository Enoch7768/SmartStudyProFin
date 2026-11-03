<?php

declare(strict_types=1);

namespace App\Core;

final class Url
{
    public static function basePath(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $directory = dirname($scriptName);
        return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
    }

    public static function to(string $path = '/'): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || str_starts_with($path, '//')) {
            return self::basePath() . '/';
        }

        return self::basePath() . '/' . ltrim($path, '/');
    }
}
