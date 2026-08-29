<?php
require_once 'auth.php';
require_login();

if (!isset($_COOKIE['user_id'])) die("No user identified.");
$user_id = $_COOKIE['user_id'];

$db_file = __DIR__ . "/database/bookings.db";
if (!file_exists($db_file)) die("Database not found");

try {
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    ensure_bookings_columns($db);

    $user = current_user();
    $stmt = $db->prepare("SELECT * FROM bookings WHERE paid=0 AND (user_id=:user_id OR account_id=:account_id)");
    $stmt->execute([':user_id' => $user_id, ':account_id' => $user['id'] ?? 0]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bookings)) die("No unpaid bookings found.");

    $total = 0;
    foreach ($bookings as $b) {
        $total += (float) preg_replace('/[^0-9.]/', '', (string) $b['price']);
    }
} catch (Exception $e) {
    error_log("checkout.php DB error: " . $e->getMessage());
    die("Sorry, we couldn't load your checkout right now. Please try again later.");
}

$siteUrl = "https://smartstudypro.com";
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
  <title>Checkout - SmartStudyPro</title>

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
      --ssp-border-color: #E2E8F0;
      --ssp-text-main: #1E293B;
      --ssp-text-muted: #64748B;
      --ssp-input-bg: #FFFFFF;
      --ssp-header-bg: rgba(255, 255, 255, 0.95);
      --ssp-badge-bg: rgba(12, 8, 107, 0.1);
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
      --ssp-border-color: #334155;
      --ssp-text-main: #F8FAFC;
      --ssp-text-muted: #CBD5E1;
      --ssp-input-bg: #0F172A;
      --ssp-header-bg: rgba(11, 15, 23, 0.95);
      --ssp-badge-bg: rgba(199, 210, 254, 0.15);
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

    .ssp-card {
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      background: var(--ssp-card-bg);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .ssp-badge {
      background-color: var(--ssp-badge-bg);
      color: var(--ssp-navy);
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

    .form-select {
      background-color: var(--ssp-input-bg);
      color: var(--ssp-text-main);
      border-color: var(--ssp-border-color);
    }

    [data-theme="dark"] .form-select {
      background-color: var(--ssp-input-bg);
      color: var(--ssp-text-main);
      border-color: var(--ssp-border-color) !important;
    }

    [data-theme="dark"] .form-select option {
      background-color: var(--ssp-card-bg);
      color: var(--ssp-text-main);
    }

    .list-group-item {
      background-color: var(--ssp-card-bg);
      border-color: var(--ssp-card-border);
      color: var(--ssp-text-main);
    }

    [data-theme="dark"] .list-group-item {
      background-color: var(--ssp-card-bg);
      border-color: var(--ssp-card-border);
      color: var(--ssp-text-main);
    }
    
    .list-group-item.bg-light {
      background-color: var(--ssp-bg-soft) !important;
      color: var(--ssp-text-main) !important;
    }

    [data-theme="dark"] .bg-light {
      background-color: var(--ssp-bg-soft) !important;
      color: var(--ssp-text-main) !important;
    }
    
    [data-theme="dark"] .text-dark {
      color: var(--ssp-text-main) !important;
    }
  </style>
</head>

<body>

  <header id="header" class="header ssp-header d-flex align-items-center sticky-top py-2">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
      
      <a href="index.php" class="logo d-flex align-items-center me-auto me-xl-0 text-decoration-none">
        <img src="Smart_Study_Logo_Fin-removebg-preview.png" alt="SmartStudyPro Logo" height="48">
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="about.php">About Us</a></li>
          <li><a href="courses.php">Courses</a></li>
          <li><a href="products.php">Products</a></li>
          <li><a href="contact.php">Contact</a></li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list fs-2 ms-3"></i>
      </nav>

      <div class="d-flex align-items-center">
        <div class="dropdown me-2">
          <a href="#" class="text-dark fs-5 text-decoration-none dropdown-toggle-no-caret" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
            <i class="bi bi-person-circle"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="userMenuDropdown">
            <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2" style="color: var(--ssp-navy);"></i>My Profile</a></li>
            <li><a class="dropdown-item py-2" href="cart.php"><i class="bi bi-bag me-2" style="color: var(--ssp-navy);"></i>My Cart</a></li>
            <li><hr class="dropdown-divider"></li>
          </ul>
        </div>

        <a class="btn-ssp-primary d-none d-sm-inline-block text-decoration-none" href="courses.php">Explore Courses</a>
      </div>

    </div>
  </header>

  <main class="py-5" style="background-color: var(--ssp-bg-soft); min-height: 80vh;">
    <div class="container my-4">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          
          <div class="text-center mb-4">
            <span class="badge ssp-badge px-3 py-2 mb-2">Secure Checkout</span>
            <h1 class="fw-bold" style="color: var(--ssp-navy);">Review & Complete Order</h1>
          </div>

          <div class="ssp-card p-4 p-md-5">
            <h4 class="fw-bold mb-3" style="color: var(--ssp-navy);">Order Summary</h4>
            <ul class="list-group mb-4 shadow-sm rounded-3">
              <?php foreach ($bookings as $b): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                  <div>
                    <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($b['service']) ?></h6>
                    <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($b['date']) ?></small>
                  </div>
                  <span class="fw-bold" style="color: var(--ssp-navy);">UGX <?= htmlspecialchars((string) $b['price']) ?></span>
                </li>
              <?php endforeach; ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light fw-bold">
                <span class="fs-5" style="color: var(--ssp-navy);">Total Amount</span>
                <span class="fs-5" style="color: var(--ssp-orange);">UGX <?= number_format($total, 2) ?></span>
              </li>
            </ul>

            <h4 class="fw-bold mb-3" style="color: var(--ssp-navy);">Select Payment Method</h4>
            <form action="process_payment.php" method="post">
              <input type="hidden" name="user_id" value="<?= $user_id ?>">
              <input type="hidden" name="total" value="<?= $total ?>">
              
              <div class="mb-4">
                <select name="payment_method" class="form-select form-select-lg rounded-3 fs-6" required>
                  <option value="">-- Choose Payment Method --</option>
                  <option value="paypal">PayPal</option>
                  <option value="dpo">DPO Pay</option>
                  <option value="pay_at_booking">Pay at Booking</option>
                </select>
              </div>

              <div class="text-center">
                <button type="submit" class="btn-ssp-primary btn-lg w-100 py-3">Pay / Confirm Booking</button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
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
            <p class="mb-1"><i class="bi bi-geo-alt-fill me-2" style="color: var(--ssp-orange);"></i>Kampala, Uganda</p>
            <p class="mb-1"><i class="bi bi-telephone-fill me-2" style="color: var(--ssp-orange);"></i>+256 704 416250</p>
            <p class="mb-1"><i class="bi bi-envelope-fill me-2" style="color: var(--ssp-orange);"></i>smartstudypro36@gmail.com</p>
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

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>