<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Cms\ContentRepository;
use App\Cms\PublicContent;
use App\Commerce\CommerceService;
use App\Commerce\RefundService;
use App\Core\Database;
use App\Core\Migrator;

$temporary = sys_get_temp_dir() . '/ssp-commerce-phase5-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create isolated commerce storage.');

try {
    $pdo = Database::connect($temporary . '/commerce.sqlite');
    Migrator::run($pdo, dirname(__DIR__) . '/database/migrations');
    $now = gmdate('Y-m-d H:i:s');
    $insertUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :hash, 'active', :created_at, :updated_at)");
    $insertUser->execute(['name' => 'Commerce Admin', 'email' => 'commerce-admin@example.test', 'hash' => password_hash('commerce-test-password', PASSWORD_DEFAULT), 'created_at' => $now, 'updated_at' => $now]);
    $adminId = (int) $pdo->lastInsertId();
    $insertUser->execute(['name' => 'Other Student', 'email' => 'other-student@example.test', 'hash' => password_hash('other-test-password', PASSWORD_DEFAULT), 'created_at' => $now, 'updated_at' => $now]);
    $otherUserId = (int) $pdo->lastInsertId();

    $media = $pdo->prepare('INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES (:path, :name, :mime, :size, :checksum, :alt, :visibility, :user_id, :created_at)');
    $assets = [];
    foreach ([
        ['media/phase5/guide.pdf', 'guide.pdf', 'application/pdf', 'private'],
        ['media/phase5/notes.txt', 'notes.txt', 'text/plain', 'private'],
        ['media/phase5/cover.png', 'cover.png', 'image/png', 'public']
    ] as $index => $asset) {
        $media->execute(['path' => $asset[0], 'name' => $asset[1], 'mime' => $asset[2], 'size' => 1024 + $index, 'checksum' => hash('sha256', $asset[1]), 'alt' => $asset[1], 'visibility' => $asset[3], 'user_id' => $adminId, 'created_at' => $now]);
        $assets[] = (int) $pdo->lastInsertId();
    }

    $content = new ContentRepository($pdo);
    $productId = $content->save('products', null, ['title' => 'Phase Five Study Pack', 'description_html' => 'A collection of study resources.', 'price_ugx' => '10000', 'category' => 'Study materials', 'product_type' => 'online', 'status' => 'published']);
    $attach = $pdo->prepare('INSERT INTO cms_product_assets (product_id, media_asset_id, asset_role, sort_order, created_at) VALUES (:product_id, :media_id, :role, :sort_order, :created_at)');
    $attach->execute(['product_id' => $productId, 'media_id' => $assets[0], 'role' => 'download', 'sort_order' => 0, 'created_at' => $now]);
    $attach->execute(['product_id' => $productId, 'media_id' => $assets[1], 'role' => 'download', 'sort_order' => 1, 'created_at' => $now]);
    $attach->execute(['product_id' => $productId, 'media_id' => $assets[2], 'role' => 'gallery', 'sort_order' => 0, 'created_at' => $now]);

    $public = new PublicContent($pdo);
    $product = $public->product((string) $productId);
    if (count($product['Gallery'] ?? []) !== 1 || ($product['Gallery'][0]['url'] ?? '') !== 'media.php?id=' . $assets[2]) throw new RuntimeException('The product gallery did not expose only the attached public image.');

    $commerce = new CommerceService($pdo);
    $order = $commerce->createOrder($adminId, [['type' => 'product', 'id' => $productId, 'quantity' => 1]]);
    $itemId = (int) $order['items'][0]['id'];
    if ((int) $order['total_ugx'] !== 10000 || (int) $pdo->query('SELECT COUNT(*) FROM order_item_assets WHERE order_item_id = ' . $itemId)->fetchColumn() !== 2) throw new RuntimeException('The order did not snapshot every private product asset.');
    $payment = $commerce->createPayment((int) $order['id'], $adminId, 'dpo');
    $samePayment = $commerce->createPayment((int) $order['id'], $adminId, 'dpo');
    if ((int) $payment['id'] !== (int) $samePayment['id']) throw new RuntimeException('Repeated payment creation did not reuse the active idempotent payment.');
    $commerce->updateProviderReference((int) $payment['id'], 'phase5-dpo-token', 'CREATED', ['company_ref' => $order['public_id']]);
    if (!$commerce->confirmPayment((int) $payment['id'], 'dpo', 'phase5-dpo-token', 'phase5-approval', 10000, 'UGX', 'COMPLETED', 'phase5-event')) throw new RuntimeException('A verified payment did not complete the purchase.');

    $files = $commerce->purchasedFiles($adminId, $itemId);
    if (count($files) !== 2 || $files[0]['original_name'] !== 'guide.pdf' || $files[1]['original_name'] !== 'notes.txt') throw new RuntimeException('Purchased files were not returned in their configured order.');
    if (!$commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) || $commerce->purchasedAsset($otherUserId, (int) $files[0]['asset_id']) !== null) throw new RuntimeException('A private purchase asset escaped order ownership checks.');

    $refunds = new RefundService($pdo);
    $firstRefund = $refunds->request((int) $order['id'], $adminId, 4000, 'Customer requested a partial refund.');
    $overRefundRejected = false;
    try {
        $refunds->request((int) $order['id'], $adminId, 6001, 'This must exceed the remaining balance.');
    } catch (RuntimeException) {
        $overRefundRejected = true;
    }
    if (!$overRefundRejected) throw new RuntimeException('Concurrent or cumulative refund requests exceeded the captured amount.');
    $refunds->resolve($firstRefund, $adminId, 'succeeded', 'DPO-REFUND-001', 'Confirmed in the DPO merchant portal.');
    $partial = $commerce->order((int) $order['id'], $adminId);
    if (($partial['status'] ?? '') !== 'partially_refunded' || $commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) === null) throw new RuntimeException('A partial refund did not preserve the remaining paid purchase entitlement.');

    $secondRefund = $refunds->request((int) $order['id'], $adminId, 6000, 'Remaining balance refunded.');
    $refunds->resolve($secondRefund, $adminId, 'succeeded', 'DPO-REFUND-002', 'Full balance confirmed in the DPO merchant portal.');
    $finalOrder = $commerce->order((int) $order['id'], $adminId);
    $finalPayment = $commerce->providerPayment('dpo', 'phase5-dpo-token');
    if (($finalOrder['status'] ?? '') !== 'refunded' || ($finalPayment['status'] ?? '') !== 'refunded') throw new RuntimeException('A fully refunded order and payment did not reach refunded status.');
    if ($commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) !== null) throw new RuntimeException('A fully refunded order retained private download access.');
    if (count($refunds->auditTrail($firstRefund)) !== 2 || count($refunds->auditTrail($secondRefund)) !== 2) throw new RuntimeException('Refund requests and provider-confirmed resolutions were not fully audited.');

    echo "PASS multi-file product snapshots and secure owner-bound delivery\n";
    echo "PASS public product gallery separation\n";
    echo "PASS idempotent active payment creation and verified payment completion\n";
    echo "PASS cumulative refund limits and partial/full refund state transitions\n";
    echo "PASS full refund revokes private purchase access and preserves audit history\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    @rmdir($temporary);
}
