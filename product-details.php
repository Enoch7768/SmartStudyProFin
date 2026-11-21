<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

$pdo = \App\Core\Database::connect();
$content = new \App\Cms\PublicContent($pdo);
$productIdentifier = $_GET['slug'] ?? $_GET['id'] ?? $_GET['_id'] ?? null;
$product = $content->product(is_string($productIdentifier) || is_int($productIdentifier) ? $productIdentifier : null);
$productId = (int) ($product['_id'] ?? 0);
if ($product && !isset($_GET['slug']) && !headers_sent()) {
    $canonicalProductPath = '/products/' . rawurlencode((string) ($product['slug'] ?? ''));
    header('Location: ' . \App\Core\Url::to($canonicalProductPath), true, 301);
    exit;
}
$contact = $content->contact();

if (!$product) http_response_code(404);

$displayTitle = $product['Title'] ?? 'Product Details';
$displayPrice = $product['Price'] ?? '0';
$readPrice = (int) ($product['ReadPrice'] ?? $displayPrice);
$downloadPrice = (int) ($product['DownloadPrice'] ?? $displayPrice);
$displayDesc = $product['Description'] ?? 'No description available.';
$productType = $product['ProductType'] ?? 'Online';
$isDigitalProduct = strtolower((string) $productType) === 'online';
$productInStock = !empty($product['StockAvailable']);
$category = $product['Category'] ?? 'General';

$phone = $contact['phone'];
$email = $contact['email'];
$address = strip_tags($contact['address']);

$imgData = $product['Image'] ?? null;
$imgUrl = $imgData['url'] ?? 'assets/img/course-details.jpg';
$gallery = $product['Gallery'] ?? [];
if (!$gallery && $imgData) $gallery = [['url' => $imgUrl, 'alt' => $displayTitle]];
$includedFiles = $product['Files'] ?? [];
$filePath = '';

$seoTitle = $product['SEO-Title'] ?? ($displayTitle . ' | SmartStudyPro Store');
$seoDescription = $product['SEO-Description'] ?? substr(strip_tags($displayDesc), 0, 160);
$siteUrl = \App\Core\Url::site();
$fullImgUrl = str_starts_with($imgUrl, 'http') ? $imgUrl : $siteUrl . '/' . ltrim($imgUrl, '/');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <script>
    (function() {
      const savedTheme = localStorage.getItem('ssp-theme');
      const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">
  
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="product">
  <meta property="og:url" content="<?= $siteUrl ?>/products/<?= rawurlencode((string) ($product['slug'] ?? '')) ?>">
  <link rel="canonical" href="<?= $siteUrl ?>/products/<?= rawurlencode((string) ($product['slug'] ?? '')) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $fullImgUrl ?>">

  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="twitter:image" content="<?= $fullImgUrl ?>">

  <?php if ($product): ?>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org/",
    "@type": "Product",
    "name": "<?= htmlspecialchars($displayTitle) ?>",
    "image": "<?= $fullImgUrl ?>",
    "description": "<?= htmlspecialchars(strip_tags($displayDesc)) ?>",
    "brand": {
      "@type": "Brand",
      "name": "SmartStudyPro"
    },
    "offers": {
      "@type": "Offer",
      "url": "<?= $siteUrl ?>/products/<?= rawurlencode((string) ($product['slug'] ?? '')) ?>",
      "priceCurrency": "UGX",
      "price": "<?= $displayPrice ?>",
      "availability": "<?= $isDigitalProduct || $productInStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>"
    }
  }
  </script>
  <?php endif; ?>

  <link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/Smart_Study_Logo_Fin-removebg-preview.png'), ENT_QUOTES, 'UTF-8') ?>" rel="apple-touch-icon">

  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">


<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/public-pages.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>

<body class="product-detail-page">

  <?php include 'nav.php'; ?>

  <main id="main-content" class="main">

    <div class="page-title-ssp text-center">
      <div class="container">
        <h1 class="fw-bold mb-2"><?= htmlspecialchars($displayTitle) ?></h1>
        <p class="mb-0 opacity-75"><?= htmlspecialchars($category) ?></p>
      </div>
    </div>

    <section class="ssp-product-detail section py-5">
      <div class="container">
        <?php if ($product): ?>
          <div class="ssp-product-detail-layout">
            <div class="ssp-product-detail-main">
              <div class="ssp-product-gallery">
                <?php if ($gallery): ?>
                  <?php foreach ($gallery as $image): ?>
                    <figure><img src="<?= htmlspecialchars($image['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($image['alt'] ?: $displayTitle, ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></figure>
                  <?php endforeach; ?>
                <?php else: ?>
                  <figure><img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>"></figure>
                <?php endif; ?>
              </div>
              <div class="ssp-product-story">
                <span class="ssp-eyebrow"><?= $isDigitalProduct ? 'Digital learning resource' : 'Physical learning material' ?></span>
                <h2>About this <?= $isDigitalProduct ? 'resource' : 'product' ?></h2>
                <div class="ssp-product-rich-content"><?= $displayDesc ?></div>
                <?php if ($includedFiles): ?>
                  <section class="ssp-included-files">
                    <h3>What’s included</h3>
                    <ul><?php foreach ($includedFiles as $file): ?><li><i class="bi bi-file-earmark-check" aria-hidden="true"></i><span><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></span><small><?= number_format((int) $file['size'] / 1024, 1) ?> KB</small></li><?php endforeach; ?></ul>
                    <p>Secure access becomes available in your order history after payment is verified.</p>
                  </section>
                <?php endif; ?>
              </div>
            </div>
            <aside class="ssp-product-purchase">
              <div class="ssp-purchase-panel">
                <div class="ssp-purchase-topline"><span><?= $isDigitalProduct ? 'Instant digital access' : 'Physical product' ?></span><span class="ssp-product-category"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span></div>
                <h2><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="ssp-purchase-description"><?= htmlspecialchars(substr(trim(strip_tags((string) $displayDesc)), 0, 180), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="ssp-purchase-facts"><span><i class="bi bi-tag" aria-hidden="true"></i><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span><span><i class="bi bi-<?= $isDigitalProduct ? 'cloud-arrow-down' : 'box-seam' ?>" aria-hidden="true"></i><?= $isDigitalProduct ? 'Digital delivery' : ($productInStock ? 'In stock' : 'Out of stock') ?></span></div>
                <?php if ($isDigitalProduct): ?>
                  <div class="ssp-access-choice">
                    <div><span class="ssp-access-label">Read or view online</span><strong>UGX <?= number_format($readPrice) ?></strong><p>Open supported content in your browser. Original-file downloads are not included.</p></div>
                    <form method="post" action="<?= \App\Core\Url::to('/cart/add') ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="type" value="product"><input type="hidden" name="id" value="<?= $productId ?>"><input type="hidden" name="entitlement" value="read"><button type="submit" class="ssp-purchase-button">Add read access <span aria-hidden="true">→</span></button></form>
                  </div>
                  <div class="ssp-access-choice ssp-access-choice-download">
                    <div><span class="ssp-access-label">Download original files</span><strong>UGX <?= number_format($downloadPrice) ?></strong><p>Save the approved original files to your device. Browser reading is not included.</p></div>
                    <form method="post" action="<?= \App\Core\Url::to('/cart/add') ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="type" value="product"><input type="hidden" name="id" value="<?= $productId ?>"><input type="hidden" name="entitlement" value="download"><button type="submit" class="ssp-purchase-button">Add download access <span aria-hidden="true">→</span></button></form>
                  </div>
                <?php else: ?>
                  <div class="ssp-physical-price"><span>Purchase price</span><strong>UGX <?= number_format((int) $displayPrice) ?></strong></div>
                  <?php if (!$productInStock): ?>
                    <p class="ssp-stock-message"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Currently out of stock. Please check back later.</p>
                  <?php else: ?>
                    <form method="post" action="<?= \App\Core\Url::to('/cart/add') ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="type" value="product"><input type="hidden" name="id" value="<?= $productId ?>"><button type="submit" class="ssp-purchase-button ssp-purchase-button-primary">Add to cart <span aria-hidden="true">→</span></button></form>
                    <p class="ssp-delivery-note"><i class="bi bi-truck" aria-hidden="true"></i> Delivery options and charges are shown during checkout.</p>
                  <?php endif; ?>
                <?php endif; ?>
                <a class="ssp-back-to-store" href="<?= htmlspecialchars(\App\Core\Url::to('/products'), ENT_QUOTES, 'UTF-8') ?>">← Back to all products</a>
              </div>
            </aside>
          </div>
        <?php else: ?>
          <div class="ssp-product-not-found"><i class="bi bi-search" aria-hidden="true"></i><h2>We couldn’t find that product.</h2><p>It may have been unpublished or the link may have changed.</p><a href="<?= htmlspecialchars(\App\Core\Url::to('/products'), ENT_QUOTES, 'UTF-8') ?>">Browse the store</a></div>
        <?php endif; ?>
      </div>
    </section>

  </main>

  <footer id="footer" class="ssp-footer pt-5 pb-3">
    <div class="container footer-top mb-4">
      <div class="row gy-4">
        
        <div class="col-lg-4 col-md-6">
          <a href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-footer-brand text-decoration-none mb-3 d-inline-block">
            SmartStudy<span>Pro</span>
          </a>
          <p class="small text-light opacity-75 mb-3">Study Made Simple, Success Made Sure.</p>
          
          <div class="small mb-3">
            <p class="mb-1"><i class="bi bi-geo-alt-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($address) ?></p>
            <p class="mb-1"><i class="bi bi-telephone-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($phone) ?></p>
            <p class="mb-1"><i class="bi bi-envelope-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($email) ?></p>
          </div>

          <div class="d-flex gap-2">
            <a href="#" class="social-icon-btn"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-facebook"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-instagram"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>

        <div class="col-lg-3 col-md-3">
          <h5 class="text-white fw-bold mb-3">Quick Navigation</h5>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-light opacity-75">Home</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-light opacity-75">About Us</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-light opacity-75">Courses</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/products'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-light opacity-75">Products & Materials</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/contact'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-light opacity-75">Contact Us</a></li>
          </ul>
        </div>

        <div class="col-lg-5 col-md-3">
          <h5 class="text-white fw-bold mb-3">Core Educational Offerings</h5>
          <ul class="list-unstyled small opacity-75">
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Private & Customized Tutoring</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Holiday Package & Guided Learning</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Homework & Assignment Support</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Science Project & STEM Innovation</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Applied Computer & ICT Lessons</li>
          </ul>
        </div>

      </div>
    </div>

    <div class="container text-center border-top border-secondary pt-3 mt-3 opacity-75 small">
      <p class="mb-0">&copy; <?= date('Y') ?> <strong>SmartStudyPro</strong>. All Rights Reserved. Empowering Education in Uganda.</p>
    </div>
  </footer>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center text-decoration-none"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>