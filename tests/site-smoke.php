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
$serverLog = $storage . '/server.log';
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
    'terms.php', 'theme-preference.php', 'verify-certificate.php', 'view.php', 'admin/analytics.php',
    'admin/backups.php', 'admin/cms.php', 'admin/diagnostics.php',
    'admin/media-preview.php', 'admin/orders.php', 'admin/payment-demo-settings.php',
    'admin/product-assets.php', 'admin/refunds.php', 'admin/updates.php'
];

try {
    require $root . '/bootstrap/app.php';

    App\Core\Installer::install($root, 'Smoke Administrator', 'smoke@example.test', 'Smoke-Only-Password-2026!');
    $pdo = App\Core\Database::connect();
    $now = gmdate('Y-m-d H:i:s');
    $course = $pdo->prepare("INSERT INTO cms_courses (slug, title, description_html, content_html, price_ugx, category, status, sort_order, created_at, updated_at) VALUES ('smoke-course', 'Smoke Course', 'Course description', 'Course content', 0, 'Testing', 'published', 1, :now, :now)");
    $course->execute(['now' => $now]);
    $product = $pdo->prepare("INSERT INTO cms_products (slug, title, description_html, price_ugx, category, product_type, status, sort_order, created_at, updated_at) VALUES ('smoke-product', 'Smoke Product', 'Product description', 1000, 'Testing', 'online', 'published', 1, :now, :now)");
    $product->execute(['now' => $now]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) throw new RuntimeException('Could not reserve a local HTTP port: ' . $errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr($address, ':'), 1);
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/index.php'];
    $serverLog = $storage . '/server.log';
    $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']];
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

    $navigationPages = [
        'index.php', 'about.php', 'accessibility.php', 'achievements.php', 'assistant.php',
        'cart.php', 'certificates.php', 'checkout.php', 'contact.php', 'cookies.php',
        'course-details.php', 'courses.php', 'eula.php', 'learning.php', 'legal.php',
        'login.php', 'notifications.php', 'orders.php', 'payment-choice.php',
        'payment-return.php', 'privacy.php', 'product-details.php', 'products.php',
        'profile.php', 'refunds.php', 'register.php', 'reset.php', 'terms.php'
    ];
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
            if ($status === 200 && in_array($page, $navigationPages, true)) {
                if (!str_contains($body, '<base href="/">')) {
                    $failures[] = $page . ': shared root URL base is missing; nested links and assets can resolve against the current route';
                }
                foreach (['id="navmenu"', 'id="theme-toggle"', 'assets/js/main.js', 'assets/css/device-responsive.css'] as $sharedFeature) {
                    if (!str_contains($body, $sharedFeature)) {
                        $failures[] = $page . ': shared site shell is missing ' . $sharedFeature;
                    }
                }
            }
            if (!preg_match('/<meta\s+name=["\']viewport["\']/i', $body)) {
                $failures[] = $page . ': HTML page is missing its responsive viewport declaration';
            }
            preg_match_all('/(?:href|src)=["\']([^"\']+)["\']/i', $body, $matches);
            foreach ($matches[1] as $url) {
                if ($url === '' || str_starts_with($url, '//') || preg_match('/^(?:[a-z][a-z0-9+.-]*:|#)/i', $url)) continue;
                $path = parse_url($url, PHP_URL_PATH);
                if (!is_string($path) || $path === '' || !preg_match('/\.(?:css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf)$/i', $path)) continue;
                $relative = ltrim(rawurldecode($path), '/');
                $hasRootBase = str_contains($body, '<base href="/">');
                $base = str_starts_with($path, '/') || $hasRootBase ? $root : $root . '/' . dirname($page);
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

    $cleanRoutes = [
        '/' => [200],
        '/about' => [200],
        '/admin' => [303],
        '/assistant' => [303],
        '/cart' => [303],
        '/courses' => [200],
        '/courses/no-such-course-for-smoke-test' => [404],
        '/courses/smoke-course' => [200],
        '/install' => [404],
        '/learn' => [303],
        '/learning' => [301],
        '/study' => [301],
        '/course-details' => [301],
        '/product-details' => [301],
        '/login' => [200],
        '/notifications' => [303],
        '/privacy' => [200],
        '/products' => [200],
        '/products/no-such-product-for-smoke-test' => [404],
        '/products/smoke-product' => [200],
        '/profile' => [303],
        '/register' => [200],
        '/favicon.ico' => [200],
        '/unknown-smoke-route' => [404],
        '/.env' => [404],
        '/app/Core/Config.php' => [404],
        '/nav.php' => [404],
    ];
    foreach ($cleanRoutes as $route => $expectedStatuses) {
        $routeBody = @file_get_contents($baseUrl . ltrim($route, '/'), false, stream_context_create([
            'http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]
        ]));
        $routeHeaders = $http_response_header ?? [];
        $routeStatus = 0;
        if (isset($routeHeaders[0]) && preg_match('/\\s([1-5][0-9]{2})\\s/', $routeHeaders[0], $match)) $routeStatus = (int) $match[1];
        if (!in_array($routeStatus, $expectedStatuses, true)) {
            $serverOutput = is_file($serverLog) ? trim((string) file_get_contents($serverLog)) : '';
            $failures[] = $route . ': expected HTTP ' . implode(' or ', $expectedStatuses) . ', received ' . $routeStatus . ($serverOutput !== '' ? '; server output: ' . substr($serverOutput, -1600) : '');
            continue;
        }
        $routeBody = is_string($routeBody) ? $routeBody : '';
        if (in_array($route, ['/courses/smoke-course', '/products/smoke-product'], true)) {
            if (!str_contains($routeBody, '<base href="/">')) $failures[] = $route . ': nested detail page is missing its root asset base';
            if (preg_match('/(?:href|src|action)=["\'][^"\']*\\\\\\\\/', $routeBody)) $failures[] = $route . ': a rendered URL contains Windows backslashes';
            if (str_contains($routeBody, 'http://courses/')) $failures[] = $route . ': an internal link was emitted as a hostname instead of a local route';
        }
        foreach (['Fatal error:', 'Parse error:', 'Uncaught Error', 'Uncaught TypeError'] as $fatal) {
            if (str_contains($routeBody, $fatal)) {
                $failures[] = $route . ': response contains ' . $fatal;
                break;
            }
        }
        $checked++;
    }

    if ($failures) {
        $serverOutput = is_file($serverLog) ? (string) file_get_contents($serverLog) : '';
        $diagnostics = $serverOutput !== '' ? "\nPHP server output:\n" . substr($serverOutput, -6000) : '';
        throw new RuntimeException("Page smoke failures:\n- " . implode("\n- ", $failures) . $diagnostics);
    }

    echo 'PASS unauthenticated HTTP smoke checks for ' . $checked . ' application pages' . PHP_EOL;
    echo 'PASS unified public navigation, theme toggle and responsive shell on ' . count($navigationPages) . ' pages' . PHP_EOL;
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
