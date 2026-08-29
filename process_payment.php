<?php
require_once 'auth.php';
require_login();

date_default_timezone_set('Africa/Kampala');

if($_SERVER['REQUEST_METHOD'] != 'POST') die("Invalid access");

$user_id = $_POST['user_id'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'Not Specified';
$submitted_total = (float) preg_replace('/[^0-9.]/', '', (string) ($_POST['total'] ?? 0));
$accountId = current_user()['id'];

$db_file = __DIR__ . "/database/bookings.db";
if(!file_exists($db_file)) die("Database not found.");

$bookings = []; 

try {
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    ensure_bookings_columns($db);

    $transactionTime = date('Y-m-d H:i:s');

    $stmt = $db->prepare("SELECT COALESCE(SUM(CAST(REPLACE(REPLACE(price,'UGX',''),',','') AS REAL)),0) AS total FROM bookings WHERE paid=0 AND (user_id=:user_id OR account_id=:account_id)");
    $stmt->execute([':user_id' => $user_id, ':account_id' => $accountId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $server_total = round((float) ($row['total'] ?? 0), 2);

    if ($server_total <= 0) {
        die("No unpaid bookings found for this user.");
    }
    
    $total = $server_total;

    $stmt = $db->prepare("UPDATE bookings SET paid=1, created_at=:now, account_id=:account_id WHERE paid=0 AND (user_id=:user_id OR account_id=:account_id2)");
    $stmt->execute([':user_id' => $user_id, ':now' => $transactionTime, ':account_id' => $accountId, ':account_id2' => $accountId]);

    $recentLimit = date('Y-m-d H:i:s', strtotime('-60 seconds'));
    $stmt = $db->prepare("SELECT * FROM bookings WHERE paid=1 AND created_at >= :recent AND (user_id=:user_id OR account_id=:account_id) ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $user_id, ':account_id' => $accountId, ':recent' => $recentLimit]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if(empty($bookings)) {
        $stmt = $db->prepare("SELECT * FROM bookings WHERE paid=1 AND (user_id=:user_id OR account_id=:account_id) ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([':user_id' => $user_id, ':account_id' => $accountId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $success = true;
} catch(Exception $e){
    error_log("process_payment.php error: " . $e->getMessage());
    $error = "We couldn't process your payment. Please try again or contact support.";
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
  <title>Receipt - SmartStudyPro</title>

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
      --ssp-badge-bg: #F8FAFC;
      --ssp-badge-text: #64748B;
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
      --ssp-badge-bg: #1E293B;
      --ssp-badge-text: #CBD5E1;
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

    .receipt-wrapper { max-width: 800px; margin: 0 auto; }
    
    .receipt-card { 
      background: var(--ssp-card-bg); 
      border-radius: 16px; 
      border: 1px solid var(--ssp-card-border); 
      overflow: hidden; 
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }
    
    .receipt-header { background: #0C086B; color: white; padding: 40px; }
    [data-theme="dark"] .receipt-header { background: #070443; }

    .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .ssp-badge-service {
      background-color: var(--ssp-badge-bg);
      color: var(--ssp-badge-text);
      border: 1px solid var(--ssp-card-border);
    }

    /* Table Fixes for Bootstrap Dark Mode */
    .table {
      --bs-table-bg: transparent;
      --bs-table-color: var(--ssp-text-main);
      --bs-table-border-color: var(--ssp-card-border);
      color: var(--ssp-text-main);
      border-color: var(--ssp-card-border);
    }

    .table td, .table tr {
      background-color: var(--ssp-card-bg) !important;
      color: var(--ssp-text-main);
    }

    .table th {
      background-color: var(--ssp-bg-soft) !important;
      color: var(--ssp-text-muted);
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

    .border-bottom {
      border-color: var(--ssp-card-border) !important;
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

    [data-theme="dark"] .text-dark {
      color: var(--ssp-text-main) !important;
    }

    @media print { 
      .no-print { display: none !important; } 
      .receipt-card { box-shadow: none; border: 1px solid #ccc; } 
    }
  </style>
</head>

<body>

  <header id="header" class="header ssp-header d-flex align-items-center sticky-top py-2 no-print">
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
    <div class="container receipt-wrapper px-3">
      <?php if(isset($success) && $success): ?>
        <div class="receipt-card">
          <div class="receipt-header text-center">
            <i class="bi bi-patch-check-fill" style="font-size: 4rem; color: var(--ssp-orange);"></i>
            <h2 class="fw-bold mt-2">Payment Successful</h2>
            <p class="mb-0 opacity-75">Thank you for your order via <?= htmlspecialchars($payment_method) ?></p>
          </div>
          
          <div class="card-body p-4 p-md-5">
            <div class="row mb-4 border-bottom pb-3">
              <div class="col-6">
                <span class="text-muted small text-uppercase fw-bold">Amount Paid</span>
                <h3 class="fw-bold" style="color: var(--ssp-orange);">UGX <?= number_format($total) ?></h3>
              </div>
              <div class="col-6 text-end">
                <span class="text-muted small text-uppercase fw-bold">Date & Time</span>
                <p class="fw-semibold mb-0" style="color: var(--ssp-navy);"><?= date('M d, Y') ?></p>
                <small class="text-muted"><?= date('h:i A') ?> (EAT)</small>
              </div>
            </div>

            <h5 class="fw-bold mb-4" style="color: var(--ssp-navy);">Your Purchased Resources</h5>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr class="text-muted small">
                    <th>DESCRIPTION</th>
                    <th class="text-end">ACCESS</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($bookings as $item): ?>
                    <tr>
                      <td>
                        <div class="fw-bold" style="color: var(--ssp-navy);"><?= htmlspecialchars($item['service']) ?></div>
                        <small class="text-muted">ID: #<?= str_pad($item['id'], 6, "0", STR_PAD_LEFT) ?></small>
                      </td>
                      <td class="text-end">
                        <?php 
                        $isCourse = (stripos($item['service'], 'Course') !== false); 
                        
                        if($isCourse): ?>
                          <a href="study.php?course_id=<?= $item['id'] ?>" class="btn-ssp-primary btn-sm rounded-pill px-3 text-decoration-none">
                            <i class="bi bi-play-circle-fill me-1"></i> Start Learning
                          </a>
                        <?php elseif(!empty($item['file_path'])): ?>
                          <a href="download.php?file=<?= urlencode($item['file_path']) ?>" class="btn-ssp-navy btn-sm rounded-pill px-3 text-decoration-none">
                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download
                          </a>
                        <?php else: ?>
                          <span class="badge ssp-badge-service px-3">Physical/Service</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="text-center mt-5 no-print">
              <button onclick="window.print()" class="btn btn-outline-secondary rounded-3 px-4 py-2 me-2 fw-semibold">
                <i class="bi bi-printer me-1"></i> Print Receipt
              </button>
              <a href="index.php" class="btn-ssp-primary text-decoration-none">Back to Home</a>
            </div>
          </div>
        </div>

      <?php elseif(isset($error)): ?>
        <div class="receipt-card p-5 text-center border-start border-danger border-5">
          <i class="bi bi-exclamation-octagon text-danger" style="font-size: 3.5rem;"></i>
          <h3 class="mt-3 fw-bold text-danger">Error Processing Order</h3>
          <p class="text-muted"><?= htmlspecialchars($error) ?></p>
          <a href="checkout.php" class="btn-ssp-navy text-decoration-none d-inline-block mt-3 px-4">Try Again</a>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer id="footer" class="ssp-footer pt-5 pb-3 no-print">
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