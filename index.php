<?php

declare(strict_types=1);

$root = __DIR__;
$rootPath = realpath($root) ?: $root;
require_once $root . '/app/Core/Url.php';
$basePath = PHP_SAPI === 'cli-server' ? '' : \App\Core\Url::basePath();
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$path = '/' . trim(preg_replace('#/+#', '/', $requestPath) ?: '/', '/');
if ($path !== '/') $path = rtrim($path, '/');

if (preg_match('#^/install(?:/|$)#i', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Page not found');
}

if (
    preg_match('#^/(?:app|bootstrap|config|database|scripts|storage|cms|docs|tests|vendor|\.git|\.github)(?:/|$)#i', $path)
    || preg_match('#(?:^|/)\.(?!well-known(?:/|$))#i', $path)
    || preg_match('/\.(?:sqlite|sqlite3|db|log|key|sql|bak|backup|old|orig|swp|zip|tar|gz|dump|sqlite-wal|sqlite-shm|db-wal|db-shm)$/i', $path)
    || in_array($path, ['/composer.json', '/composer.lock', '/phpunit.xml', '/nav.php', '/auth.php'], true)
) {
    http_response_code(404);
    exit('Page not found');
}

if (PHP_SAPI === 'cli-server' && $path !== '/' && $path !== '/index.php' && $path !== '/favicon.ico') {
    $requestedFile = realpath($root . $path);
    if ($requestedFile !== false && str_starts_with($requestedFile, $rootPath . DIRECTORY_SEPARATOR) && is_file($requestedFile)) {
        if (strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) === 'php') {
            $requestedUrl = $basePath . $path;
            $_SERVER['SCRIPT_NAME'] = $requestedUrl;
            $_SERVER['PHP_SELF'] = $requestedUrl;
            $_SERVER['SCRIPT_FILENAME'] = $requestedFile;
            require $requestedFile;
            return;
        }
        return false;
    }
}

$aliases = [
    '/learning' => '/learn',
    '/study' => '/learn',
    '/course-details' => '/courses',
    '/product-details' => '/products',
];

if (isset($aliases[$path]) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    $destination = $basePath . $aliases[$path];
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') $destination .= '?' . $query;
    header('Location: ' . $destination, true, 301);
    exit;
}

$routes = [
    '/about' => 'about.php',
    '/accessibility' => 'accessibility.php',
    '/achievements' => 'achievements.php',
    '/admin' => 'admin.php',
    '/assistant' => 'assistant.php',
    '/booking' => 'booking.php',
    '/cart' => 'cart.php',
    '/cart/add' => 'cart-add.php',
    '/certificates' => 'certificates.php',
    '/checkout' => 'checkout.php',
    '/contact' => 'contact.php',
    '/cookies' => 'cookies.php',
    '/courses' => 'courses.php',
    '/download' => 'download.php',
    '/eula' => 'eula.php',
    '/favicon.ico' => 'favicon.php',
    '/legal' => 'legal.php',
    '/legal-consent' => 'legal-consent.php',
    '/login' => 'login.php',
    '/logout' => 'logout.php',
    '/learn' => 'learning.php',
    '/media' => 'media.php',
    '/notifications' => 'notifications.php',
    '/orders' => 'orders.php',
    '/payment-choice' => 'payment-choice.php',
    '/payment-demo' => 'payment-demo.php',
    '/payment-return' => 'payment-return.php',
    '/payment-start' => 'payment-start.php',
    '/privacy' => 'privacy.php',
    '/products' => 'products.php',
    '/profile' => 'profile.php',
    '/receipt' => 'receipt.php',
    '/refunds' => 'refunds.php',
    '/register' => 'register.php',
    '/reset' => 'reset.php',
    '/stream' => 'stream.php',
    '/terms' => 'terms.php',
    '/theme-preference' => 'theme-preference.php',
    '/verify-certificate' => 'verify-certificate.php',
    '/view' => 'view.php',
];

$script = $routes[$path] ?? null;


if ($script === null && preg_match('#^/courses/([a-z0-9][a-z0-9-]*)$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $script = 'course-details.php';
}

if ($script === null && preg_match('#^/products/([a-z0-9][a-z0-9-]*)$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $script = 'product-details.php';
}

if ($script === null && preg_match('#^/learn/([a-z0-9][a-z0-9-]*)(?:/([0-9]+))?$#i', $path, $matches)) {
    $_GET['course_slug'] = $matches[1];
    if (isset($matches[2])) $_GET['lesson_id'] = $matches[2];
    $script = 'study.php';
}

if ($script !== null) {
    $target = $root . DIRECTORY_SEPARATOR . $script;
    if (!is_file($target)) {
        http_response_code(404);
        exit('Page not found');
    }

    $targetUrl = $basePath . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $script);
    $_SERVER['SCRIPT_NAME'] = $targetUrl;
    $_SERVER['PHP_SELF'] = $targetUrl;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    require $target;
    return;
}

if ($path !== '/' && $path !== '/index.php') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Page not found');
}

$_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;


require_once __DIR__ . '/bootstrap/app.php';

$pdo = \App\Core\Database::connect();
$content = new \App\Cms\PublicContent($pdo);
$home = $content->home();
$courses = $content->courses();
$contact = $content->contact();

$phone = $contact['phone'];
$email = $contact['email'];
$address = strip_tags($contact['address']);

$seoTitle = $home['SEO-Title'] ?? ($home['Hero-Title'] ?: 'SmartStudyPro | Study Made Simple, Success Made Sure');
$seoDescription = $home['SEO-Description'] ?? 'Empowering students in Uganda with innovative learning solutions, professional courses, and academic guidance.';
$siteUrl = \App\Core\Url::site();
?>
