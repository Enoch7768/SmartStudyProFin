<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Commerce\DemoPaymentService;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Forders.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('You do not have permission to manage demo payments.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Invalid demo payment settings request.');
}
$enabled = ($_POST['enabled'] ?? '0') === '1';
try {
    (new DemoPaymentService($pdo))->setEnabled($enabled, (int) $user['id']);
    Security::redirect('orders.php?demo_settings=' . ($enabled ? 'enabled' : 'disabled'));
} catch (Throwable $exception) {
    http_response_code(400);
    exit(Security::escape($exception instanceof RuntimeException ? $exception->getMessage() : 'Demo payment settings could not be updated.'));
}
