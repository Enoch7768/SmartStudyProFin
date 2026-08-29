<?php 
require_once 'cms-init.php'; 

$about = null;
$contact = null;

try {
    if (function_exists('cockpit')) {
        $about = cockpit('content')->item('AboutPage'); 
        $contact = cockpit('content')->item('ContactDetails');
    }
} catch (Exception $e) {
    $about = null; 
    $contact = null;
}

$displayTitle = $about['Main-Title'] ?? 'About Us';
$displayDesc  = $about['Story-Text'] ?? 'SmartStudyPro is dedicated to providing high-quality educational resources, personalized tutoring, and modern learning solutions across Uganda.';

$aboutImgData = $about['About-Image'] ?? null;
$aboutImg = !empty($aboutImgData['path']) 
            ? '/schoolprojectt/SmartStudyProV2.3/cms/storage/uploads'.$aboutImgData['path'] 
            : 'assets/img/20240102_164438.jpg';

$phone   = $contact['phone'] ?? '+256 704 416250';
$email   = $contact['email'] ?? 'smartstudypro36@gmail.com';
$address = strip_tags($contact['address'] ?? 'Kampala, Uganda');

$seoTitle       = $about['SEO-Title'] ?? 'About Us | SmartStudyPro Uganda';
$seoDescription = $about['SEO-Description'] ?? strip_tags($displayDesc);
$siteUrl        = "https://smartstudypro.com";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta name="keywords" content="about SmartStudyPro, e-learning Uganda, online education Kampala, tutoring services">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $siteUrl ?>/about.php">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $siteUrl . $aboutImg ?>">

  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>">

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
      --ssp-navy-dark: #050338;
      --ssp-orange: #E66A00;
      --ssp-orange-hover: #C65B00;
      --ssp-bg-soft: #F1F5F9;
      --ssp-text-main: #0F172A;
      --ssp-text-muted: #334155;
      --ssp-bg-page: #FFFFFF;
      --ssp-card-bg: #FFFFFF;
      --ssp-card-border: #CBD5E1;
    }

    /* Enhanced Dark Mode High Contrast Variables */
    [data-theme="dark"] {
      --ssp-navy: #C7D2FE;
      --ssp-navy-dark: #03021D;
      --ssp-orange: #FF8A1A;
      --ssp-orange-hover: #FF9B3B;
      --ssp-bg-soft: #0F172A;
      --ssp-text-main: #F8FAFC;
      --ssp-text-muted: #E2E8F0;
      --ssp-bg-page: #0B0F17;
      --ssp-card-bg: #111827;
      --ssp-card-border: #475569;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ssp-text-main);
      background-color: var(--ssp-bg-page);
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    h1, h2, h3, h4, h5, .brand-font {
      font-family: 'Outfit', sans-serif;
    }

    .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .ssp-header {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(12px);
      border-bottom: 2px solid rgba(12, 8, 107, 0.12);
      transition: all 0.3s ease;
    }

    [data-theme="dark"] .ssp-header {
      background: rgba(11, 15, 23, 0.98);
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
      font-weight: 700;
      font-size: 0.95rem;
      text-decoration: none !important;
      display: inline-block;
      white-space: nowrap;
      transition: color 0.2s ease;
    }

    .navmenu ul li a:hover,
    .navmenu ul li a.active {
      color: var(--ssp-orange) !important;
      font-weight: 800;
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
      box-shadow: 0 4px 14px rgba(230, 106, 0, 0.35);
      transition: all 0.25s ease;
    }

    .btn-ssp-primary:hover {
      background-color: var(--ssp-orange-hover);
      color: #FFFFFF;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(230, 106, 0, 0.45);
    }

    .btn-ssp-navy {
      background-color: #0C086B;
      color: #FFFFFF;
      font-weight: 700;
      border-radius: 10px;
      padding: 10px 24px;
      border: none;
      transition: all 0.25s ease;
    }

    [data-theme="dark"] .btn-ssp-navy {
      background-color: #3730A3;
      color: #FFFFFF;
    }

    .btn-ssp-navy:hover {
      background-color: var(--ssp-navy-dark);
      color: #FFFFFF;
      transform: translateY(-2px);
    }

    .ssp-page-title {
      background: linear-gradient(135deg, #070443 0%, #03021D 100%);
      padding: 80px 0 60px;
      color: #FFFFFF;
      position: relative;
    }

    .ssp-page-title .breadcrumb {
      background: transparent;
      padding: 0;
      margin-bottom: 12px;
    }

    .ssp-page-title .breadcrumb-item, 
    .ssp-page-title .breadcrumb-item a {
      color: #E2E8F0;
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
    }

    .ssp-page-title .breadcrumb-item.active {
      color: #FF9B3B;
      font-weight: 800;
    }

    .ssp-footer {
      background-color: var(--ssp-navy-dark);
      color: #CBD5E1;
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
      background: rgba(255, 255, 255, 0.12);
      color: #FFFFFF;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .social-icon-btn:hover {
      background: var(--ssp-orange);
      color: #FFFFFF;
    }
  </style>
  <script>
    // Immediate inline theme detection to prevent screen flicker
    const savedTheme = localStorage.getItem('ssp-theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  </script>
</head>

<body class="about-page">

  <?php include 'nav.php'; ?>

  <main class="main">

    <div class="ssp-page-title text-center">
      <div class="container" data-aos="fade">
        <nav aria-label="breadcrumb" class="d-flex justify-content-center">
          <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">About Us</li>
          </ol>
        </nav>
        <h1 class="fw-bold mb-2 text-white">About SmartStudyPro</h1>
        <p class="text-white opacity-90 mb-0 fw-medium">Study Made Simple, Success Made Sure</p>
      </div>
    </div>

    <section id="about-us" class="about-us section py-5">
      <div class="container" data-aos="fade-up">
        <div class="row gy-4 align-items-center">
          
          <div class="col-lg-6 order-1 order-lg-2">
            <div class="position-relative">
              <img src="<?= $aboutImg ?>" class="img-fluid rounded-4 shadow-lg w-100" alt="About SmartStudyPro">
            </div>
          </div>

          <div class="col-lg-6 order-2 order-lg-1">
            <span class="text-uppercase fw-bold text-primary fs-7 tracking-wider">Our Purpose & Vision</span>
            <h2 class="fw-bold mt-1 mb-3" style="color: var(--ssp-navy);"><?= htmlspecialchars($displayTitle) ?></h2>
            
            <div class="text-muted leading-relaxed mb-4 fs-6 fw-normal">
              <?= $displayDesc ?>
            </div>

            <div class="my-4">
              <div class="d-flex gap-3 mb-3">
                <div class="fs-4 flex-shrink-0"><i class="bi bi-patch-check-fill" style="color: var(--ssp-orange);"></i></div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Expert & Dedicated Instructors</h6>
                  <p class="text-muted small mb-0">Professional educators providing targeted guidance across multiple disciplines.</p>
                </div>
              </div>

              <div class="d-flex gap-3 mb-3">
                <div class="fs-4 flex-shrink-0"><i class="bi bi-patch-check-fill" style="color: var(--ssp-orange);"></i></div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Tailored Academic Packages</h6>
                  <p class="text-muted small mb-0">Customized learning solutions built around individual student schedules and needs.</p>
                </div>
              </div>

              <div class="d-flex gap-3">
                <div class="fs-4 flex-shrink-0"><i class="bi bi-patch-check-fill" style="color: var(--ssp-orange);"></i></div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Innovative STEM & ICT Guidance</h6>
                  <p class="text-muted small mb-0">Hands-on assistance for modern science projects and digital computer literacy.</p>
                </div>
              </div>
            </div>

            <a href="courses.php" class="btn-ssp-primary text-decoration-none d-inline-block">Browse Our Courses</a>
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
          <p class="small text-white opacity-90 mb-3">Study Made Simple, Success Made Sure.</p>
          
          <div class="small mb-3">
            <p class="mb-1 text-white"><i class="bi bi-geo-alt-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($address) ?></p>
            <p class="mb-1 text-white"><i class="bi bi-telephone-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($phone) ?></p>
            <p class="mb-1 text-white"><i class="bi bi-envelope-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($email) ?></p>
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
            <li class="mb-2"><a href="index.php" class="text-decoration-none text-white opacity-90">Home</a></li>
            <li class="mb-2"><a href="about.php" class="text-decoration-none text-white opacity-90">About Us</a></li>
            <li class="mb-2"><a href="courses.php" class="text-decoration-none text-white opacity-90">Courses</a></li>
            <li class="mb-2"><a href="products.php" class="text-decoration-none text-white opacity-90">Products & Materials</a></li>
            <li class="mb-2"><a href="contact.php" class="text-decoration-none text-white opacity-90">Contact Us</a></li>
          </ul>
        </div>

        <div class="col-lg-5 col-md-3">
          <h5 class="text-white fw-bold mb-3">Core Educational Offerings</h5>
          <ul class="list-unstyled small text-white opacity-90">
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Private & Customized Tutoring</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Holiday Package & Guided Learning</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Homework & Assignment Support</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Science Project & STEM Innovation</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Applied Computer & ICT Lessons</li>
          </ul>
        </div>

      </div>
    </div>

    <div class="container text-center border-top border-secondary pt-3 mt-3 text-white opacity-90 small">
      <p class="mb-0">&copy; <?= date('Y') ?> <strong>SmartStudyPro</strong>. All Rights Reserved. Empowering Education in Uganda.</p>
    </div>
  </footer>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center text-decoration-none"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>