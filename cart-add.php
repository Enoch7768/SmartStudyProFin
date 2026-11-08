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
$columns = $type === 'product' ? 'id, slug, price_ugx, product_type, stock_quantity, stock_reserved' : 'id, slug, price_ugx';
$statement = $pdo->prepare("SELECT {$columns} FROM {$table} WHERE id = :id AND status = 'published' LIMIT 1");
$statement->execute(['id' => $id]);
$item = $statement->fetch();
if (!$item) {
    http_response_code(404);
    exit('This item is no longer available.');
}
if ($type === 'product' && ($item['product_type'] ?? '') === 'physical' && $item['stock_quantity'] !== null && ((int)$item['stock_quantity'] - (int)$item['stock_reserved']) < 1) {
    http_response_code(409);
    exit('This physical product is currently out of stock.');
}

$offeringId = filter_var($_POST['offering_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if ($type === 'course') {
    $learning = new LearningService($pdo);
    if ($offeringId > 0) {
        $offeringQuery = $pdo->prepare("SELECT id,mode,title,price_ugx,capacity FROM course_offerings WHERE id=:id AND course_id=:course AND is_published=1");
        $offeringQuery->execute(['id'=>$offeringId,'course'=>$id]);
        $offering = $offeringQuery->fetch();
        if (!$offering) { http_response_code(404); exit('This course option is no longer available.'); }
        if ((int)$offering['price_ugx'] === 0) {
            if (!$user) Security::redirect(Url::to('/login?redirect=' . rawurlencode(Url::to('/courses/' . rawurlencode((string)$item['slug'])))));
            if ($offering['mode'] === 'self_paced') {
                $learning->enrolFreeCourse((int)$user['id'],$id);
            } else {
                $pdo->exec('BEGIN IMMEDIATE');
                try {
                    $count=$pdo->prepare("SELECT COUNT(*) FROM course_offering_enrolments WHERE offering_id=:offering AND status IN ('active','completed')");
                    $count->execute(['offering'=>$offeringId]);
                    if ($offering['capacity'] !== null && (int)$count->fetchColumn() >= (int)$offering['capacity']) throw new RuntimeException('This course option is full.');
                    $now=gmdate('Y-m-d H:i:s');
                    $enrol=$pdo->prepare("INSERT INTO course_offering_enrolments(user_id,offering_id,source,status,created_at,updated_at) VALUES(:user,:offering,'free','active',:now,:now) ON CONFLICT(user_id,offering_id) DO UPDATE SET status='active',updated_at=excluded.updated_at");
                    $enrol->execute(['user'=>(int)$user['id'],'offering'=>$offeringId,'now'=>$now]);
                    $pdo->exec('COMMIT');
                } catch (Throwable $exception) { try{$pdo->exec('ROLLBACK');}catch(Throwable){} throw $exception; }
            }
            Security::redirect(Url::to($offering['mode']==='self_paced' ? '/learn/' . rawurlencode((string)$item['slug']) : '/meetings'));
        }
    } else {
        $published = $pdo->prepare('SELECT 1 FROM course_offerings WHERE course_id=:course AND is_published=1 LIMIT 1');
        $published->execute(['course'=>$id]);
        if ($published->fetchColumn()) { http_response_code(400); exit('Choose one of the published course delivery options.'); }
        if ($user && $learning->hasAccess((int)$user['id'],$id)) Security::redirect(Url::to('/learn/' . rawurlencode((string)$item['slug'])));
        if ((int)$item['price_ugx']===0) {
            $destination=Url::to('/learn/' . rawurlencode((string)$item['slug']));
            if (!$user) Security::redirect(Url::to('/login?redirect=' . rawurlencode($destination)));
            $learning->enrolFreeCourse((int)$user['id'],$id);
            Security::redirect($destination);
        }
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
$key = $type . ':' . $id . ($type === 'course' && $offeringId > 0 ? ':offering:' . $offeringId : ($entitlement !== '' ? ':' . $entitlement : ''));
$_SESSION['ssp_cart'][$key] = ['type' => $type, 'id' => $id, 'quantity' => 1, 'entitlement' => $entitlement, 'offering_id' => $offeringId ?: null];
Security::redirect(Url::to('/cart'));
