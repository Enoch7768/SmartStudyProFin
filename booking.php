<?php

require_once 'cms-init.php';
require_once 'auth.php';

if(!isset($_COOKIE['user_id'])) {
    $user_id = bin2hex(random_bytes(16));
    setcookie('user_id', $user_id, time() + (86400*30), "/"); 
} else {
    $user_id = $_COOKIE['user_id'];
}

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = trim($_POST['name']    ?? '');
    $email    = trim($_POST['email']   ?? '');
    $phone    = trim($_POST['phone']   ?? 'N/A');
    $date     = $_POST['date']    ?? date('Y-m-d');
    $service  = trim($_POST['service'] ?? $_POST['item_name'] ?? 'Unknown Item');
    $message  = trim($_POST['message'] ?? $_POST['address'] ?? '');
    $price    = $_POST['price']   ?? $_POST['item_price'] ?? '0';
    $filePath = $_POST['product_file'] ?? '';
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid name and email address.";
    }
    $price = number_format((float) preg_replace('/[^0-9.]/', '', (string) $price), 2, '.', '');
    $filePath = ltrim(str_replace(['..', '\\'], '', (string) $filePath), '/');

    $courseId  = $_POST['course_id']  ?? null;
    $productId = $_POST['product_id'] ?? null;

    if (!isset($error) && $courseId && function_exists('cockpit')) {
        $course = cockpit('content')->item('Courses', ['_id' => $courseId]);
        if ($course) {
            $service  = $course['Title'] ?? $service;
            $price    = number_format((float) ($course['Price'] ?? 0), 2, '.', '');
            $filePath = ''; 
        } else {
            $error = "The selected course could not be found.";
        }
    } elseif (!isset($error) && $productId && function_exists('cockpit')) {
        $product = cockpit('content')->item('Products', ['_id' => $productId]);
        if ($product) {
            $service  = $product['Title'] ?? $service;
            $price    = number_format((float) ($product['Price'] ?? 0), 2, '.', '');
            $realFile = $product['ProductFile']['path'] ?? '';
            $filePath = $realFile ? ltrim(str_replace(['..', '\\'], '', (string) $realFile), '/') : '';
        } else {
            $error = "The selected product could not be found.";
        }
    }
    $accountId = null;
    if (function_exists('current_user') && ($user = current_user())) {
        $accountId = $user['id'];
        $name  = $user['name'];
        $email = $user['email'];
    }

    try {
        if (isset($error)) {
            throw new Exception($error);
        }

        $db_dir = __DIR__ . "/database";
        $db_file = $db_dir . "/bookings.db";
        
        if (!file_exists($db_dir)) mkdir($db_dir, 0777, true);

        $db = new PDO("sqlite:$db_file");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        ensure_bookings_columns($db);
        $stmt = $db->prepare("INSERT INTO bookings (user_id, name, email, phone, date, service, message, price, file_path, account_id) 
                              VALUES (:user_id, :name, :email, :phone, :date, :service, :message, :price, :file_path, :account_id)");
        $stmt->execute([
            ':user_id'    => $user_id,
            ':name'       => $name,
            ':email'      => $email,
            ':phone'      => $phone,
            ':date'       => $date,
            ':service'    => $service,
            ':message'    => $message,
            ':price'      => $price,
            ':file_path'  => $filePath,
            ':account_id' => $accountId
        ]);

        $success = true;
    } catch (Exception $e) {
        if (!isset($error)) {
            error_log("booking.php error: " . $e->getMessage());
            $error = "Sorry, we couldn't process your order. Please try again.";
        }
    }
} else {
    header("Location: products.php");
    exit;
}
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
  <title>Processing Order - SmartStudyPro</title>

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

    .btn-ssp-navy {
      background-color: var(--ssp-navy);
      color: #FFFFFF;
      font-weight: 600;
      border-radius: 10px;
      padding: 10px 24px;
      border: none;
      transition: all 0.25s ease;
    }

    .btn-ssp-navy:hover {
      background-color: var(--ssp-navy-dark);
      color: #FFFFFF;
      transform: translateY(-2px);
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

  <main class="py-5" style="background-color: var(--ssp-bg-soft); min-height: 80vh;">
    <div class="container my-4">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
          
          <?php if($success): ?>
            <div class="ssp-card text-center p-5">
              <div class="mb-4">
                <i class="bi bi-bag-check-fill" style="font-size: 4.5rem; color: var(--ssp-orange);"></i>
              </div>
              <h2 class="fw-bold" style="color: var(--ssp-navy);">Item Added to Cart</h2>
              <p class="text-muted px-lg-4">Your selection <strong>"<?= htmlspecialchars($service) ?>"</strong> has been successfully added to your shopping bag.</p>
              <hr class="my-4 mx-5 opacity-25">
              <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="products.php" class="btn btn-outline-secondary rounded-3 px-4 py-2 fw-semibold text-decoration-none">Continue Shopping</a>
                <a href="cart.php" class="btn-ssp-primary text-decoration-none">View My Cart</a>
              </div>
            </div>

          <?php else: ?>
            <div class="ssp-card p-5 border-start border-danger border-5">
              <h3 class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Database Sync Required</h3>
              <p class="mt-3"><?= htmlspecialchars($error) ?></p>
              <p class="small text-muted">Technical Tip: If this error persists, try deleting <code>database/bookings.db</code> to reset the schema.</p>
              <a href="javascript:history.back()" class="btn-ssp-navy text-decoration-none d-inline-block mt-3">Go Back</a>
            </div>
          <?php endif; ?>

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