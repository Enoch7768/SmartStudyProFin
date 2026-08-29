<?php 
require_once 'cms-init.php'; 

$courses = [];
$contact = null;

try {
    if (function_exists('cockpit')) {
        $courses = cockpit('content')->items('Courses');
        $contact = cockpit('content')->item('ContactDetails');
    }
} catch (Exception $e) {
    $courses = [];
    $contact = null;
}

$phone   = $contact['phone'] ?? '+256 704 416250';
$email   = $contact['email'] ?? 'smartstudypro36@gmail.com';
$address = strip_tags($contact['address'] ?? 'Kampala, Uganda');

$seoTitle       = 'Professional Courses in Uganda | SmartStudyPro';
$seoDescription = 'Browse our catalog of expert-led courses. From ICT and Science to professional skill development, find the right path for your future at SmartStudyPro.';
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
  <meta name="keywords" content="coding courses, science projects, ICT training Uganda, professional certificates">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $siteUrl ?>/courses.php">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $siteUrl ?>/Smart_Study_Logo_Fin-removebg-preview.png">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "ItemList",
    "itemListElement": [
      <?php 
      $i = 1;
      foreach(array_slice($courses, 0, 5) as $course):
      ?>
      {
        "@type": "ListItem",
        "position": <?= $i ?>,
        "name": "<?= htmlspecialchars($course['Title'] ?? 'Course') ?>",
        "url": "<?= $siteUrl ?>/course-details.php?id=<?= $course['_id'] ?>"
      }<?= ($i < count(array_slice($courses, 0, 5))) ? ',' : '' ?>
      <?php $i++; endforeach; ?>
    ]
  }
  </script>

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
    }

    .course-item {
      display: flex;
      flex-direction: column;
      height: 100%;
      background: var(--ssp-card-bg);
      border: 1px solid var(--ssp-card-border);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .course-item:hover {
      transform: translateY(-4px);
      border-color: rgba(255, 122, 0, 0.3);
      box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.25);
    }

    .course-content {
      display: flex;
      flex-direction: column;
      flex-grow: 1;
      padding: 20px;
    }

    .course-title-link {
      color: var(--ssp-text-main);
      transition: color 0.2s ease;
    }

    .course-title-link:hover {
      color: var(--ssp-orange);
    }

    .description {
      flex-grow: 1;
      margin-bottom: 20px;
      color: var(--ssp-text-muted);
      font-size: 0.9rem;
    }

    .trainer {
      margin-top: auto;
      border-top: 1px solid var(--ssp-card-border);
      padding-top: 15px;
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

<body class="courses-page">

 <?php include 'nav.php'; ?>
  <main class="main">

    <div class="ssp-page-title text-center">
      <div class="container" data-aos="fade">
        <h1 class="fw-bold mb-2">Our Courses</h1>
        <p class="text-light opacity-75 mb-0">Quality educational programs tailored for academic excellence and practical skill creation.</p>
      </div>
    </div>

    <section id="courses" class="courses section py-5">
      <div class="container">
        <div class="row gy-4">
          <?php if (!empty($courses)): ?>
            <?php foreach($courses as $course): ?>
              <div class="col-lg-4 col-md-6 d-flex align-items-stretch" data-aos="zoom-in">
                <div class="course-item w-100">
                  <?php 
                    $cImgData = $course['Image'] ?? $course['image'] ?? null;
                    $cImg = !empty($cImgData['path']) 
                            ? '/schoolprojectt/SmartStudyProV2.3/cms/storage/uploads'.$cImgData['path'] 
                            : 'assets/img/course-1.jpg'; 
                  ?>
                  <img src="<?= $cImg ?>" class="img-fluid" style="height: 200px; object-fit: cover;" alt="<?= htmlspecialchars($course['Title'] ?? 'Course') ?>">
                  
                  <div class="course-content">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <span class="badge bg-warning text-dark fw-bold px-3 py-1">
                        <?= htmlspecialchars($course['Category'] ?? 'General') ?>
                      </span>
                      <p class="fw-bold fs-5 mb-0" style="color: var(--ssp-navy);">UGX <?= number_format(floatval($course['Price'] ?? 0), 2) ?></p>
                    </div>

                    <h5 class="fw-bold mb-2">
                      <a href="course-details.php?id=<?= $course['_id'] ?>" class="text-decoration-none course-title-link"><?= htmlspecialchars($course['Title'] ?? 'Untitled Course') ?></a>
                    </h5>
                    
                    <div class="description">
                        <?= substr(strip_tags($course['Description'] ?? $course['description'] ?? ''), 0, 110) ?>...
                    </div>
                    
                    <div class="trainer">
                        <a href="course-details.php?id=<?= $course['_id'] ?>" class="btn-ssp-primary d-block text-center text-decoration-none">
                            Explore Course
                        </a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12 text-center py-5">
              <p class="text-muted fs-5">No courses found. Please check back later.</p>
            </div>
          <?php endif; ?>
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

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>