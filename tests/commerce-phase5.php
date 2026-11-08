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
use App\Commerce\ShippingService;
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
    $courseId = $content->save('courses', null, ['title' => 'Refundable Course', 'description_html' => 'Paid course access test.', 'content_html' => 'Course content.', 'price_ugx' => '10000', 'category' => 'Testing', 'status' => 'published']);
    $productId = $content->save('products', null, ['title' => 'Phase Five Study Pack', 'description_html' => 'A collection of study resources.', 'price_ugx' => '10000', 'read_price_ugx' => '6000', 'download_price_ugx' => '10000', 'category' => 'Study materials', 'product_type' => 'online', 'status' => 'published']);
    $attach = $pdo->prepare('INSERT INTO cms_product_assets (product_id, media_asset_id, asset_role, sort_order, created_at) VALUES (:product_id, :media_id, :role, :sort_order, :created_at)');
    $attach->execute(['product_id' => $productId, 'media_id' => $assets[0], 'role' => 'download', 'sort_order' => 0, 'created_at' => $now]);
    $attach->execute(['product_id' => $productId, 'media_id' => $assets[1], 'role' => 'download', 'sort_order' => 1, 'created_at' => $now]);
    $attach->execute(['product_id' => $productId, 'media_id' => $assets[2], 'role' => 'gallery', 'sort_order' => 0, 'created_at' => $now]);

    $public = new PublicContent($pdo);
    $product = $public->product((string) $productId);
    if (count($product['Gallery'] ?? []) !== 1 || ($product['Gallery'][0]['url'] ?? '') !== 'media.php?id=' . $assets[2]) throw new RuntimeException('The product gallery did not expose only the attached public image.');
    if (count($product['Files'] ?? []) !== 2 || isset($product['Files'][0]['url'])) throw new RuntimeException('The product page did not expose safe file descriptions without private download URLs.');

    $commerce = new CommerceService($pdo);
    $offeringCourseId = $content->save('courses', null, ['title'=>'Live Chemistry Programme','description_html'=>'Live course offering checkout.','content_html'=>'','price_ugx'=>'0','category'=>'Science','status'=>'published']);
    $meetingPlanner = new App\Learning\MeetingPlannerService($pdo);
    $liveOfferingId = $meetingPlanner->saveOffering($offeringCourseId,'live_online','Live Chemistry Cohort','Weekly live sessions',15000,1,true);
    $offeringOrder = $commerce->createOrder($adminId,[['type'=>'course','id'=>$offeringCourseId,'offering_id'=>$liveOfferingId,'quantity'=>1]]);
    if ((int)$offeringOrder['total_ugx']!==15000 || (int)$offeringOrder['items'][0]['course_offering_id']!==$liveOfferingId || $offeringOrder['items'][0]['course_offering_mode']!=='live_online') throw new RuntimeException('Course checkout did not use the selected published offering price and delivery mode.');
    $offeringPayment=$commerce->createPayment((int)$offeringOrder['id'],$adminId,'dpo');
    $commerce->updateProviderReference((int)$offeringPayment['id'],'phase3-offering-token','CREATED');
    if(!$commerce->confirmPayment((int)$offeringPayment['id'],'dpo','phase3-offering-token','phase3-offering-tx',15000,'UGX','COMPLETED','phase3-offering-event')) throw new RuntimeException('Paid course offering did not confirm.');
    $offeringEnrolment=$pdo->prepare("SELECT status FROM course_offering_enrolments WHERE user_id=:user AND offering_id=:offering");
    $offeringEnrolment->execute(['user'=>$adminId,'offering'=>$liveOfferingId]);
    if($offeringEnrolment->fetchColumn()!=='active') throw new RuntimeException('Live course purchase did not grant offering-specific enrolment.');
    $capacityRejected=false;
    try{$commerce->createOrder($otherUserId,[['type'=>'course','id'=>$offeringCourseId,'offering_id'=>$liveOfferingId,'quantity'=>1]]);}catch(RuntimeException){$capacityRejected=true;}
    if(!$capacityRejected) throw new RuntimeException('A full course offering accepted another pending checkout.');
    $offeringItemId=(int)$offeringOrder['items'][0]['id'];
    $offeringRefund=(new RefundService($pdo))->request($offeringItemId,$adminId,15000,'Course offering refund.');
    (new RefundService($pdo))->resolve($offeringRefund,$adminId,'succeeded','DPO-OFFERING-REFUND','Live course offering refund confirmed.');
    $offeringEnrolment->execute(['user'=>$adminId,'offering'=>$liveOfferingId]);
    if($offeringEnrolment->fetchColumn()!=='revoked') throw new RuntimeException('Refunding a live course purchase did not revoke its specific offering access.');
    $inPersonCourseId=$content->save('courses',null,['title'=>'In-person Workshop Course','description_html'=>'','content_html'=>'','price_ugx'=>'0','category'=>'Testing','status'=>'published']);
    $inPersonOfferingId=$meetingPlanner->saveOffering($inPersonCourseId,'in_person','Kampala Workshop','',12000,5,true);
    $inPersonOrder=$commerce->createOrder($adminId,[['type'=>'course','id'=>$inPersonCourseId,'offering_id'=>$inPersonOfferingId,'quantity'=>1]]);
    $inPersonPayment=$commerce->createPayment((int)$inPersonOrder['id'],$adminId,'dpo');
    $commerce->updateProviderReference((int)$inPersonPayment['id'],'phase3-inperson-token','CREATED');
    if(!$commerce->confirmPayment((int)$inPersonPayment['id'],'dpo','phase3-inperson-token','phase3-inperson-tx',12000,'UGX','COMPLETED','phase3-inperson-event')) throw new RuntimeException('In-person course purchase did not confirm.');
    $inPersonRefundRejected=false;
    try{(new RefundService($pdo))->request((int)$inPersonOrder['items'][0]['id'],$adminId,12000,'In-person fee must not use the online refund ledger.');}catch(RuntimeException){$inPersonRefundRejected=true;}
    if(!$inPersonRefundRejected) throw new RuntimeException('The online refund ledger accepted an in-person course fee.');
    $shipping = new ShippingService($pdo);
    $zoneId = $shipping->saveZone(null, 'Kampala delivery', 'UG', 'Kampala', true);
    $methodId = $shipping->saveMethod(null, $zoneId, 'Standard delivery', 'Door-to-door delivery', 2500, 1, 3, true);
    $physicalProductId = $content->save('products', null, ['title' => 'Printed Study Workbook', 'description_html' => 'Physical workbook.', 'price_ugx' => '7000', 'category' => 'Books', 'product_type' => 'physical', 'stock_quantity' => '2', 'status' => 'published']);
    $regionRejected = false;
    try { $shipping->quote($methodId, 'UG', 'Wakiso'); } catch (RuntimeException) { $regionRejected = true; }
    if (!$regionRejected) throw new RuntimeException('A district-specific delivery method was offered outside its configured region.');
    $delivery = ['method_id'=>$methodId,'name'=>'Test Recipient','phone'=>'+256700000001','address'=>'Plot 10 Test Road','city'=>'Kampala','region'=>'Kampala','country'=>'UG'];
    $physicalOrder = $commerce->createOrder($adminId, [['type'=>'product','id'=>$physicalProductId,'quantity'=>1]], $delivery);
    if ((int)$physicalOrder['subtotal_ugx'] !== 7000 || (int)$physicalOrder['delivery_fee_ugx'] !== 2500 || (int)$physicalOrder['total_ugx'] !== 9500 || $physicalOrder['fulfillment_status'] !== 'pending') throw new RuntimeException('Physical checkout did not snapshot the server-calculated delivery price and recipient details.');
    $stockState=$pdo->query('SELECT stock_quantity,stock_reserved FROM cms_products WHERE id='.(int)$physicalProductId)->fetch();
    if ((int)$stockState['stock_quantity']!==2 || (int)$stockState['stock_reserved']!==1) throw new RuntimeException('Physical checkout did not reserve finite inventory.');
    $mixedOrder = $commerce->createOrder($adminId, [['type'=>'product','id'=>$productId,'quantity'=>1],['type'=>'product','id'=>$physicalProductId,'quantity'=>1]], $delivery);
    if ((int)$mixedOrder['total_ugx'] !== 19500 || (int)$mixedOrder['delivery_fee_ugx'] !== 2500) throw new RuntimeException('A mixed cart did not apply delivery once to its physical items only.');
    $stockState=$pdo->query('SELECT stock_reserved FROM cms_products WHERE id='.(int)$physicalProductId)->fetchColumn();
    if ((int)$stockState!==2) throw new RuntimeException('Mixed checkout did not reserve the additional physical quantity.');
    $digitalOnlyOrder = $commerce->createOrder($adminId, [['type'=>'product','id'=>$productId,'quantity'=>1]]);
    if ((int)$digitalOnlyOrder['delivery_fee_ugx'] !== 0 || $digitalOnlyOrder['shipping_method_id'] !== null) throw new RuntimeException('A digital-only order was charged delivery.');
    $physicalPayment = $commerce->createPayment((int)$physicalOrder['id'], $adminId, 'dpo');
    $commerce->updateProviderReference((int)$physicalPayment['id'], 'phase2-physical-token', 'CREATED');
    if (!$commerce->confirmPayment((int)$physicalPayment['id'], 'dpo', 'phase2-physical-token', 'phase2-physical-tx', 9500, 'UGX', 'COMPLETED', 'phase2-physical-event')) throw new RuntimeException('Physical order payment did not complete.');
    $stockState=$pdo->query('SELECT stock_quantity,stock_reserved FROM cms_products WHERE id='.(int)$physicalProductId)->fetch();
    if ((int)$stockState['stock_quantity']!==1 || (int)$stockState['stock_reserved']!==1) throw new RuntimeException('Paid physical checkout did not commit its stock reservation.');
    $physicalItemId = (int)$physicalOrder['items'][0]['id'];
    $physicalRefundRejected = false;
    try { (new RefundService($pdo))->request($physicalItemId, $adminId, 1000, 'Physical goods are not refundable through this online refund ledger.'); } catch (RuntimeException) { $physicalRefundRejected = true; }
    if (!$physicalRefundRejected) throw new RuntimeException('The online refund ledger accepted a physical product.');
    $commerce->cancelPendingOrder((int)$mixedOrder['id'],$adminId);
    $stockState=$pdo->query('SELECT stock_quantity,stock_reserved FROM cms_products WHERE id='.(int)$physicalProductId)->fetch();
    if ((int)$stockState['stock_quantity']!==1 || (int)$stockState['stock_reserved']!==0) throw new RuntimeException('Cancelling an unpaid physical order did not release its inventory reservation.');
    $outOfStockRejected=false;
    try{$commerce->createOrder($adminId,[['type'=>'product','id'=>$physicalProductId,'quantity'=>2]],$delivery);}catch(RuntimeException){$outOfStockRejected=true;}
    if(!$outOfStockRejected) throw new RuntimeException('Checkout allowed more physical units than remaining stock.');
    $shipping->updateFulfillment((int)$physicalOrder['id'], $adminId, 'processing', '', '');
    $shipping->updateFulfillment((int)$physicalOrder['id'], $adminId, 'shipped', 'Test Courier', 'TRACK-100');
    $shipping->updateFulfillment((int)$physicalOrder['id'], $adminId, 'delivered', 'Test Courier', 'TRACK-100');
    $fulfilled = $pdo->query('SELECT fulfillment_status,carrier_name,tracking_reference,shipped_at,delivered_at FROM orders WHERE id='.(int)$physicalOrder['id'])->fetch();
    if ($fulfilled['fulfillment_status'] !== 'delivered' || $fulfilled['carrier_name'] !== 'Test Courier' || $fulfilled['tracking_reference'] !== 'TRACK-100' || !$fulfilled['shipped_at'] || !$fulfilled['delivered_at']) throw new RuntimeException('Physical order fulfilment and tracking history were not recorded.');

    $emptyProductId = $content->save('products', null, ['title' => 'Incomplete Digital Product', 'description_html' => 'Must not be purchasable without files.', 'price_ugx' => '1000', 'category' => 'Testing', 'product_type' => 'online', 'status' => 'published']);
    $missingFilesRejected = false;
    try {
        $commerce->createOrder($adminId, [['type' => 'product', 'id' => $emptyProductId, 'quantity' => 1]]);
    } catch (RuntimeException) {
        $missingFilesRejected = true;
    }
    if (!$missingFilesRejected) throw new RuntimeException('An online product without downloadable files was purchasable.');

    $media->execute(['path' => 'media/phase5/unsupported.mp4', 'name' => 'unsupported.mp4', 'mime' => 'video/mp4', 'size' => 2048, 'checksum' => hash('sha256', 'unsupported.mp4'), 'alt' => '', 'visibility' => 'private', 'user_id' => $adminId, 'created_at' => $now]);
    $unsupportedAssetId = (int) $pdo->lastInsertId();
    $attach->execute(['product_id' => $productId, 'media_id' => $unsupportedAssetId, 'role' => 'download', 'sort_order' => 2, 'created_at' => $now]);
    $unsupportedFileRejected = false;
    try {
        $commerce->createOrder($adminId, [['type' => 'product', 'id' => $productId, 'quantity' => 1]]);
    } catch (RuntimeException) {
        $unsupportedFileRejected = true;
    }
    if (!$unsupportedFileRejected) throw new RuntimeException('An unsupported media type was accepted as a digital download.');
    $pdo->prepare('DELETE FROM cms_product_assets WHERE product_id = :product_id AND media_asset_id = :media_id')->execute(['product_id' => $productId, 'media_id' => $unsupportedAssetId]);

    $order = $commerce->createOrder($adminId, [['type' => 'product', 'id' => $productId, 'quantity' => 1], ['type' => 'course', 'id' => $courseId, 'quantity' => 1]]);
    if ((int) $order['delivery_fee_ugx'] !== 0) throw new RuntimeException('Course and digital purchases unexpectedly incurred delivery fees.');
    $itemId = (int) $order['items'][0]['id'];
    $courseItemId = (int) $order['items'][1]['id'];
    if ((int) $order['total_ugx'] !== 20000 || (int) $pdo->query('SELECT COUNT(*) FROM order_item_assets WHERE order_item_id = ' . $itemId)->fetchColumn() !== 2) throw new RuntimeException('The order did not snapshot every private product asset.');
    $payment = $commerce->createPayment((int) $order['id'], $adminId, 'dpo');
    $samePayment = $commerce->createPayment((int) $order['id'], $adminId, 'dpo');
    if ((int) $payment['id'] !== (int) $samePayment['id']) throw new RuntimeException('Repeated payment creation did not reuse the active idempotent payment.');
    $commerce->updateProviderReference((int) $payment['id'], 'phase5-dpo-token', 'CREATED', ['company_ref' => $order['public_id']]);
    $blankReferenceRejected = false;
    try { $commerce->confirmPayment((int) $payment['id'], 'dpo', 'phase5-dpo-token', '', 20000, 'UGX', 'COMPLETED', 'phase5-invalid-event'); } catch (RuntimeException) { $blankReferenceRejected = true; }
    if (!$blankReferenceRejected) throw new RuntimeException('A payment without a provider transaction reference was accepted.');
    if (!$commerce->confirmPayment((int) $payment['id'], 'dpo', 'phase5-dpo-token', 'phase5-approval', 20000, 'UGX', 'COMPLETED', 'phase5-event')) throw new RuntimeException('A verified payment did not complete the purchase.');

    $learning = new App\Learning\LearningService($pdo);
    if (!$learning->hasAccess($adminId, $courseId)) throw new RuntimeException('A verified course purchase did not grant course access.');
    $files = $commerce->purchasedFiles($adminId, $itemId);
    if (count($files) !== 2 || $files[0]['original_name'] !== 'guide.pdf' || $files[1]['original_name'] !== 'notes.txt') throw new RuntimeException('Purchased files were not returned in their configured order.');
    $readOrder = $commerce->createOrder($adminId, [['type'=>'product','id'=>$productId,'quantity'=>1,'entitlement'=>'read']]);
    $readItemId = (int) $readOrder['items'][0]['id'];
    if ((int)$readOrder['total_ugx'] !== 6000 || $readOrder['items'][0]['product_entitlement'] !== 'read') throw new RuntimeException('The read entitlement did not use its independent purchase-time price.');
    $readPayment = $commerce->createPayment((int)$readOrder['id'], $adminId, 'dpo');
    $commerce->updateProviderReference((int)$readPayment['id'], 'phase2-read-token', 'CREATED');
    if (!$commerce->confirmPayment((int)$readPayment['id'], 'dpo', 'phase2-read-token', 'phase2-read-tx', 6000, 'UGX', 'COMPLETED', 'phase2-read-event')) throw new RuntimeException('Read-only digital purchase did not complete.');
    $readFiles = $commerce->purchasedReadableFiles($adminId, $readItemId);
    if (count($readFiles) !== 2 || $commerce->purchasedFiles($adminId, $readItemId) !== [] || !$commerce->purchasedReadableAsset($adminId, (int)$readFiles[0]['asset_id']) || $commerce->purchasedAsset($adminId, (int)$readFiles[0]['asset_id']) !== null) throw new RuntimeException('Read access incorrectly granted original-file download permission.');
    $readRefund = (new RefundService($pdo))->request($readItemId, $adminId, 6000, 'Read entitlement cancelled.');
    (new RefundService($pdo))->resolve($readRefund, $adminId, 'succeeded', 'DPO-READ-REFUND', 'Read access refund confirmed.');
    if ($commerce->purchasedReadableAsset($adminId, (int)$readFiles[0]['asset_id']) !== null || $commerce->purchasedAsset($adminId, (int)$files[0]['asset_id']) === null) throw new RuntimeException('Refunding read access did not revoke only the read entitlement.');
    if (!$commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) || $commerce->purchasedAsset($otherUserId, (int) $files[0]['asset_id']) !== null) throw new RuntimeException('A private purchase asset escaped order ownership checks.');

    $refunds = new RefundService($pdo);
    $legacyReservation = $pdo->prepare("INSERT INTO refunds (payment_id, amount_ugx, reason, status, created_at, requested_by, updated_at) VALUES (:payment_id, 19000, 'Legacy pre-allocation refund', 'pending', :created_at, :admin_id, :updated_at)");
    $legacyReservation->execute(['payment_id' => (int) $payment['id'], 'created_at' => $now, 'admin_id' => $adminId, 'updated_at' => $now]);
    $legacyRefundId = (int) $pdo->lastInsertId();
    $legacyBalanceRejected = false;
    try { $refunds->request($itemId, $adminId, 2000, 'Must respect a legacy payment-level reservation.'); } catch (RuntimeException) { $legacyBalanceRejected = true; }
    if (!$legacyBalanceRejected) throw new RuntimeException('A new item refund ignored a pending legacy payment-level refund.');
    $refunds->resolve($legacyRefundId, $adminId, 'failed', '', 'Legacy refund request was not submitted to the provider.');
    $firstRefund = $refunds->request($itemId, $adminId, 8000, 'Customer requested a partial refund.');
    $overRefundRejected = false;
    try {
        $refunds->request($itemId, $adminId, 2001, 'This must exceed the remaining product balance.');
    } catch (RuntimeException) {
        $overRefundRejected = true;
    }
    if (!$overRefundRejected) throw new RuntimeException('Concurrent or cumulative refund requests exceeded the captured amount.');
    $refunds->resolve($firstRefund, $adminId, 'succeeded', 'DPO-REFUND-001', 'Confirmed in the DPO merchant portal.');
    $partial = $commerce->order((int) $order['id'], $adminId);
    if (($partial['status'] ?? '') !== 'partially_refunded' || $commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) === null) throw new RuntimeException('A partial refund did not preserve the remaining paid purchase entitlement.');

    $secondRefund = $refunds->request($itemId, $adminId, 2000, 'Remaining digital product balance refunded.');
    $refunds->resolve($secondRefund, $adminId, 'succeeded', 'DPO-REFUND-002', 'Digital product refund confirmed in the DPO merchant portal.');
    if ($commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) !== null || !$learning->hasAccess($adminId, $courseId)) throw new RuntimeException('A fully refunded digital item was not revoked independently of the still-paid course.');
    $thirdRefund = $refunds->request($courseItemId, $adminId, 10000, 'Course purchase refunded.');
    $refunds->resolve($thirdRefund, $adminId, 'succeeded', 'DPO-REFUND-003', 'Course refund confirmed in the DPO merchant portal.');
    $finalOrder = $commerce->order((int) $order['id'], $adminId);
    $finalPayment = $commerce->providerPayment('dpo', 'phase5-dpo-token');
    if (($finalOrder['status'] ?? '') !== 'refunded' || ($finalPayment['status'] ?? '') !== 'refunded') throw new RuntimeException('A fully refunded order and payment did not reach refunded status.');
    if ($commerce->purchasedAsset($adminId, (int) $files[0]['asset_id']) !== null) throw new RuntimeException('A fully refunded order retained private download access.');
    if ($learning->hasAccess($adminId, $courseId)) throw new RuntimeException('A fully refunded course remained accessible without another paid purchase.');
    if (count($refunds->auditTrail($firstRefund)) !== 2 || count($refunds->auditTrail($secondRefund)) !== 2 || count($refunds->auditTrail($thirdRefund)) !== 2) throw new RuntimeException('Refund requests and provider-confirmed resolutions were not fully audited.');

    echo "PASS server-priced physical delivery, inventory reservation and release\n";
    echo "PASS mixed-cart shipping separation and delivery-free digital orders\n";
    echo "PASS physical order fulfilment tracking and physical refund exclusion\n";
    echo "PASS multi-file product snapshots and secure owner-bound delivery\n";
    echo "PASS public product gallery separation\n";
    echo "PASS idempotent active payment creation and verified payment completion\n";
    echo "PASS item-level refund limits and independent entitlement revocation\n";
    echo "PASS full refund revokes private purchase access and preserves audit history\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    @rmdir($temporary);
}
