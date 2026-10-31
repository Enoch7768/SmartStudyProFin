<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Commerce\DemoPaymentService;
use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;

$root = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/ssp-demo-' . bin2hex(random_bytes(6));
mkdir($temp, 0700, true);
$previousEnv = getenv('APP_ENV');
$previousAllowed = getenv('DEMO_PAYMENTS_ALLOWED');
try {
    putenv('APP_ENV=staging');
    putenv('DEMO_PAYMENTS_ALLOWED=true');
    Config::load($root);
    $pdo = Database::connect($temp . '/demo.sqlite');
    Migrator::run($pdo, $root . '/database/migrations');
    $now = gmdate('Y-m-d H:i:s');
    $user = $pdo->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES ('Demo Student', 'demo@example.test', :hash, 'active', :now, :now)");
    $user->execute(['hash' => password_hash('demo-password', PASSWORD_DEFAULT), 'now' => $now]);
    $userId = (int) $pdo->lastInsertId();
    $course = $pdo->prepare("INSERT INTO cms_courses (title, slug, description_html, content_html, price_ugx, category, status, sort_order, created_at, updated_at) VALUES ('Demo Course', 'demo-course', 'Demo', 'Content', 15000, 'Testing', 'published', 1, :now, :now)");
    $course->execute(['now' => $now]);
    $courseId = (int) $pdo->lastInsertId();
    $service = new DemoPaymentService($pdo);
    if ($service->enabled()) throw new RuntimeException('Demo mode must start disabled.');
    $service->setEnabled(true, $userId);
    if (!$service->enabled()) throw new RuntimeException('Administrator enable did not persist.');
    $makeOrder = static function (string $itemType, int $itemId) use ($pdo, $userId, $now): int {
        $publicId = 'TEST-' . strtoupper(bin2hex(random_bytes(5)));
        $insert = $pdo->prepare("INSERT INTO orders (public_id, user_id, status, currency, subtotal_ugx, total_ugx, customer_name, customer_email, created_at, updated_at) VALUES (:public_id, :user_id, 'pending', 'UGX', 15000, 15000, 'Demo Student', 'demo@example.test', :now, :now)");
        $insert->execute(['public_id' => $publicId, 'user_id' => $userId, 'now' => $now]);
        $orderId = (int) $pdo->lastInsertId();
        $line = $pdo->prepare('INSERT INTO order_items (order_id, item_type, item_id, item_title, unit_price_ugx, quantity, line_total_ugx, created_at) VALUES (:order_id, :type, :item_id, :title, 15000, 1, 15000, :now)');
        $line->execute(['order_id' => $orderId, 'type' => $itemType, 'item_id' => $itemId, 'title' => 'Demo item', 'now' => $now]);
        return $orderId;
    };
    $failedId = $makeOrder('course', $courseId);
    $service->simulate($failedId, $userId, 'failed');
    if ($pdo->query("SELECT status FROM orders WHERE id = $failedId")->fetchColumn() !== 'pending') throw new RuntimeException('Failed simulation changed order status.');
    $successId = $makeOrder('course', $courseId);
    $service->simulate($successId, $userId, 'success');
    if ($pdo->query("SELECT status FROM orders WHERE id = $successId")->fetchColumn() !== 'paid') throw new RuntimeException('Successful simulation did not pay order.');
    $access = $pdo->prepare("SELECT 1 FROM course_enrolments WHERE user_id = :user_id AND course_id = :course_id AND status = 'active'");
    $access->execute(['user_id' => $userId, 'course_id' => $courseId]);
    if (!$access->fetchColumn()) throw new RuntimeException('Successful simulation did not grant course access.');
    $cancelId = $makeOrder('product', 1);
    $service->simulate($cancelId, $userId, 'cancelled');
    if ($pdo->query("SELECT status FROM orders WHERE id = $cancelId")->fetchColumn() !== 'cancelled') throw new RuntimeException('Cancelled simulation did not cancel order.');
    $service->setEnabled(false, $userId);
    if ($service->enabled()) throw new RuntimeException('Disable setting did not persist.');
    putenv('APP_ENV=production');
    Config::load($root);
    if ($service->enabled()) throw new RuntimeException('Production exposed demo mode.');
    $blocked = false;
    try { $service->simulate($failedId, $userId, 'success'); } catch (RuntimeException) { $blocked = true; }
    if (!$blocked) throw new RuntimeException('Production accepted a simulated payment.');
    echo "PASS demo mode defaults disabled and requires administrator toggle\nPASS simulated success grants access only to the demo order\nPASS failed and cancelled scenarios\nPASS production environment hard block\n";
} finally {
    $previousEnv === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $previousEnv);
    $previousAllowed === false ? putenv('DEMO_PAYMENTS_ALLOWED') : putenv('DEMO_PAYMENTS_ALLOWED=' . $previousAllowed);
    foreach (glob($temp . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    @rmdir($temp);
}
