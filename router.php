<?php

declare(strict_types=1);

$root = __DIR__;
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = '/' . trim(rawurldecode(is_string($requestPath) ? $requestPath : '/'), '/');
$path = preg_replace('#/+#', '/', $path) ?: '/';

if ($path === '/' || is_file($root . $path)) {
    return false;
}

$routes = [
    '/about' => 'about.php',
    '/admin' => 'admin.php',
    '/booking' => 'booking.php',
    '/cart' => 'cart.php',
    '/checkout' => 'checkout.php',
    '/contact' => 'contact.php',
    '/courses' => 'courses.php',
    '/login' => 'login.php',
    '/logout' => 'logout.php',
    '/learn' => 'learning.php',
    '/products' => 'products.php',
    '/profile' => 'profile.php',
    '/privacy' => 'privacy.php',
    '/register' => 'register.php',
    '/reset' => 'reset.php',
    '/terms' => 'terms.php',
    '/cookies' => 'cookies.php',
    '/accessibility' => 'accessibility.php',
    '/eula' => 'eula.php',
    '/refunds' => 'refunds.php',
    '/stream' => 'stream.php',
    '/favicon.ico' => 'favicon.php',
    '/cart/add' => 'cart-add.php',
];

$script = $routes[$path] ?? null;

if ($script === null && preg_match('#^/courses/([a-z0-9][a-z0-9-]*)/?$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $script = 'course-details.php';
}

if ($script === null && preg_match('#^/products/([a-z0-9][a-z0-9-]*)/?$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $script = 'product-details.php';
}

if ($script === null && preg_match('#^/learn/([a-z0-9][a-z0-9-]*)(?:/([0-9]+))?/?$#i', $path, $matches)) {
    $_GET['course_slug'] = $matches[1];
    if (isset($matches[2]) && $matches[2] !== '') $_GET['lesson_id'] = $matches[2];
    $script = 'study.php';
}

if ($script === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Page not found';
    return true;
}

$scriptPath = $root . DIRECTORY_SEPARATOR . $script;

if (!is_file($scriptPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Page not found';
    return true;
}

$_SERVER['SCRIPT_NAME'] = '/' . $script;
$_SERVER['PHP_SELF'] = '/' . $script;
$_SERVER['SCRIPT_FILENAME'] = $scriptPath;

require $scriptPath;
return true;
