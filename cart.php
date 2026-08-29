<?php

$bookings = [];

if (isset($_COOKIE['user_id'])) {
    $user_id = $_COOKIE['user_id'];
    $db_file = __DIR__ . "/database/bookings.db";

    if (file_exists($db_file)) {
        try {
            $db = new PDO("sqlite:$db_file");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT * FROM bookings WHERE user_id=:user_id AND paid=0");
            $stmt->execute([':user_id'=>$user_id]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("cart.php DB error: " . $e->getMessage());
            $bookings = [];
        }
    }
}

$phone   = '+256 704 416250';
$email   = 'smartstudypro36@gmail.com';
$address = 'Kampala, Uganda';

$seoTitle       = 'Your Cart | SmartStudyPro';
$seoDescription = 'Review your selected courses and products before checkout on SmartStudyPro.';
$siteUrl        = "https://smartstudypro.com";
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
      --ssp-table-header: #F1F5F9;
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
      --ssp-table-header: #1E293B;
      --ssp-header-bg: rgba(11, 15, 23, 0.95);
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ssp-text-main);
      background-color: var(--ssp-bg-main);
      transition: background-color 0.3s ease, color 0.3s ease;
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

    [data-theme="dark"] .ssp-header {
      border-bottom-color: rgba(255, 255, 255, 0.1);
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

    [data-theme="dark"] .navmenu ul li a {
      color: #F1F5F9;
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

    [data-theme="dark"] .dropdown-menu {
      background-color: var(--ssp-card-bg);
      border-color: var(--ssp-card-border);
    }
    
    [data-theme="dark"] .dropdown-item {
      color: var(--ssp-text-main);
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

    [data-theme="dark"] .page-title-ssp {
      background: linear-gradient(135deg, #0B0F17 0%, #151C2C 100%);
    }

    .ssp-card-box {
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      background: var(--ssp-card-bg);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    /* Table dark mode fixes */
    .ssp-table {
      border-collapse: separate;
      border-spacing: 0;
      color: var(--ssp-text-main) !important;
      background-color: transparent !important;
    }

    .ssp-table th,
    .ssp-table td {
      background-color: transparent !important;
      color: var(--ssp-text-main) !important;
      border-color: var(--ssp-card-border) !important;
    }

    .ssp-table th {
      background-color: var(--ssp-table-header) !important;
      color: var(--ssp-navy) !important;
      font-weight: 700;
      border-bottom: 2px solid var(--ssp-card-border) !important;
    }

    .table-hover tbody tr:hover td,
    .table-hover tbody tr:hover th {
      background-color: rgba(255, 122, 0, 0.08) !important;
    }

    tfoot tr td {
      background-color: var(--ssp-table-header) !important;
    }

    .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .btn-outline-secondary {
      color: var(--ssp-text-main);
      border-color: var(--ssp-card-border);
    }

    .btn-outline-secondary:hover {
      background-color: var(--ssp-bg-soft);
      color: var(--ssp-text-main);
      border-color: var(--ssp-card-border);
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

    [data-theme="dark"] .text-dark {
      color: var(--ssp-text-main) !important;
    }
  </style>
</head>

<body class="cart-page">

  <?php include 'nav.php'; ?>

  <main class="main">

    <div class="page-title-ssp text-center">
      <div class="container" data-aos="fade-up">
        <h1 class="fw-bold mb-2">Shopping Cart</h1>
        <p class="mb-0 opacity-75">Review your selected learning packages and complete your enrollment</p>
      </div>
    </div>

    <section class="section py-5" style="background-color: var(--ssp-bg-main);">
      <div class="container" data-aos="fade-up">
        
        <?php if(empty($bookings)): ?>
          <div class="ssp-card-box p-5 text-center my-4">
            <i class="bi bi-bag-x text-muted" style="font-size: 4rem;"></i>
            <h3 class="fw-bold mt-3 mb-2" style="color: var(--ssp-navy);">Your cart is currently empty</h3>
            <p class="text-muted mb-4">Looks like you haven't added any courses or products to your cart yet.</p>
            <a href="courses.php" class="btn-ssp-primary text-decoration-none d-inline-block py-2 px-4">Browse Courses</a>
          </div>
        <?php else: ?>
          <div class="ssp-card-box p-4 p-md-5 overflow-hidden">
            <div class="table-responsive">
              <table class="table ssp-table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th class="py-3 px-3 rounded-start">Service / Item</th>
                    <th class="py-3 px-3">Date / Schedule</th>
                    <th class="py-3 px-3">Price</th>
                    <th class="py-3 px-3 text-end rounded-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $total = 0;
                  foreach($bookings as $b):
                    $price = floatval(str_replace('UGX ','',$b['price']));
                    $total += $price;
                  ?>
                  <tr>
                    <td class="py-3 px-3 fw-bold" style="color: var(--ssp-navy);"><?= htmlspecialchars($b['service']) ?></td>
                    <td class="py-3 px-3 text-muted"><?= htmlspecialchars($b['date']) ?></td>
                    <td class="py-3 px-3 fw-semibold" style="color: var(--ssp-orange);">UGX <?= number_format($price, 2) ?></td>
                    <td class="py-3 px-3 text-end">
                      <a href="remove_from_cart.php?id=<?= (int) $b['id'] ?>" class="btn btn-outline-danger btn-sm rounded-2">
                        <i class="bi bi-trash me-1"></i> Remove
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" class="py-3 px-3 fw-bold text-end" style="color: var(--ssp-navy);">Grand Total:</td>
                    <td colspan="2" class="py-3 px-3 fw-bold fs-5" style="color: var(--ssp-orange);">UGX <?= number_format($total, 2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top" style="border-color: var(--ssp-card-border) !important;">
              <a href="courses.php" class="btn btn-outline-secondary rounded-3 text-decoration-none px-4 py-2">
                <i class="bi bi-arrow-left me-1"></i> Continue Browsing
              </a>
              <a href="checkout.php" class="btn-ssp-primary text-decoration-none px-4 py-2 fs-6">
                Proceed to Checkout <i class="bi bi-arrow-right ms-1"></i>
              </a>
            </div>
          </div>
        <?php endif; ?>

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