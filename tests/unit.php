<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Router;
use App\Core\Security;
use App\Core\Session;

$token = Security::csrfToken();
if (!Security::verifyCsrf($token) || Security::verifyCsrf('invalid-token')) throw new RuntimeException('CSRF token validation failed.');
if (Security::escape('<script>') !== '&lt;script&gt;') throw new RuntimeException('HTML escaping failed.');
$router = new Router();
$router->get('/health', static fn (): string => 'ok');
if ($router->dispatch('GET', '/health?check=1') !== 'ok') throw new RuntimeException('GET route dispatch failed.');
$router->dispatch('POST', '/health');
if (http_response_code() !== 404) throw new RuntimeException('Unknown route did not return 404.');
$duplicateRejected = false;
try {
    $router->get('/health', static fn (): string => 'duplicate');
} catch (RuntimeException) {
    $duplicateRejected = true;
}
if (!$duplicateRejected) throw new RuntimeException('Duplicate routes were accepted.');
Session::destroy();
echo "PASS CSRF token validation\nPASS HTML escaping\nPASS route dispatch and query stripping\nPASS unknown route status\nPASS duplicate route rejection\n";
