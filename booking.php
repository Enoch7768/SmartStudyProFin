<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Session;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=courses.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Invalid cart request. Refresh the page and try again.');
}
$type = isset($_POST['course_id']) ? 'course' : (isset($_POST['product_id']) ? 'product' : '');
$id = filter_var($_POST[$type . '_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
if (!in_array($type, ['course', 'product'], true) || !$id || !$quantity || $quantity < 1 || $quantity > 20) {
    http_response_code(422);
    exit('The selected item is invalid.');
}
$table = $type === 'course' ? 'cms_courses' : 'cms_products';
$query = $pdo->prepare("SELECT id FROM {$table} WHERE id = :id AND status = 'published'");
$query->execute(['id' => $id]);
if (!$query->fetchColumn()) {
    http_response_code(404);
    exit('This item is no longer available.');
}
if ($type === 'course') $quantity = 1;
Session::start();
$_SESSION['ssp_cart'] ??= [];
$key = $type . ':' . $id;
$_SESSION['ssp_cart'][$key] = ['type' => $type, 'id' => $id, 'quantity' => min(20, (int) ($_SESSION['ssp_cart'][$key]['quantity'] ?? 0) + $quantity)];
Security::redirect('cart.php');
