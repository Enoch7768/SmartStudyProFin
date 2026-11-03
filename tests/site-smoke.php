<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$storage = sys_get_temp_dir() . '/ssp-page-smoke-' . bin2hex(random_bytes(8));
$database = $storage . '/smartstudypro.sqlite';
$key = bin2hex(random_bytes(32));

if (!mkdir($storage, 0700, true)) {
    throw new RuntimeException('Could not create isolated page-smoke storage.');
}

putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_PATH=' . $database);
putenv('APP_KEY=' . $key);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');

$server = null;
$pages = [
    'index.php', 'about.php', 'accessibility.php', 'achievements.php', 'admin.php',
    'assistant.php', 'booking.php', 'cart.php', 'cart-add.php', 'certificates.php',
    'checkout.php', 'contact.php', 'contact-process.php', 'cookies.php',
    'course-details.php', 'courses.php', 'download.php', 'dpo-webhook.php',
    'eula.php', 'favicon.php', 'forms/contact.php', 'forms/newsletter.php',
    'google-auth.php', 'grade-quiz.php', 'install/index.php', 'learning.php',
    'legal-consent.php', 'legal.php', 'lesson-media.php', 'login.php', 'logout.php',
    'media.php', 'notifications.php', 'orders.php', 'payment-choice.php',
    'payment-demo.php', 'payment-return.php', 'payment-start.php',
    'payment-webhook.php', 'privacy.php', 'process_payment.php',
    'product-details.php', 'products.php', 'profile.php', 'receipt.php',
    'refunds.php', 'register.php', 'reset.php', 'stream.php', 'study.php',
    'terms.php', 'verify-certificate.php', 'view.php', 'admin/analytics.php',
    'admin/backups.php', 'admin/cms.php', 'admin/diagnostics.php',
    'admin/media-preview.php', 'admin/orders.php', 'admin/payment-demo-settings.php',
    'admin/product-assets.php', 'admin/refunds.php', 'admin/updates.php'
];

try {
    require $root . '/bootstrap/app.php';

    App\Core\Installer::install($root, 'Smoke Administrator', 'smoke@example.test', 'Smoke-Only-Password-2026!');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) throw new RuntimeException('Could not reserve a local HTTP port: ' . $errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr($address, ':'), 1);
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']];
    $server = proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($server)) throw new RuntimeException('Could not start the isolated PHP page server.');
    fclose($pipes[0]);

    $baseUrl = 'http://127.0.0.1:' . $port . '/';
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $headers = @get_headers($baseUrl . 'login.php');
        if (is_array($headers)) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('The isolated PHP page server did not become ready.');

    $checked = 0;
    $assetChecks = 0;
    $failures = [];
    foreach ($pages as $page) {
        if (!is_file($root . '/' . $page)) {
            $failures[] = $page . ': page file is missing';
            continue;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 10,
                'follow_location' => 0,
                'header' => "Accept: text/html,application/json\r\n"
            ]
        ]);
        $body = @file_get_contents($baseUrl . $page . '?__page_smoke=1', false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('/\s([1-5][0-9]{2})\s/', $responseHeaders[0], $match)) {
            $status = (int) $match[1];
        }
        if ($status === 0 || $status >= 500) {
            $failures[] = $page . ': unexpected HTTP status ' . $status;
            continue;
        }
        $body = is_string($body) ? $body : '';
        foreach (['Fatal error:', 'Parse error:', 'Uncaught Error', 'Uncaught TypeError'] as $fatal) {
            if (str_contains($body, $fatal)) {
                $failures[] = $page . ': response contains ' . $fatal;
                break;
            }
        }
        if (str_contains(strtolower($body), '<html')) {
            if (!preg_match('/<meta\s+name=["\']viewport["\']/i', $body)) {
                $failures[] = $page . ': HTML page is missing its responsive viewport declaration';
            }
            preg_match_all('/(?:href|src)=["\']([^"\']+)["\']/i', $body, $matches);
            foreach ($matches[1] as $url) {
                if ($url === '' || str_starts_with($url, '//') || preg_match('/^(?:[a-z][a-z0-9+.-]*:|#)/i', $url)) continue;
                $path = parse_url($url, PHP_URL_PATH);
                if (!is_string($path) || $path === '' || !preg_match('/\.(?:css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf)$/i', $path)) continue;
                $relative = ltrim(rawurldecode($path), '/');
                $base = str_starts_with($path, '/') ? $root : $root . '/' . dirname($page);
                $candidate = realpath($base . '/' . $relative);
                if ($candidate === false || !str_starts_with($candidate, realpath($root) . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
                    $failures[] = $page . ': missing local asset ' . $url;
                    continue;
                }
                $assetChecks++;
            }
        }
        $checked++;
    }

    if ($failures) {
        throw new RuntimeException("Page smoke failures:\n- " . implode("\n- ", $failures));
    }

    echo 'PASS unauthenticated HTTP smoke checks for ' . $checked . ' application pages' . PHP_EOL;
    echo 'PASS local CSS, JavaScript, image and font references (' . $assetChecks . ' checks)' . PHP_EOL;
    echo 'PASS isolated SQLite installation and administrator bootstrap' . PHP_EOL;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) $remove($entry->getPathname());
        @rmdir($path);
    };
    $remove($storage);
}
