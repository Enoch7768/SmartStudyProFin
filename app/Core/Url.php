<?php

declare(strict_types=1);

namespace App\Core;

final class Url
{
    public static function basePath(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $scriptName)) {
            $scriptPath = parse_url($scriptName, PHP_URL_PATH);
        } else {
            $scriptPath = $scriptName;
        }

        if (!is_string($scriptPath) || $scriptPath === '') $scriptPath = '/index.php';
        $scriptPath = '/' . ltrim(preg_replace('#/+#', '/', $scriptPath) ?: '/index.php', '/');
        $directory = dirname($scriptPath);
        if (in_array(strtolower(basename($directory)), ['admin', 'forms', 'install'], true)) {
            $directory = dirname($directory);
        }

        return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
    }

    public static function applicationBasePath(): string
    {
        return self::basePath();
    }

    public static function documentBase(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $scriptPath = preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $scriptName)
            ? (string) parse_url($scriptName, PHP_URL_PATH)
            : $scriptName;
        $scriptPath = '/' . ltrim(preg_replace('#/+#', '/', (string) $scriptPath) ?: '/index.php', '/');
        $directory = dirname($scriptPath);
        $baseDirectory = self::basePath();
        if (in_array(strtolower(basename($directory)), ['admin', 'forms', 'install'], true)) {
            $baseDirectory .= '/' . basename($directory);
        }

        return rtrim($baseDirectory, '/') . '/';
    }

    public static function resolve(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || preg_match('/[\r\n\\\\]/', $path)) {
            return self::to('/');
        }

        if (str_starts_with($path, '/')) {
            $basePath = self::basePath();
            $pathname = (string) parse_url($path, PHP_URL_PATH);
            if ($basePath !== '' && ($pathname === $basePath || str_starts_with($pathname, $basePath . '/'))) return $path;
            return self::to($path);
        }

        $parts = preg_split('/([?#])/', $path, 2, PREG_SPLIT_DELIM_CAPTURE);
        $pathname = (string) ($parts[0] ?? '');
        $suffix = isset($parts[1]) ? $parts[1] . ($parts[2] ?? '') : '';
        $rootSegments = array_values(array_filter(explode('/', self::basePath()), static fn (string $segment): bool => $segment !== ''));
        $segments = $rootSegments;
        $documentSegments = array_values(array_filter(explode('/', self::documentBase()), static fn (string $segment): bool => $segment !== ''));
        foreach (array_slice($documentSegments, count($rootSegments)) as $segment) $segments[] = $segment;
        foreach (explode('/', $pathname) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                if (count($segments) > count($rootSegments)) array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments) . $suffix;
    }

    public static function to(string $path = '/'): string
    {
        $path = trim($path);
        if ($path === '') {
            return self::basePath() . '/';
        }

        // Already a full absolute URL – leave it alone
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $parts = preg_split('/([?#])/', $path, 2, PREG_SPLIT_DELIM_CAPTURE);
        $pathname = str_replace('\\', '/', (string) ($parts[0] ?? ''));
        $pathname = preg_replace('#/+#', '/', $pathname) ?: '/';
        $suffix = isset($parts[1]) ? $parts[1] . ($parts[2] ?? '') : '';

        // After normalizing backslashes, anything that still looks protocol-relative
        // (started with // or \\ ) is treated as an external-host attempt and rejected.
        if (str_starts_with($pathname, '//')) {
            return self::basePath() . '/';
        }

        if ($pathname === '' || $pathname === '/') {
            return self::basePath() . '/' . $suffix;
        }

        // Reject anything that looks like a scheme
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $pathname)) {
            return self::basePath() . '/';
        }

        $result = self::basePath() . '/' . ltrim($pathname, '/') . $suffix;

        // Final safety net: never emit a protocol-relative result
        if (str_starts_with($result, '//')) {
            $result = '/' . ltrim($result, '/');
        }

        return $result;
    }

    /**
     * Build an absolute URL suitable for Open Graph, schema.org, emails and redirects.
     * Prefers APP_URL from Config; falls back to the current request host + base path.
     */
    public static function absolute(string $path = '/'): string
    {
        $configured = '';
        if (class_exists(Config::class)) {
            $configured = rtrim((string) Config::get('app.url', ''), '/');
        }

        if ($configured !== '') {
            $relative = self::to($path);
            $basePath = self::basePath();
            // Strip the installation base path so we do not double-prefix when APP_URL already includes it.
            if ($basePath !== '' && str_starts_with($relative, $basePath)) {
                $relative = substr($relative, strlen($basePath)) ?: '/';
            }
            return $configured . ($relative === '/' ? '' : $relative);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . self::to($path);
    }

    /** Convenience: the canonical site origin (no trailing path). */
    public static function site(): string
    {
        return rtrim(self::absolute('/'), '/');
    }
}
