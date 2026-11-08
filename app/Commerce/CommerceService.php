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
            $cartKey = $type . ':' . $id . ($type === 'product' ? ':' . (string) ($entry['entitlement'] ?? 'download') : '');
            if (isset($seen[$cartKey])) throw new RuntimeException('The same item and access option cannot appear more than once in an order.');
            $seen[$cartKey] = true;
            $table = $type === 'course' ? 'cms_courses' : 'cms_products';
            $query = $this->pdo->prepare("SELECT id, title, price_ugx" . ($type === 'product' ? ", product_type, product_file_media_id, read_price_ugx, download_price_ugx, stock_quantity, stock_reserved" : "") . " FROM {$table} WHERE id = :id AND status = 'published'");
            $query->execute(['id' => $id]);
            $item = $query->fetch();
            if (!$item) throw new RuntimeException('A cart item is no longer available.');
            $courseOffering = null;
            if ($type === 'course') {
                $offeringId = filter_var($entry['offering_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                if ($offeringId > 0) {
                    $offeringQuery = $this->pdo->prepare("SELECT o.id,o.mode,o.title,o.price_ugx,o.capacity FROM course_offerings o INNER JOIN cms_courses c ON c.id=o.course_id WHERE o.id=:offering AND o.course_id=:course AND o.is_published=1 AND c.status='published' LIMIT 1");
                    $offeringQuery->execute(['offering'=>$offeringId,'course'=>$id]);
                    $courseOffering = $offeringQuery->fetch() ?: null;
                    if (!$courseOffering) throw new RuntimeException('That course delivery option is no longer available.');
                    if ($courseOffering['mode'] === 'self_paced') {
                        $access = $this->pdo->prepare("SELECT 1 FROM course_enrolments WHERE user_id=:user_id AND course_id=:course_id AND status IN ('active','completed') LIMIT 1");
                        $access->execute(['user_id'=>$userId,'course_id'=>$id]);
                        if ($access->fetchColumn()) throw new RuntimeException('You already have access to this self-paced course.');
                    } else {
                        $access = $this->pdo->prepare("SELECT 1 FROM course_offering_enrolments WHERE user_id=:user_id AND offering_id=:offering AND status IN ('active','completed') LIMIT 1");
                        $access->execute(['user_id'=>$userId,'offering'=>$offeringId]);
                        if ($access->fetchColumn()) throw new RuntimeException('You already have access to this course offering.');
                    }
                } else {
                    $published = $this->pdo->prepare('SELECT 1 FROM course_offerings WHERE course_id=:course AND is_published=1 LIMIT 1');
                    $published->execute(['course'=>$id]);
                    if ($published->fetchColumn()) throw new RuntimeException('Choose one of the published course delivery options before checkout.');
                    $access = $this->pdo->prepare("SELECT 1 FROM course_enrolments WHERE user_id=:user_id AND course_id=:course_id AND status IN ('active','completed') LIMIT 1");
                    $access->execute(['user_id'=>$userId,'course_id'=>$id]);
                    if ($access->fetchColumn()) throw new RuntimeException('You already have access to this course.');
                }
            }
            $assets = [];
            $entitlement = 'download';
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
            $price = $type === 'course' && $courseOffering
                ? (int) $courseOffering['price_ugx']
                : ($type === 'product' && ($item['product_type'] ?? '') === 'online'
                    ? (int) ($entitlement === 'read' ? $item['read_price_ugx'] : $item['download_price_ugx'])
                    : (int) $item['price_ugx']);
            if ($price < 0 || $price > 1000000000) throw new RuntimeException('A product price is invalid.');
            $line = $price * $quantity;
            if ($line > 1000000000 || $subtotal > 1000000000 - $line) throw new RuntimeException('Your order total is too large.');
            $subtotal += $line;
            $items[] = ['type' => $type, 'id' => $id, 'title' => (string) $item['title'] . ($courseOffering ? ' · ' . $courseOffering['title'] : ''), 'product_type' => $item['product_type'] ?? null, 'file_id' => $item['product_file_media_id'] ?? null, 'entitlement' => $entitlement, 'offering_id' => $courseOffering ? (int) $courseOffering['id'] : null, 'offering_mode' => $courseOffering['mode'] ?? 'legacy', 'offering_title' => $courseOffering['title'] ?? '', 'offering_capacity' => $courseOffering['capacity'] ?? null, 'stock_quantity' => $item['stock_quantity'] ?? null, 'stock_reserved' => (int)($item['stock_reserved'] ?? 0), 'assets' => $assets, 'price' => $price, 'quantity' => $quantity, 'line' => $line];
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
            $lineInsert = $this->pdo->prepare('INSERT INTO order_items (order_id, item_type, item_id, item_title, product_type, product_file_media_id, unit_price_ugx, quantity, line_total_ugx, product_entitlement, course_offering_id, course_offering_mode, course_offering_title, created_at) VALUES (:order_id, :type, :item_id, :title, :product_type, :file_id, :price, :quantity, :line, :entitlement, :offering_id, :offering_mode, :offering_title, :created_at)');
            foreach ($items as $item) {
                $lineInsert->execute(['order_id' => $orderId, 'type' => $item['type'], 'item_id' => $item['id'], 'title' => $item['title'], 'product_type' => $item['product_type'], 'file_id' => $item['file_id'], 'price' => $item['price'], 'quantity' => $item['quantity'], 'line' => $item['line'], 'entitlement' => $item['entitlement'], 'offering_id' => $item['offering_id'], 'offering_mode' => $item['offering_mode'], 'offering_title' => $item['offering_title'], 'created_at' => $now]);
                $orderItemId = (int) $this->pdo->lastInsertId();
                if ($item['type'] === 'product' && $item['product_type'] === 'physical') {
                    if ($item['stock_quantity'] !== null) {
                        $reserveStock = $this->pdo->prepare('UPDATE cms_products SET stock_reserved=stock_reserved+:add_quantity WHERE id=:product_id AND stock_quantity IS NOT NULL AND stock_quantity-stock_reserved>=:required_quantity');
                        $reserveStock->execute(['add_quantity'=>$item['quantity'],'required_quantity'=>$item['quantity'],'product_id'=>$item['id']]);
                        if ($reserveStock->rowCount() !== 1) throw new RuntimeException('This physical product does not have enough stock available.');
                    }
                    $reservation = $this->pdo->prepare("INSERT INTO product_stock_reservations(order_item_id,product_id,quantity,status,created_at,updated_at) VALUES(:item,:product,:quantity,'reserved',:now,:now)");
                    $reservation->execute(['item'=>$orderItemId,'product'=>$item['id'],'quantity'=>$item['quantity'],'now'=>$now]);
                }
                if ($item['offering_id'] !== null) {
                    $sameUser = $this->pdo->prepare("SELECT 1 FROM orders o INNER JOIN order_items oi ON oi.order_id=o.id WHERE o.user_id=:user AND o.status='pending' AND oi.course_offering_id=:offering AND o.id!=:order_id AND datetime(o.created_at)>=datetime('now','-1 hour') LIMIT 1");
                    $sameUser->execute(['user'=>$userId,'offering'=>$item['offering_id'],'order_id'=>$orderId]);
                    if ($sameUser->fetchColumn()) throw new RuntimeException('You already have a pending checkout for this course offering.');
                    $capacity = $item['offering_capacity'];
                    if ($capacity !== null) {
                        $occupied = $this->pdo->prepare("SELECT (SELECT COUNT(*) FROM course_offering_enrolments WHERE offering_id=:offering AND status IN ('active','completed')) + (SELECT COUNT(*) FROM order_items oi INNER JOIN orders o ON o.id=oi.order_id WHERE oi.course_offering_id=:offering AND o.status='pending' AND datetime(o.created_at)>=datetime('now','-1 hour') AND o.id!=:order_id)");
                        $occupied->execute(['offering'=>$item['offering_id'],'order_id'=>$orderId]);
                        if ((int)$occupied->fetchColumn() >= (int)$capacity) throw new RuntimeException('This course offering is currently full. Please choose another option.');
                    }
                }
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

    public function cancelPendingOrder(int $orderId, ?int $userId = null): void
    {
        if ($orderId < 1) throw new RuntimeException('Choose a valid order to cancel.');
        $this->pdo->beginTransaction();
        try {
            $query=$this->pdo->prepare('SELECT id,user_id,status FROM orders WHERE id=:id');
            $query->execute(['id'=>$orderId]);
            $order=$query->fetch();
            if (!$order || $order['status']!=='pending' || ($userId !== null && (int)$order['user_id'] !== $userId)) throw new RuntimeException('Only your own pending order can be cancelled.');
            $now=gmdate('Y-m-d H:i:s');
            $reservations=$this->pdo->prepare("SELECT r.*,p.stock_quantity FROM product_stock_reservations r INNER JOIN cms_products p ON p.id=r.product_id INNER JOIN order_items oi ON oi.id=r.order_item_id WHERE oi.order_id=:order_id AND r.status='reserved'");
            $reservations->execute(['order_id'=>$orderId]);
            foreach($reservations->fetchAll() as $reservation) {
                if($reservation['stock_quantity'] !== null) {
                    $release=$this->pdo->prepare('UPDATE cms_products SET stock_reserved=stock_reserved-:release WHERE id=:product_id AND stock_reserved>=:required');
                    $release->execute(['release'=>(int)$reservation['quantity'],'required'=>(int)$reservation['quantity'],'product_id'=>(int)$reservation['product_id']]);
                    if($release->rowCount()!==1) throw new RuntimeException('Reserved product stock could not be released safely.');
                }
                $this->pdo->prepare("UPDATE product_stock_reservations SET status='released',updated_at=:now WHERE id=:id AND status='reserved'")->execute(['now'=>$now,'id'=>(int)$reservation['id']]);
            }
            $this->pdo->prepare("UPDATE payments SET status='cancelled',updated_at=:now WHERE order_id=:order_id AND status IN ('created','pending')")->execute(['now'=>$now,'order_id'=>$orderId]);
            $this->pdo->prepare("UPDATE orders SET status='cancelled',updated_at=:now WHERE id=:order_id AND status='pending'")->execute(['now'=>$now,'order_id'=>$orderId]);
            $this->pdo->commit();
        } catch(Throwable $exception) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
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
            $lines = $this->pdo->prepare('SELECT id AS order_item_id,item_type,item_id,course_offering_id,course_offering_mode FROM order_items WHERE order_id = :order_id');
            $lines->execute(['order_id' => $payment['order_id']]);
            $stockReservations = $this->pdo->prepare("SELECT r.*,p.stock_quantity FROM product_stock_reservations r INNER JOIN cms_products p ON p.id=r.product_id INNER JOIN order_items oi ON oi.id=r.order_item_id WHERE oi.order_id=:order_id AND r.status='reserved'");
            $stockReservations->execute(['order_id'=>$payment['order_id']]);
            foreach ($stockReservations->fetchAll() as $reservation) {
                if ($reservation['stock_quantity'] !== null) {
                    $commitStock = $this->pdo->prepare('UPDATE cms_products SET stock_quantity=stock_quantity-:decrement,stock_reserved=stock_reserved-:release WHERE id=:product_id AND stock_quantity>=:available AND stock_reserved>=:reserved');
                    $commitStock->execute(['decrement'=>(int)$reservation['quantity'],'release'=>(int)$reservation['quantity'],'available'=>(int)$reservation['quantity'],'reserved'=>(int)$reservation['quantity'],'product_id'=>(int)$reservation['product_id']]);
                    if ($commitStock->rowCount() !== 1) throw new RuntimeException('Reserved product stock could not be committed. Administrator reconciliation is required.');
                }
                $this->pdo->prepare("UPDATE product_stock_reservations SET status='committed',updated_at=:now WHERE id=:id AND status='reserved'")->execute(['now'=>$now,'id'=>(int)$reservation['id']]);
            }
            $enrol = $this->pdo->prepare("INSERT INTO course_enrolments (user_id, course_id, status, source, enrolled_at, updated_at) VALUES (:user_id, :course_id, 'active', 'payment', :enrolled_at, :updated_at) ON CONFLICT(user_id, course_id) DO UPDATE SET status = CASE WHEN course_enrolments.status = 'revoked' THEN 'active' ELSE course_enrolments.status END, source = 'payment', updated_at = excluded.updated_at");
            $offeringEnrol = $this->pdo->prepare("INSERT INTO course_offering_enrolments (user_id,offering_id,order_item_id,source,status,created_at,updated_at) VALUES (:user_id,:offering_id,:order_item_id,'payment','active',:created_at,:updated_at) ON CONFLICT(user_id,offering_id) DO UPDATE SET order_item_id=excluded.order_item_id,source='payment',status='active',updated_at=excluded.updated_at");
            foreach ($lines->fetchAll() as $line) {
                if ($line['item_type'] !== 'course') continue;
                if (empty($line['course_offering_id']) || $line['course_offering_mode'] === 'legacy' || $line['course_offering_mode'] === 'self_paced') {
                    $enrol->execute(['user_id' => $payment['user_id'], 'course_id' => (int) $line['item_id'], 'enrolled_at' => $now, 'updated_at' => $now]);
                } else {
                    $offeringEnrol->execute(['user_id'=>(int)$payment['user_id'],'offering_id'=>(int)$line['course_offering_id'],'order_item_id'=>(int)$line['order_item_id'],'created_at'=>$now,'updated_at'=>$now]);
                }
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