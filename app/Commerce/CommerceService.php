<?php

declare(strict_types=1);

namespace App\Commerce;

use PDO;
use RuntimeException;
use Throwable;

final class CommerceService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createOrder(int $userId, array $cart, array $shipping = []): array
    {
        if (!$cart || count($cart) > 20) throw new RuntimeException('Your cart is empty or contains too many items.');
        $userQuery = $this->pdo->prepare("SELECT name, email FROM users WHERE id = :id AND status = 'active'");
        $userQuery->execute(['id' => $userId]);
        $user = $userQuery->fetch();
        if (!$user) throw new RuntimeException('Your account is not available for checkout.');
        $items = [];
        $seen = [];
        $subtotal = 0;
        $hasPhysical = false;
        foreach ($cart as $entry) {
            $type = (string) ($entry['type'] ?? '');
            $id = filter_var($entry['id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($entry['quantity'] ?? 1, FILTER_VALIDATE_INT);
            if (!in_array($type, ['course', 'product'], true) || !$id || !$quantity || $quantity < 1 || $quantity > 20) throw new RuntimeException('A cart item is invalid.');
            if ($type === 'course' && $quantity !== 1) throw new RuntimeException('A course can only be purchased once per order.');
            $cartKey = $type . ':' . $id;
            if (isset($seen[$cartKey])) throw new RuntimeException('The same item cannot appear more than once in an order.');
            $seen[$cartKey] = true;
            $table = $type === 'course' ? 'cms_courses' : 'cms_products';
            $query = $this->pdo->prepare("SELECT id, title, price_ugx" . ($type === 'product' ? ", product_type, product_file_media_id, read_price_ugx, download_price_ugx" : "") . " FROM {$table} WHERE id = :id AND status = 'published'");
            $query->execute(['id' => $id]);
            $item = $query->fetch();
            if (!$item) throw new RuntimeException('A cart item is no longer available.');
            if ($type === 'course') {
                $access = $this->pdo->prepare("SELECT 1 FROM course_enrolments WHERE user_id = :user_id AND course_id = :course_id AND status IN ('active', 'completed') LIMIT 1");
                $access->execute(['user_id' => $userId, 'course_id' => $id]);
                if ($access->fetchColumn()) throw new RuntimeException('You already have access to this course.');
            }
            $assets = [];
            $entitlement = '';
            if ($type === 'product' && ($item['product_type'] ?? '') === 'physical') {
                $hasPhysical = true;
                $entitlement = 'physical';
            }
            if ($type === 'product' && ($item['product_type'] ?? '') === 'online') {
                $entitlement = (string) ($entry['entitlement'] ?? 'download');
                if (!in_array($entitlement, ['read', 'download'], true)) throw new RuntimeException('Choose read access or a downloadable copy for this digital product.');
            }
            if ($type === 'product' && ($item['product_type'] ?? '') === 'online') {
                $assetQuery = $this->pdo->prepare("SELECT m.id, m.storage_path, m.original_name, m.mime_type, m.file_size, m.checksum_sha256 FROM cms_product_assets pa INNER JOIN media_assets m ON m.id = pa.media_asset_id WHERE pa.product_id = :product_id AND pa.asset_role = 'download' AND m.visibility = 'private' ORDER BY pa.sort_order, pa.id");
                $assetQuery->execute(['product_id' => $id]);
                $assets = $assetQuery->fetchAll();
                if (!empty($item['product_file_media_id']) && !in_array((int) $item['product_file_media_id'], array_map(static fn ($asset) => (int) $asset['id'], $assets), true)) {
                    $legacy = $this->pdo->prepare("SELECT id, storage_path, original_name, mime_type, file_size, checksum_sha256 FROM media_assets WHERE id = :id AND visibility = 'private'");
                    $legacy->execute(['id' => (int) $item['product_file_media_id']]);
                    $legacyAsset = $legacy->fetch();
                    if ($legacyAsset) array_unshift($assets, $legacyAsset);
                }
                $allowedMimes = ['application/pdf', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/rtf', 'text/rtf', 'application/vnd.oasis.opendocument.text', 'application/zip'];
                $assetBytes = 0;
                if (!$assets || count($assets) > 25) throw new RuntimeException('Add at least one private digital file before publishing this product.');
                foreach ($assets as $asset) {
                    if (!in_array($asset['mime_type'], $allowedMimes, true) || (int) $asset['file_size'] < 1) throw new RuntimeException('A digital product contains an unsupported or empty file.');
                    $assetBytes += (int) $asset['file_size'];
                    if ($assetBytes > 262144000) throw new RuntimeException('A digital product cannot exceed 250 MB of downloadable files.');
                }
            }
            $price = $type === 'product' && ($item['product_type'] ?? '') === 'online'
                ? (int) ($entitlement === 'read' ? $item['read_price_ugx'] : $item['download_price_ugx'])
                : (int) $item['price_ugx'];
            if ($price < 0 || $price > 1000000000) throw new RuntimeException('A product price is invalid.');
            $line = $price * $quantity;
            if ($line > 1000000000 || $subtotal > 1000000000 - $line) throw new RuntimeException('Your order total is too large.');
            $subtotal += $line;
            $items[] = ['type' => $type, 'id' => $id, 'title' => (string) $item['title'], 'product_type' => $item['product_type'] ?? null, 'file_id' => $item['product_file_media_id'] ?? null, 'entitlement' => $entitlement, 'assets' => $assets, 'price' => $price, 'quantity' => $quantity, 'line' => $line];
        }
        if ($subtotal < 1) throw new RuntimeException('The order total must be greater than zero.');
        $shippingQuote = null;
        $shippingSnapshot = ['name'=>'','phone'=>'','address'=>'','city'=>'','region'=>'','country'=>'UG'];
        if ($hasPhysical) {
            foreach (['name'=>120,'phone'=>40,'address'=>240,'city'=>100,'region'=>100] as $field=>$limit) {
                $value = trim((string) ($shipping[$field] ?? ''));
                if ($value === '' || strlen($value) > $limit) throw new RuntimeException('Complete the delivery name, phone, street address, city and region.');
                $shippingSnapshot[$field] = $value;
            }
            $shippingSnapshot['country'] = strtoupper(trim((string) ($shipping['country'] ?? 'UG')));
            $shippingQuote = (new ShippingService($this->pdo))->quote((int) ($shipping['method_id'] ?? 0), $shippingSnapshot['country'], $shippingSnapshot['region']);
        }
        $deliveryFee = (int) ($shippingQuote['fee_ugx'] ?? 0);
        if ($deliveryFee < 0 || $subtotal > 1000000000 - $deliveryFee) throw new RuntimeException('Your order total is too large.');
        $total = $subtotal + $deliveryFee;
        $now = gmdate('Y-m-d H:i:s');
        $publicId = 'SSP-' . strtoupper(bin2hex(random_bytes(8)));
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare("INSERT INTO orders (public_id, user_id, status, currency, subtotal_ugx, total_ugx, customer_name, customer_email, created_at, updated_at, shipping_method_id, shipping_method_name, delivery_fee_ugx, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_region, shipping_country, fulfillment_status) VALUES (:public_id, :user_id, 'pending', 'UGX', :subtotal, :total, :name, :email, :created_at, :updated_at, :method_id, :method_name, :delivery_fee, :shipping_name, :shipping_phone, :shipping_address, :shipping_city, :shipping_region, :shipping_country, :fulfillment_status)");
            $insert->execute(['public_id' => $publicId, 'user_id' => $userId, 'subtotal' => $subtotal, 'total' => $total, 'name' => $user['name'], 'email' => $user['email'], 'created_at' => $now, 'updated_at' => $now, 'method_id' => $shippingQuote ? (int) $shippingQuote['id'] : null, 'method_name' => $shippingQuote['name'] ?? '', 'delivery_fee' => $deliveryFee, 'shipping_name' => $shippingSnapshot['name'], 'shipping_phone' => $shippingSnapshot['phone'], 'shipping_address' => $shippingSnapshot['address'], 'shipping_city' => $shippingSnapshot['city'], 'shipping_region' => $shippingSnapshot['region'], 'shipping_country' => $shippingSnapshot['country'], 'fulfillment_status' => $hasPhysical ? 'pending' : 'not_required']);
            $orderId = (int) $this->pdo->lastInsertId();
            $lineInsert = $this->pdo->prepare('INSERT INTO order_items (order_id, item_type, item_id, item_title, product_type, product_file_media_id, unit_price_ugx, quantity, line_total_ugx, product_entitlement, created_at) VALUES (:order_id, :type, :item_id, :title, :product_type, :file_id, :price, :quantity, :line, :entitlement, :created_at)');
            foreach ($items as $item) {
                $lineInsert->execute(['order_id' => $orderId, 'type' => $item['type'], 'item_id' => $item['id'], 'title' => $item['title'], 'product_type' => $item['product_type'], 'file_id' => $item['file_id'], 'price' => $item['price'], 'quantity' => $item['quantity'], 'line' => $item['line'], 'entitlement' => $item['entitlement'], 'created_at' => $now]);
                $orderItemId = (int) $this->pdo->lastInsertId();
                if ($item['assets']) {
                    $assetInsert = $this->pdo->prepare('INSERT INTO order_item_assets (order_item_id, media_asset_id, storage_path, original_name, mime_type, file_size, checksum_sha256, sort_order, created_at) VALUES (:order_item_id, :media_asset_id, :storage_path, :original_name, :mime_type, :file_size, :checksum, :sort_order, :created_at)');
                    foreach ($item['assets'] as $position => $asset) $assetInsert->execute(['order_item_id' => $orderItemId, 'media_asset_id' => (int) $asset['id'], 'storage_path' => $asset['storage_path'], 'original_name' => $asset['original_name'], 'mime_type' => $asset['mime_type'], 'file_size' => (int) $asset['file_size'], 'checksum' => $asset['checksum_sha256'], 'sort_order' => $position, 'created_at' => $now]);
                }
            }
            $this->pdo->commit();
            return $this->order($orderId, $userId) ?? throw new RuntimeException('The order could not be loaded.');
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function order(int $orderId, int $userId): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :user_id');
        $query->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $query->fetch();
        if (!$order) return null;
        $items = $this->pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id');
        $items->execute(['order_id' => $orderId]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    public function createPayment(int $orderId, int $userId, string $provider): array
    {
        if (!in_array($provider, ['paypal', 'dpo'], true)) throw new RuntimeException('Choose a supported payment provider.');
        $this->pdo->exec('BEGIN IMMEDIATE');
        $active = true;
        try {
            $order = $this->order($orderId, $userId);
            if (!$order || $order['status'] !== 'pending') throw new RuntimeException('This order cannot be paid.');
            $existing = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id AND provider = :provider AND status IN ('created', 'pending') ORDER BY id DESC LIMIT 1");
            $existing->execute(['order_id' => $orderId, 'provider' => $provider]);
            $payment = $existing->fetch();
            if ($payment) {
                $this->pdo->exec('COMMIT');
                $active = false;
                return $payment;
            }
            $now = gmdate('Y-m-d H:i:s');
            $insert = $this->pdo->prepare("INSERT INTO payments (order_id, provider, idempotency_key, amount_ugx, currency, status, created_at, updated_at) VALUES (:order_id, :provider, :key, :amount, 'UGX', 'created', :created_at, :updated_at)");
            $insert->execute(['order_id' => $orderId, 'provider' => $provider, 'key' => bin2hex(random_bytes(24)), 'amount' => (int) $order['total_ugx'], 'created_at' => $now, 'updated_at' => $now]);
            $query = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id');
            $query->execute(['id' => (int) $this->pdo->lastInsertId()]);
            $payment = $query->fetch();
            $this->pdo->exec('COMMIT');
            $active = false;
            return $payment;
        } catch (Throwable $exception) {
            if ($active) {
                try {
                    $this->pdo->exec('ROLLBACK');
                } catch (Throwable) {
                }
            }
            throw $exception;
        }
    }

    public function updateProviderReference(int $paymentId, string $providerOrderId, string $providerStatus, array $metadata = []): void
    {
        if ($providerOrderId === '' || strlen($providerOrderId) > 190) throw new RuntimeException('The payment provider returned an invalid reference.');
        $statement = $this->pdo->prepare("UPDATE payments SET provider_order_id = :reference, provider_status = :provider_status, status = 'pending', metadata_json = :metadata, updated_at = :updated_at WHERE id = :id AND status IN ('created', 'pending')");
        $statement->execute(['reference' => $providerOrderId, 'provider_status' => substr($providerStatus, 0, 80), 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $paymentId]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('The payment reference could not be saved.');
    }

    public function confirmPayment(int $paymentId, string $provider, string $providerReference, string $transactionReference, int $amount, string $currency, string $providerStatus, string $eventId): bool
    {
        if ($amount < 1 || $currency !== 'UGX' || $providerStatus !== 'COMPLETED' || $eventId === '' || trim($providerReference) === '' || trim($transactionReference) === '' || strlen($transactionReference) > 190) throw new RuntimeException('The payment confirmation is incomplete.');
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT p.*, o.user_id, o.total_ugx, o.status AS order_status FROM payments p INNER JOIN orders o ON o.id = p.order_id WHERE p.id = :id AND p.provider = :provider');
            $query->execute(['id' => $paymentId, 'provider' => $provider]);
            $payment = $query->fetch();
            if (!$payment || !hash_equals((string) $payment['provider_order_id'], $providerReference) || (int) $payment['amount_ugx'] !== $amount || (int) $payment['total_ugx'] !== $amount || $payment['currency'] !== $currency) throw new RuntimeException('The verified payment does not match this order.');
            if ($payment['status'] === 'paid' && $payment['order_status'] === 'paid') {
                $this->pdo->commit();
                return false;
            }
            if ($payment['order_status'] !== 'pending' || !in_array($payment['status'], ['created', 'pending'], true)) throw new RuntimeException('This order is no longer awaiting payment.');
            $now = gmdate('Y-m-d H:i:s');
            $event = $this->pdo->prepare('INSERT INTO payment_events (provider, provider_event_id, payment_id, payload_hash, provider_verified, processed, received_at, processed_at) VALUES (:provider, :event, :payment_id, :hash, 1, 1, :received, :processed)');
            $event->execute(['provider' => $provider, 'event' => $eventId, 'payment_id' => $paymentId, 'hash' => hash('sha256', $providerReference . '|' . $transactionReference . '|' . $amount . '|' . $currency), 'received' => $now, 'processed' => $now]);
            $update = $this->pdo->prepare("UPDATE payments SET status = 'paid', provider_transaction_id = :transaction_id, provider_status = :provider_status, paid_at = :paid_at, updated_at = :updated_at WHERE id = :id");
            $update->execute(['transaction_id' => $transactionReference, 'provider_status' => $providerStatus, 'paid_at' => $now, 'updated_at' => $now, 'id' => $paymentId]);
            $this->pdo->prepare("UPDATE orders SET status = 'paid', paid_at = :paid_at, updated_at = :updated_at WHERE id = :id AND status = 'pending'")->execute(['paid_at' => $now, 'updated_at' => $now, 'id' => $payment['order_id']]);
            $lines = $this->pdo->prepare('SELECT item_type, item_id FROM order_items WHERE order_id = :order_id');
            $lines->execute(['order_id' => $payment['order_id']]);
            $enrol = $this->pdo->prepare("INSERT INTO course_enrolments (user_id, course_id, status, source, enrolled_at, updated_at) VALUES (:user_id, :course_id, 'active', 'payment', :enrolled_at, :updated_at) ON CONFLICT(user_id, course_id) DO UPDATE SET status = CASE WHEN course_enrolments.status = 'revoked' THEN 'active' ELSE course_enrolments.status END, source = 'payment', updated_at = excluded.updated_at");
            foreach ($lines->fetchAll() as $line) {
                if ($line['item_type'] === 'course') $enrol->execute(['user_id' => $payment['user_id'], 'course_id' => (int) $line['item_id'], 'enrolled_at' => $now, 'updated_at' => $now]);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof \PDOException && str_contains(strtolower($exception->getMessage()), 'unique')) return false;
            throw $exception;
        }
    }

    public function providerPayment(string $provider, string $reference): ?array
    {
        $query = $this->pdo->prepare('SELECT p.*, o.id AS order_id, o.public_id, o.user_id, o.total_ugx, o.currency AS order_currency, o.status AS order_status FROM payments p INNER JOIN orders o ON o.id = p.order_id WHERE p.provider = :provider AND (p.provider_order_id = :reference OR o.public_id = :reference) ORDER BY p.id DESC LIMIT 1');
        $query->execute(['provider' => $provider, 'reference' => $reference]);
        return $query->fetch() ?: null;
    }

    public function history(int $userId): array
    {
        $query = $this->pdo->prepare('SELECT id, public_id, status, currency, total_ugx, created_at, paid_at FROM orders WHERE user_id = :user_id ORDER BY id DESC LIMIT 50');
        $query->execute(['user_id' => $userId]);
        return $query->fetchAll();
    }

    public function purchasedFile(int $userId, int $orderItemId): ?array
    {
        $query = $this->pdo->prepare("SELECT oi.item_title, m.storage_path, m.original_name, m.mime_type, m.file_size FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id INNER JOIN payments p ON p.order_id = o.id AND p.status = 'paid' INNER JOIN media_assets m ON m.id = oi.product_file_media_id WHERE oi.id = :item_id AND oi.item_type = 'product' AND oi.product_type = 'online' AND oi.product_entitlement IN ('download','both') AND o.user_id = :user_id AND o.status IN ('paid', 'partially_refunded') AND COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = oi.id AND r.status = 'succeeded'), 0) < oi.line_total_ugx AND m.visibility = 'private' LIMIT 1");
        $query->execute(['item_id' => $orderItemId, 'user_id' => $userId]);
        return $query->fetch() ?: null;
    }
    public function purchasedFiles(int $userId, int $orderItemId): array
    {
        $query = $this->pdo->prepare("SELECT a.id AS asset_id, a.order_item_id, oi.item_title, a.storage_path, a.original_name, a.mime_type, a.file_size, a.checksum_sha256 FROM order_item_assets a INNER JOIN order_items oi ON oi.id = a.order_item_id INNER JOIN orders o ON o.id = oi.order_id WHERE oi.id = :item_id AND oi.item_type = 'product' AND oi.product_type = 'online' AND oi.product_entitlement IN ('download','both') AND o.user_id = :user_id AND o.status IN ('paid', 'partially_refunded') AND COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = oi.id AND r.status = 'succeeded'), 0) < oi.line_total_ugx AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status = 'paid') ORDER BY a.sort_order, a.id");
        $query->execute(['item_id' => $orderItemId, 'user_id' => $userId]);
        $files = $query->fetchAll();
        if ($files) return $files;
        $legacy = $this->purchasedFile($userId, $orderItemId);
        return $legacy ? [['asset_id' => null] + $legacy] : [];
    }

    public function purchasedReadableFiles(int $userId, int $orderItemId): array
    {
        $query = $this->pdo->prepare("SELECT a.id AS asset_id, a.order_item_id, oi.item_title, a.storage_path, a.original_name, a.mime_type, a.file_size, a.checksum_sha256 FROM order_item_assets a INNER JOIN order_items oi ON oi.id = a.order_item_id INNER JOIN orders o ON o.id = oi.order_id WHERE oi.id = :item_id AND oi.item_type = 'product' AND oi.product_type = 'online' AND oi.product_entitlement IN ('read','both') AND o.user_id = :user_id AND o.status IN ('paid','partially_refunded') AND COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = oi.id AND r.status = 'succeeded'),0) < oi.line_total_ugx AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status = 'paid') ORDER BY a.sort_order,a.id");
        $query->execute(['item_id'=>$orderItemId,'user_id'=>$userId]);
        return $query->fetchAll();
    }

    public function purchasedReadableAsset(int $userId, int $assetId): ?array
    {
        $query = $this->pdo->prepare("SELECT a.id AS asset_id,a.order_item_id,oi.item_title,a.storage_path,a.original_name,a.mime_type,a.file_size,a.checksum_sha256 FROM order_item_assets a INNER JOIN order_items oi ON oi.id=a.order_item_id INNER JOIN orders o ON o.id=oi.order_id WHERE a.id=:asset_id AND oi.item_type='product' AND oi.product_type='online' AND oi.product_entitlement IN ('read','both') AND o.user_id=:user_id AND o.status IN ('paid','partially_refunded') AND COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id=ri.refund_id WHERE ri.order_item_id=oi.id AND r.status='succeeded'),0)<oi.line_total_ugx AND EXISTS(SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='paid') LIMIT 1");
        $query->execute(['asset_id'=>$assetId,'user_id'=>$userId]);
        return $query->fetch() ?: null;
    }

    public function purchasedReadableFile(int $userId, int $orderItemId): ?array
    {
        return $this->purchasedReadableFiles($userId,$orderItemId)[0] ?? null;
    }

    public function purchasedAsset(int $userId, int $assetId): ?array
    {
        $query = $this->pdo->prepare("SELECT a.id AS asset_id, oi.id AS order_item_id, oi.item_title, a.storage_path, a.original_name, a.mime_type, a.file_size, a.checksum_sha256 FROM order_item_assets a INNER JOIN order_items oi ON oi.id = a.order_item_id INNER JOIN orders o ON o.id = oi.order_id WHERE a.id = :asset_id AND oi.item_type = 'product' AND oi.product_type = 'online' AND oi.product_entitlement IN ('download','both') AND o.user_id = :user_id AND o.status IN ('paid', 'partially_refunded') AND COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = oi.id AND r.status = 'succeeded'), 0) < oi.line_total_ugx AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status = 'paid') LIMIT 1");
        $query->execute(['asset_id' => $assetId, 'user_id' => $userId]);
        return $query->fetch() ?: null;
    }

}