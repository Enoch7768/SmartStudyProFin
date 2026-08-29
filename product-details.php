<?php 
require_once 'cms-init.php'; 

$product = null;
$contact = null;
$productId = $_GET['id'] ?? $_GET['_id'] ?? null;

if (function_exists('cockpit')) {
    if ($productId) {
        try {
            $product = cockpit('content')->findOne('Products', ['_id' => $productId]);

            if (!$product) {
                $items = cockpit('content')->items('Products', ['filter' => ['_id' => $productId]]);
                if (!empty($items)) {
                    $product = $items[0];
                }
            }

            if (!$product) {
                $allProducts = cockpit('content')->items('Products');
                foreach ($allProducts as $p) {
                    if (isset($p['_id']) && $p['_id'] == $productId) {
                        $product = $p;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $product = null;
        }
    }

    try {
        $contact = cockpit('content')->findOne('ContactDetails') 
                ?? cockpit('content')->item('ContactDetails');
    } catch (Exception $e) {
        $contact = null;
    }
}

$displayTitle = $product['Title'] ?? $product['title'] ?? 'Product Details';
$displayPrice = $product['Price'] ?? $product['price'] ?? '0';
$displayDesc  = $product['Description'] ?? $product['description'] ?? 'No description available.';
$productType  = $product['ProductType'] ?? $product['producttype'] ?? 'Online'; 
$category     = $product['Category'] ?? $product['category'] ?? 'General';

$phone   = $contact['phone'] ?? '+256 704 416250';
$email   = $contact['email'] ?? 'smartstudypro36@gmail.com';
$address = strip_tags($contact['address'] ?? 'Kampala, Uganda');

$imgData = $product['Image'] ?? $product['image'] ?? null;
$imgUrl = !empty($imgData['path']) 
          ? '/schoolprojectt/SmartStudyProV2.3/cms/storage/uploads' . $imgData['path'] 
          : 'assets/img/course-details-tab-1.png';

$filePath = $product['ProductFile']['path'] ?? $product['productfile']['path'] ?? '';

$seoTitle       = $product['SEO-Title'] ?? $product['seo_title'] ?? ($displayTitle . " | SmartStudyPro Store");
$seoDescription = $product['SEO-Description'] ?? $product['seo_description'] ?? substr(strip_tags($displayDesc), 0, 160);
$siteUrl        = "https://smartstudypro.com";
$fullImgUrl     = $siteUrl . $imgUrl;
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
  
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="product">
  <meta property="og:url" content="<?= $siteUrl ?>/product-details.php?id=<?= $productId ?>">
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
      "url": "<?= $siteUrl ?>/product-details.php?id=<?= $productId ?>",
      "priceCurrency": "UGX",
      "price": "<?= $displayPrice ?>",
      "availability": "https://schema.org/InStock"
    }
  }
  </script>
  <?php endif; ?>

  <link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon">
  <link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="apple-touch-icon">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

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
      --ssp-input-bg: #FFFFFF;
      --ssp-input-border: #CBD5E1;
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
      --ssp-input-bg: #1E293B;
      --ssp-input-border: #334155;
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

    .ssp-card-box {
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      background: var(--ssp-card-bg);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .form-control {
      background-color: var(--ssp-input-bg);
      border-color: var(--ssp-input-border);
      color: var(--ssp-text-main);
    }

    .form-control:focus {
      background-color: var(--ssp-input-bg);
      color: var(--ssp-text-main);
      border-color: var(--ssp-orange);
    }

    .form-label {
      color: var(--ssp-text-main);
    }

    .content.text-muted {
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
</head>

<body>

  <?php include 'nav.php'; ?>

  <main class="main">

    <div class="page-title-ssp text-center">
      <div class="container" data-aos="fade-up">
        <h1 class="fw-bold mb-2"><?= htmlspecialchars($displayTitle) ?></h1>
        <p class="mb-0 opacity-75"><?= htmlspecialchars($category) ?></p>
      </div>
    </div>

    <section class="section py-5">
      <div class="container" data-aos="fade-up">
        <div class="row gy-4">
          
          <div class="col-lg-8">
            <img src="<?= $imgUrl ?>" class="img-fluid rounded-4 shadow-sm mb-4 w-100" style="max-height: 420px; object-fit: cover;" alt="<?= htmlspecialchars($displayTitle) ?>">
            <h3 class="fw-bold mb-3" style="color: var(--ssp-navy);">About this Resource</h3>
            <div class="content text-muted lh-lg">
                <?= $displayDesc ?>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="p-4 ssp-card-box">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="m-0 fw-bold" style="color: var(--ssp-navy);">Price</h4>
                <span class="h3 m-0 fw-bold" style="color: var(--ssp-orange);">UGX <?= number_format(floatval($displayPrice ?? 0)) ?></span>
              </div>
              <p class="small" style="color: var(--ssp-text-muted);"><i class="bi bi-info-circle me-1"></i> Type: <strong><?= htmlspecialchars($productType) ?></strong></p>
              <hr class="my-3" style="border-color: var(--ssp-card-border);">
              
              <form action="booking.php" method="post">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                <input type="hidden" name="item_name" value="<?= htmlspecialchars($displayTitle) ?>">
                <input type="hidden" name="item_price" value="<?= htmlspecialchars($displayPrice) ?>">
                <input type="hidden" name="product_file" value="<?= htmlspecialchars($filePath) ?>">
                
                <div class="mb-3">
                  <label class="form-label small fw-semibold">Full Name</label>
                  <input type="text" name="name" class="form-control rounded-3" placeholder="John Doe" required>
                </div>
                <div class="mb-3">
                  <label class="form-label small fw-semibold">Email Address</label>
                  <input type="email" name="email" class="form-control rounded-3" placeholder="example@mail.com" required>
                </div>
                
                <?php if(strtolower($productType) === 'physical'): ?>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Shipping Address</label>
                    <textarea name="address" class="form-control rounded-3" rows="2" placeholder="Your delivery address..." required></textarea>
                  </div>
                <?php endif; ?>

                <button type="submit" class="btn-ssp-primary w-100 py-2 mt-2">
                    Add to Cart
                </button>
              </form>
            </div>
          </div>

        </div>
      </div>
    </section>

  </main>

  <footer id="footer" class="ssp-footer pt-5 pb-3">
    <div class="container footer-top mb-4">
      <div class="row gy-4">
        
        <div class="col-lg-4 col-md-6">
          <a href="index.php" class="ssp-footer-brand text-decoration-none mb-3 d-inline-block">
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
            <li class="mb-2"><a href="index.php" class="text-decoration-none text-light opacity-75">Home</a></li>
            <li class="mb-2"><a href="about.php" class="text-decoration-none text-light opacity-75">About Us</a></li>
            <li class="mb-2"><a href="courses.php" class="text-decoration-none text-light opacity-75">Courses</a></li>
            <li class="mb-2"><a href="products.php" class="text-decoration-none text-light opacity-75">Products & Materials</a></li>
            <li class="mb-2"><a href="contact.php" class="text-decoration-none text-light opacity-75">Contact Us</a></li>
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

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>