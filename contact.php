<?php 
require_once 'cms-init.php'; 

$contact = null;

try {
    if (function_exists('cockpit')) {
        $contact = cockpit('content')->item('ContactDetails'); 
    }
} catch (Exception $e) {
    $contact = null; 
}

$phone   = !empty($contact['phone'])   ? strip_tags($contact['phone'])   : '+256 704 416250';
$email   = !empty($contact['email'])   ? strip_tags($contact['email'])   : 'smartstudypro36@gmail.com';
$address = !empty($contact['address']) ? strip_tags($contact['address']) : 'Kampala, Uganda';

$seoTitle       = 'Contact Us | SmartStudyPro Uganda';
$seoDescription = 'Get in touch with SmartStudyPro for inquiries about our online courses, private tutoring, and educational packages in Kampala, Uganda.';
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
  <meta name="keywords" content="contact SmartStudyPro, tutoring Kampala, education Uganda support, inquiry">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $siteUrl ?>/contact.php">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $siteUrl ?>/Smart_Study_Logo_Fin-removebg-preview.png">

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
      --ssp-input-bg: #1A2338;
      --ssp-input-border: #2E3A52;
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

    .ssp-page-title {
      background: linear-gradient(135deg, #0C086B 0%, #070443 100%);
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
      color: rgba(255, 255, 255, 0.7);
      font-size: 0.9rem;
      text-decoration: none;
    }

    .ssp-page-title .breadcrumb-item.active {
      color: var(--ssp-orange);
      font-weight: 600;
    }

    .info-card {
      background: var(--ssp-card-bg);
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    .info-card:hover {
      transform: translateY(-4px);
      border-color: rgba(255, 122, 0, 0.4);
      box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.25);
    }

    .info-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background-color: rgba(255, 122, 0, 0.1);
      color: var(--ssp-orange);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .contact-form-container {
      background: var(--ssp-card-bg);
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .form-control {
      border-radius: 10px;
      padding: 12px 16px;
      background-color: var(--ssp-input-bg);
      color: var(--ssp-text-main);
      border: 1px solid var(--ssp-input-border);
    }

    .form-control::placeholder {
      color: var(--ssp-text-muted);
    }

    .form-control:focus {
      background-color: var(--ssp-input-bg);
      color: var(--ssp-text-main);
      border-color: var(--ssp-orange);
      box-shadow: 0 0 0 0.25rem rgba(255, 122, 0, 0.2);
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

<body class="contact-page">

  <?php include 'nav.php'; ?>

  <main class="main">

    <div class="ssp-page-title text-center">
      <div class="container" data-aos="fade">
        <nav aria-label="breadcrumb" class="d-flex justify-content-center">
          <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Contact</li>
          </ol>
        </nav>
        <h1 class="fw-bold mb-2">Get In Touch</h1>
        <p class="text-light opacity-75 mb-0">Reach out to us for any inquiries, guidance, or educational support.</p>
      </div>
    </div>

    <section id="contact" class="contact section py-5" style="background-color: var(--ssp-bg-main);">
      <div class="container" data-aos="fade-up">
        
        <div class="row gy-4 mb-5">

          <div class="col-lg-4">
            <div class="d-flex flex-column gap-3">
              
              <div class="info-card d-flex align-items-center gap-3">
                <div class="info-icon flex-shrink-0">
                  <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Location</h6>
                  <p class="text-muted small mb-0"><?= htmlspecialchars($address) ?></p>
                </div>
              </div>

              <div class="info-card d-flex align-items-center gap-3">
                <div class="info-icon flex-shrink-0">
                  <i class="bi bi-telephone-fill"></i>
                </div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Call Us</h6>
                  <p class="text-muted small mb-0"><?= htmlspecialchars($phone) ?></p>
                </div>
              </div>

              <div class="info-card d-flex align-items-center gap-3">
                <div class="info-icon flex-shrink-0">
                  <i class="bi bi-envelope-fill"></i>
                </div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Email Us</h6>
                  <p class="text-muted small mb-0"><?= htmlspecialchars($email) ?></p>
                </div>
              </div>

            </div>
          </div>

          <div class="col-lg-8">
            <div class="contact-form-container">
              <h4 class="fw-bold mb-3" style="color: var(--ssp-navy);">Send Us A Message</h4>
              <form action="contact-process.php" method="post" class="php-email-form">
                <div class="row gy-3">
                  <div class="col-md-6">
                    <input type="text" name="name" class="form-control" placeholder="Your Name" required>
                  </div>
                  <div class="col-md-6">
                    <input type="email" class="form-control" name="email" placeholder="Your Email" required>
                  </div>
                  <div class="col-md-12">
                    <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                  </div>
                  <div class="col-md-12">
                    <textarea class="form-control" name="message" rows="5" placeholder="Message" required></textarea>
                  </div>
                  <div class="col-md-12">
                    <button type="submit" class="btn-ssp-primary w-100">Send Message</button>
                  </div>
                </div>
              </form>
            </div>
          </div>

        </div>

        <div class="rounded-4 overflow-hidden border shadow-sm" style="border-color: var(--ssp-card-border) !important;" data-aos="fade-up">
          <iframe style="border:0; width: 100%; height: 320px;" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15959.0270477025!2d32.5694863!3d0.313611!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x177dbc0917056f71%3A0x2863e46c98ee3e40!2sKampala!5e0!3m2!1sen!2sug!4v1700000000000!5m2!1sen!2sug" allowfullscreen="" loading="lazy"></iframe>
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
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>