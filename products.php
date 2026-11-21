<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

$pdo = \App\Core\Database::connect();
$content = new \App\Cms\PublicContent($pdo);
$products = $content->products();
$contact = $content->contact();

$phone = $contact['phone'];
$email = $contact['email'];
$address = strip_tags($contact['address']);

$seoTitle = 'Educational Products & Learning Materials | SmartStudyPro';
$seoDescription = 'Browse physical and digital learning materials, holiday packages, and study resources from SmartStudyPro in Uganda.';
$siteUrl = \App\Core\Url::site();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <base href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">
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
  
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta name="keywords" content="smartstudypro products, educational resources Uganda, study materials, holiday packages">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $siteUrl ?>/products">
  <link rel="canonical" href="<?= $siteUrl ?>/products">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $siteUrl ?>/Smart_Study_Logo_Fin-removebg-preview.png">

  <link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/Smart_Study_Logo_Fin-removebg-preview.png'), ENT_QUOTES, 'UTF-8') ?>" rel="apple-touch-icon">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

  <style>
    :root {
      --ssp-navy: #0C086B;
      --ssp-navy-dark: #070443;
      --ssp-orange: #FF7A00;
      --ssp-orange-hover: #E06B00;
      --ssp-bg-main: #FFFFFF;
      --ssp-bg-soft: #F8FAFC;
      --ssp-card-bg: #FFFFFF;
      --ssp-card-border: #E2E8F0;
      --ssp-text-main: #1E293B;
      --ssp-text-muted: #64748B;
      --ssp-badge-bg: #EFF6FF;
      --ssp-header-bg: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] {
      --ssp-navy: #C7D2FE;
      --ssp-navy-dark: #0B0F17;
      --ssp-orange: #FF8A1D;
      --ssp-orange-hover: #FF9E3B;
      --ssp-bg-main: #0B0F17;
      --ssp-bg-soft: #1E293B;
      --ssp-card-bg: #151C2C;
      --ssp-card-border: #2E3A52;
      --ssp-text-main: #F8FAFC;
      --ssp-text-muted: #94A3B8;
      --ssp-badge-bg: #1E293B;
      --ssp-header-bg: rgba(11, 15, 23, 0.95);
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ssp-text-main);
      background-color: var(--ssp-bg-main);
    }

    h1, h2, h3, h4, h5, .brand-font {
      font-family: 'Outfit', sans-serif;
    }

    .ssp-header {
      background: var(--ssp-header-bg);
      backdrop-filter: blur(12px);
      border-bottom: 2px solid rgba(12, 8, 107, 0.08);
      transition: all 0.3s ease;
    }

    .navmenu ul {
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
      display: flex !important;
      align-items: center !important;
      gap: 24px;
    }

    .navmenu ul li {
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
    }

    .navmenu ul li a {
      color: var(--ssp-navy);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none !important;
      display: inline-block;
      white-space: nowrap;
      transition: color 0.2s ease;
    }

    .navmenu ul li a:hover,
    .navmenu ul li a.active {
      color: var(--ssp-orange) !important;
      font-weight: 700;
    }

    .dropdown-toggle-no-caret::after {
      display: none !important;
    }

    .dropdown-menu .dropdown-item:hover {
      background-color: var(--ssp-bg-soft);
      color: var(--ssp-orange);
    }

    .btn-ssp-primary {
      background-color: var(--ssp-orange);
      color: #FFFFFF;
      font-weight: 700;
      border-radius: 10px;
      padding: 10px 24px;
      border: none;
      box-shadow: 0 4px 14px rgba(255, 122, 0, 0.35);
      transition: all 0.25s ease;
    }

    .btn-ssp-primary:hover {
      background-color: var(--ssp-orange-hover);
      color: #FFFFFF;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 122, 0, 0.45);
    }

    .page-title-ssp {
      background: linear-gradient(135deg, #0C086B 0%, #070443 100%);
      color: #FFFFFF;
      padding: 60px 0;
    }

    .ssp-course-card {
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      overflow: hidden;
      background: var(--ssp-card-bg);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .ssp-course-card:hover {
      transform: translateY(-8px);
      border-color: rgba(255, 122, 0, 0.4);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25);
    }

    /* Badge Truncation & Overlap Fix */
    .ssp-card-badge {
      background: var(--ssp-badge-bg);
      color: var(--ssp-navy);
      font-size: 0.75rem;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      max-width: 60%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: inline-block;
    }

    .ssp-price-tag {
      color: var(--ssp-navy);
      font-weight: 800;
      font-size: 1.15rem;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .product-title-link {
      color: var(--ssp-text-main);
      transition: color 0.2s ease;
    }

    .product-title-link:hover {
      color: var(--ssp-orange);
    }

    .btn-outline-custom {
      color: var(--ssp-text-main);
      border-color: var(--ssp-card-border);
      transition: all 0.2s ease;
    }

    .btn-outline-custom:hover {
      background-color: var(--ssp-orange);
      border-color: var(--ssp-orange);
      color: #FFFFFF;
    }

    .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .ssp-footer {
      background-color: #070443;
      color: #94A3B8;
    }

    [data-theme="dark"] .ssp-footer {
      background-color: #060911;
    }

    .ssp-footer-brand {
      color: #FFFFFF;
      font-size: 1.5rem;
      font-weight: 800;
    }

    .ssp-footer-brand span {
      color: var(--ssp-orange);
    }

    .social-icon-btn {
      width: 38px;
      height: 38px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
      color: #FFFFFF;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .social-icon-btn:hover {
      background: var(--ssp-orange);
      color: #FFFFFF;
    }
  </style>
<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/public-pages.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>

<body class="products-page">

<?php include 'nav.php'; ?>

  <main id="main-content" class="main">

    <div class="page-title-ssp text-center">
      <div class="container">
        <h1 class="fw-bold mb-2">Products & Learning Resources</h1>
        <p class="mb-0 opacity-75">Quality Educational Materials, Guides, and Kits for Effective Learning</p>
      </div>
    </div>

    <section id="products" class="products section py-5">
      <div class="container">
        <?php
          $productTypes = [];
          $productCategories = [];
          foreach ($products as $productRecord) {
              $isDigital = strtolower((string) ($productRecord['ProductType'] ?? '')) === 'online';
              $productTypes[$isDigital ? 'online' : 'physical'] = $isDigital ? 'Digital resources' : 'Physical products';
              $categoryName = trim((string) ($productRecord['Category'] ?? ''));
              if ($categoryName !== '') $productCategories[$categoryName] = $categoryName;
          }
        ?>
        <div class="ssp-commerce-intro">
          <div><span class="ssp-eyebrow">The learning store</span><h2>Resources that move learning forward.</h2><p>Choose digital resources to read online or download, or find physical materials for hands-on learning.</p></div>
          <div class="ssp-commerce-note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Clear access options and pricing before you buy.</span></div>
        </div>
        <?php if ($products): ?>
          <div class="ssp-catalogue-tools ssp-product-tools">
            <label class="ssp-catalogue-search"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Search products</span><input id="product-search" type="search" placeholder="Search resources, subjects or materials" autocomplete="off"></label>
            <label class="ssp-catalogue-filter"><span class="visually-hidden">Filter by product type</span><select id="product-type"><option value="">All products</option><?php foreach ($productTypes as $type => $label): ?><option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <label class="ssp-catalogue-filter"><span class="visually-hidden">Filter by category</span><select id="product-category"><option value="">All categories</option><?php foreach ($productCategories as $categoryName): ?><option value="<?= htmlspecialchars(strtolower($categoryName), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <p class="ssp-catalogue-count" id="product-count" role="status" aria-live="polite"><?= count($products) ?> <?= count($products) === 1 ? 'item' : 'items' ?></p>
          </div>
          <div class="row g-4" id="product-grid">
            <?php foreach ($products as $product): ?>
              <?php
                $productTitle = (string) ($product['Title'] ?? 'Untitled Product');
                $productDescription = trim(strip_tags((string) ($product['Description'] ?? '')));
                $productCategory = (string) ($product['Category'] ?? 'Resource');
                $productIsDigital = strtolower((string) ($product['ProductType'] ?? '')) === 'online';
                $productImage = (string) ($product['Image']['url'] ?? 'assets/img/course-1.jpg');
                $productPath = \App\Core\Url::to('/products/' . rawurlencode((string) ($product['slug'] ?? $product['_id'])));
                $productSearch = strtolower($productTitle . ' ' . $productCategory . ' ' . $productDescription);
              ?>
              <article class="col-lg-4 col-md-6 d-flex align-items-stretch ssp-product-result" data-product-search="<?= htmlspecialchars($productSearch, ENT_QUOTES, 'UTF-8') ?>" data-product-type="<?= $productIsDigital ? 'online' : 'physical' ?>" data-product-category="<?= htmlspecialchars(strtolower($productCategory), ENT_QUOTES, 'UTF-8') ?>">
                <div class="ssp-product-card w-100">
                  <a class="ssp-product-image" href="<?= htmlspecialchars($productPath, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true"><img src="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"></a>
                  <div class="ssp-product-card-body">
                    <div class="ssp-product-meta"><span class="ssp-product-kind"><?= $productIsDigital ? 'Digital resource' : 'Physical product' ?></span><span class="ssp-product-category"><?= htmlspecialchars($productCategory, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <h3><a href="<?= htmlspecialchars($productPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($productTitle, ENT_QUOTES, 'UTF-8') ?></a></h3>
                    <p class="ssp-product-description"><?= htmlspecialchars(substr($productDescription, 0, 142), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($productIsDigital): ?>
                      <div class="ssp-product-prices"><div><span>Read online</span><strong>UGX <?= number_format((int) ($product['ReadPrice'] ?? $product['Price'] ?? 0)) ?></strong></div><div><span>Download</span><strong>UGX <?= number_format((int) ($product['DownloadPrice'] ?? $product['Price'] ?? 0)) ?></strong></div></div>
                    <?php else: ?>
                      <div class="ssp-product-physical-price"><span><?= empty($product['StockAvailable']) ? 'Out of stock' : 'Purchase price' ?></span><strong>UGX <?= number_format((int) ($product['Price'] ?? 0)) ?></strong></div>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($productPath, ENT_QUOTES, 'UTF-8') ?>" class="ssp-product-link">Explore product <span aria-hidden="true">↗</span></a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
            <div class="col-12 ssp-catalogue-empty" id="product-empty" hidden><div class="ssp-catalogue-empty-inner"><i class="bi bi-search" aria-hidden="true"></i><h2 class="h4">No matching products</h2><p>Try another search or remove a filter.</p><button type="button" class="btn-ssp-primary" id="product-clear">Clear filters</button></div></div>
          </div>
        <?php else: ?>
          <div class="ssp-product-empty"><i class="bi bi-bag" aria-hidden="true"></i><h2>No products just yet</h2><p>New learning resources will appear here when they are published.</p><a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">Explore courses</a></div>
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
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/catalogue.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>