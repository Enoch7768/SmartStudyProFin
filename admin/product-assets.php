<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Fproduct-assets.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator') || !Auth::hasPermission($pdo, (int) $user['id'], 'manage_content')) {
    http_response_code(403);
    exit('Administrator access is required.');
}

$error = '';
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            $role = (string) ($_POST['asset_role'] ?? '');
            $ids = $_POST['media_ids'] ?? [];
            if ($productId < 1 || !in_array($role, ['gallery', 'download'], true) || !is_array($ids) || count($ids) > 25) throw new RuntimeException('Choose a product and a valid asset group.');
            $product = $pdo->prepare("SELECT id FROM cms_products WHERE id = :id AND product_type = 'online'");
            $product->execute(['id' => $productId]);
            if (!$product->fetchColumn()) throw new RuntimeException('Choose an online product.');
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM cms_product_assets WHERE product_id = :product_id AND asset_role = :asset_role')->execute(['product_id' => $productId, 'asset_role' => $role]);
            $insert = $pdo->prepare('INSERT INTO cms_product_assets (product_id, media_asset_id, asset_role, sort_order, created_at) VALUES (:product_id, :media_asset_id, :asset_role, :sort_order, :created_at)');
            $assetQuery = $pdo->prepare('SELECT id, mime_type, visibility, file_size FROM media_assets WHERE id = :id');
            $allowedFiles = ['application/pdf', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/rtf', 'text/rtf', 'application/vnd.oasis.opendocument.text'];
            $seen = [];
            foreach ($ids as $position => $rawId) {
                $assetId = filter_var($rawId, FILTER_VALIDATE_INT);
                if (!$assetId || isset($seen[$assetId])) throw new RuntimeException('The selected assets contain an invalid or duplicate item.');
                $seen[$assetId] = true;
                $assetQuery->execute(['id' => $assetId]);
                $asset = $assetQuery->fetch();
                $valid = $asset && (int) $asset['file_size'] > 0 && ($role === 'gallery'
                    ? $asset['visibility'] === 'public' && in_array($asset['mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
                    : $asset['visibility'] === 'private' && in_array($asset['mime_type'], $allowedFiles, true));
                if (!$valid) throw new RuntimeException('Gallery images must be public images. Download files must be supported private assets.');
                $insert->execute(['product_id' => $productId, 'media_asset_id' => $assetId, 'asset_role' => $role, 'sort_order' => $position, 'created_at' => gmdate('Y-m-d H:i:s')]);
            }
            $pdo->commit();
            Security::redirect('product-assets.php?product=' . $productId . '&role=' . $role . '&saved=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getMessage();
        }
    }
}

$products = $pdo->query("SELECT id, title FROM cms_products WHERE product_type = 'online' ORDER BY title")->fetchAll();
$productId = filter_var($_GET['product'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT) ?: (int) ($products[0]['id'] ?? 0);
$role = in_array($_GET['role'] ?? 'download', ['gallery', 'download'], true) ? (string) ($_GET['role'] ?? 'download') : 'download';
$selected = [];
if ($productId > 0) {
    $query = $pdo->prepare('SELECT media_asset_id FROM cms_product_assets WHERE product_id = :product_id AND asset_role = :asset_role ORDER BY sort_order, id');
    $query->execute(['product_id' => $productId, 'asset_role' => $role]);
    $selected = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
}
$assets = $pdo->query('SELECT id, original_name, mime_type, file_size, visibility FROM media_assets ORDER BY id DESC')->fetchAll();
if (isset($_GET['saved'])) $notice = 'Product assets saved.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Product files and images | SmartStudyPro</title>
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<link href="../assets/css/device-responsive.css" rel="stylesheet">
</head>
<body>
<main class="container py-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
<div><p class="small text-uppercase fw-bold mb-1" style="color:#FF7A00">Commerce management</p><h1 class="h2 mb-0">Product files and images</h1></div>
<a class="btn btn-outline-secondary" href="cms.php?section=products">Back to products</a>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert alert-success"><?= Security::escape($notice) ?></div><?php endif; ?>
<?php if (!$products): ?><div class="border rounded-4 p-4">Create an online product before attaching files or images.</div><?php else: ?>
<form method="get" class="row g-3 mb-4">
<div class="col-md-6"><label class="form-label" for="product">Online product</label><select class="form-select" id="product" name="product" onchange="this.form.submit()"><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= $productId === (int) $product['id'] ? 'selected' : '' ?>><?= Security::escape($product['title']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="role">Asset group</label><select class="form-select" id="role" name="role" onchange="this.form.submit()"><option value="download" <?= $role === 'download' ? 'selected' : '' ?>>Private files delivered after purchase</option><option value="gallery" <?= $role === 'gallery' ? 'selected' : '' ?>>Public product gallery images</option></select></div>
</form>
<form method="post" class="border rounded-4 p-3 p-md-4">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<input type="hidden" name="product_id" value="<?= $productId ?>">
<input type="hidden" name="asset_role" value="<?= Security::escape($role) ?>">
<p class="text-muted"><?= $role === 'gallery' ? 'Select public images shown on the product page.' : 'Select private documents, PDFs, text files, spreadsheets, presentations or image files delivered only after verified payment.' ?></p>
<div class="row g-3">
<?php foreach ($assets as $asset):
$eligible = $role === 'gallery' ? $asset['visibility'] === 'public' && in_array($asset['mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) : $asset['visibility'] === 'private';
if (!$eligible) continue;
?>
<div class="col-md-6 col-xl-4"><label class="border rounded-3 p-3 d-flex gap-2 h-100"><input class="form-check-input mt-1" type="checkbox" name="media_ids[]" value="<?= (int) $asset['id'] ?>" <?= in_array((int) $asset['id'], $selected, true) ? 'checked' : '' ?>><span><strong><?= Security::escape($asset['original_name']) ?></strong><br><small class="text-muted"><?= Security::escape($asset['mime_type']) ?> · <?= number_format((int) $asset['file_size']) ?> bytes</small></span></label></div>
<?php endforeach; ?>
</div>
<div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-primary">Save selected assets</button><a class="btn btn-outline-secondary" href="cms.php?section=media">Open media library</a></div>
</form>
<?php endif; ?>
</main>
</body>
</html>
