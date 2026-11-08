<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Url;
use App\Core\Session;
use App\Learning\LearningService;

Session::start();
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Your session expired. Refresh the page and try again.');
}

$pdo = Database::connect();
$user = Auth::user($pdo);

$type = (string) ($_POST['type'] ?? '');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if (!in_array($type, ['course', 'product'], true) || $id < 1) {
    http_response_code(400);
    exit('The selected item is invalid.');
}

$table = $type === 'course' ? 'cms_courses' : 'cms_products';
$columns = $type === 'product' ? 'id, slug, price_ugx, product_type' : 'id, slug, price_ugx';
$statement = $pdo->prepare("SELECT {$columns} FROM {$table} WHERE id = :id AND status = 'published' LIMIT 1");
$statement->execute(['id' => $id]);
$item = $statement->fetch();
if (!$item) {
    http_response_code(404);
    exit('This item is no longer available.');
}

if ($type === 'course') {
    $learning = new LearningService($pdo);
    if ($user && $learning->hasAccess((int) $user['id'], $id)) Security::redirect(Url::to('/learn/' . rawurlencode((string) $item['slug'])));
    if ((int) $item['price_ugx'] === 0) {
        $destination = Url::to('/learn/' . rawurlencode((string) $item['slug']));
        if (!$user) Security::redirect(Url::to('/login?redirect=' . rawurlencode($destination)));
        $learning->enrolFreeCourse((int) $user['id'], $id);
        Security::redirect($destination);
    }
}

$entitlement = '';
if ($type === 'product') {
    $entitlement = ($item['product_type'] ?? '') === 'online' ? (string) ($_POST['entitlement'] ?? 'download') : 'physical';
    if (($item['product_type'] ?? '') === 'online' && !in_array($entitlement, ['read','download'], true)) {
        http_response_code(400);
        exit('Choose a valid digital access option.');
    }
}
$key = $type . ':' . $id . ($entitlement !== '' ? ':' . $entitlement : '');
$_SESSION['ssp_cart'][$key] = ['type' => $type, 'id' => $id, 'quantity' => 1, 'entitlement' => $entitlement];
Security::redirect(Url::to('/cart'));
