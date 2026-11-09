<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

$pdo = \App\Core\Database::connect();
$content = new \App\Cms\PublicContent($pdo);
$courseIdentifier = $_GET['slug'] ?? $_GET['id'] ?? $_GET['_id'] ?? null;
$course = $content->course(is_string($courseIdentifier) || is_int($courseIdentifier) ? $courseIdentifier : null);
$courseId = (int) ($course['_id'] ?? 0);
if ($course && !isset($_GET['slug']) && !headers_sent()) {
    $canonicalCoursePath = '/courses/' . rawurlencode((string) ($course['slug'] ?? ''));
    header('Location: ' . \App\Core\Url::to($canonicalCoursePath), true, 301);
    exit;
}
$contact = $content->contact();

if (!$course) http_response_code(404);

$displayTitle = $course['Title'] ?? 'Course Details';
$displayPrice = $course['Price'] ?? '0';
$displaySubtitle = $course['Subtitle'] ?? 'Master your skills with Expert Instructors | Comprehensive Training for All Levels';

$phone = $contact['phone'];
$email = $contact['email'];
$address = strip_tags($contact['address']);

$seoTitle = $course['SEO-Title'] ?? ($displayTitle . ' | Course at SmartStudyPro');
$seoDescription = $course['SEO-Description'] ?? strip_tags($course['Description'] ?? $displaySubtitle);
$siteUrl = \App\Core\Url::site();

$cImgData = $course['Image'] ?? null;
$ogImage = isset($cImgData['url']) ? $siteUrl . '/' . ltrim($cImgData['url'], '/') : $siteUrl . '/assets/img/course-1.jpg';
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

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $siteUrl ?>/courses/<?= rawurlencode((string) ($course['slug'] ?? '')) ?>">
  <link rel="canonical" href="<?= $siteUrl ?>/courses/<?= rawurlencode((string) ($course['slug'] ?? '')) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $ogImage ?>">

  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="twitter:image" content="<?= $ogImage ?>">

  <?php if ($course): ?>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Course",
    "name": "<?= htmlspecialchars((string) $displayTitle, ENT_QUOTES, 'UTF-8') ?>",
    "description": "<?= htmlspecialchars(strip_tags($course['Description'] ?? $displaySubtitle)) ?>",
    "provider": {
      "@type": "Organization",
      "name": "SmartStudyPro",
      "sameAs": "<?= $siteUrl ?>"
    },
    "offers": {
      "@type": "Offer",
      "category": "Paid",
      "price": "<?= $displayPrice ?>",
      "priceCurrency": "UGX"
    }
  }
  </script>
  <?php endif; ?>

  <link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/Smart_Study_Logo_Fin-removebg-preview.png'), ENT_QUOTES, 'UTF-8') ?>" rel="apple-touch-icon">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/aos/aos.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

  <link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">

</head>

<body class="ssp-course-details">

  <?php include 'nav.php'; ?>

  <main id="main-content" class="main">

    <div class="page-title-ssp text-center">
      <div class="container" data-aos="fade-up">
        <h1 class="fw-bold mb-2"><?= htmlspecialchars($displayTitle) ?></h1>
        <p class="mb-0 opacity-75"><?= htmlspecialchars($displaySubtitle) ?></p>
      </div>
    </div>

    <?php if (!empty($course['Offerings'])): ?>
    <section class="section py-4"><div class="container"><div class="row justify-content-center"><div class="col-lg-10"><div class="p-4 ssp-card-box"><h2 class="h4 fw-bold mb-3" class="ssp-brand-text">Choose how you want to learn</h2><div class="row g-3">
      <?php foreach ($course['Offerings'] as $offering): ?>
      <div class="col-md-4"><div class="h-100 p-3 rounded border d-flex flex-column"><strong><?= htmlspecialchars(match ($offering['mode']) {'self_paced' => 'Self-paced in-app', 'live_online' => 'Live online', 'in_person' => 'In person', default => 'Course'}) ?></strong><div class="small text-muted mt-1"><?= htmlspecialchars($offering['title']) ?></div><div class="fw-bold mt-2">UGX <?= number_format((int) $offering['price_ugx']) ?></div><?php if ($offering['capacity'] !== null): ?><div class="small text-muted">Capacity: <?= (int) $offering['capacity'] ?></div><?php endif; ?><form method="post" action="<?= \App\Core\Url::to('/cart/add') ?>" class="mt-auto pt-3"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="type" value="course"><input type="hidden" name="id" value="<?= $courseId ?>"><input type="hidden" name="offering_id" value="<?= (int)$offering['id'] ?>"><button class="btn-ssp-primary w-100" type="submit"><?= (int)$offering['price_ugx'] === 0 ? 'Join this option' : 'Add option to cart' ?></button></form></div></div>
      <?php endforeach; ?>
    </div></div></div></div></section>
    <?php endif; ?>

    <?php if (trim(strip_tags((string) ($course['Description'] ?? '') . (string) ($course['Content'] ?? ''))) !== ''): ?>
    <section class="section py-4"><div class="container"><div class="row justify-content-center"><div class="col-lg-10"><article class="p-4 p-md-5 ssp-card-box"><h2 class="h3 fw-bold mb-3" class="ssp-brand-text">About this course</h2><?php if (trim((string) ($course['Description'] ?? '')) !== ''): ?><div class="course-description mb-4"><?= $course['Description'] ?></div><?php endif; ?><?php if (trim((string) ($course['Content'] ?? '')) !== ''): ?><h3 class="h5 fw-bold mb-3" class="ssp-brand-text">Course overview</h3><div class="course-overview"><?= $course['Content'] ?></div><?php endif; ?></article></div></div></div></section>
    <?php endif; ?>

    <?php if (empty($course['Offerings'])): ?><section class="section pt-4 pb-0"><div class="container"><div class="row justify-content-center"><div class="col-lg-8"><div class="p-4 p-md-5 ssp-card-box"><div class="small text-uppercase fw-bold mb-2" class="ssp-accent-text">Start learning</div><h2 class="h3 fw-bold mb-2">Join this course</h2><p class="text-muted mb-3">Access the structured lessons, learning resources and knowledge checks in your student dashboard.</p><?php if ((int) $displayPrice === 0): ?><a class="btn-ssp-primary text-decoration-none" href="<?= \App\Core\Url::to('/learn/' . rawurlencode((string) ($course['slug'] ?? ''))) ?>">Start this free course</a><?php else: ?><div class="d-flex flex-wrap align-items-center justify-content-between gap-3"><strong class="fs-4" class="ssp-accent-text">UGX <?= number_format((int) $displayPrice) ?></strong><form method="post" action="<?= \App\Core\Url::to('/cart/add') ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="type" value="course"><input type="hidden" name="id" value="<?= $courseId ?>"><button class="btn-ssp-primary" type="submit">Add course to cart</button></form></div><?php endif; ?></div></div></div></div></section>

    <?php endif; ?>
    <section id="starter-section" class="starter-section section py-5">
      <div class="container section-title text-center mb-4" data-aos="fade-up">
        <h2 class="fw-bold" class="ssp-brand-text"><?= htmlspecialchars($displayTitle) ?></h2>
        <p class="ssp-muted-text">Book Your Session</p>
      </div>

      <div class="container" data-aos="fade-up">
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <div class="p-4 p-md-5 ssp-card-box">
              <h3 class="text-center mb-4 fw-bold" class="ssp-brand-text">Reservation Form</h3>
              
              <form action="<?= htmlspecialchars(\App\Core\Url::to('/booking'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="course_id" value="<?= htmlspecialchars((string) $courseId, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="price" value="<?= htmlspecialchars((string) $displayPrice, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="service" value="<?= htmlspecialchars($displayTitle) ?>">
                  
                  <div class="row">
                      <div class="col-md-6 form-group mb-3">
                          <label for="name" class="form-label small fw-semibold">Full Name</label>
                          <input type="text" name="name" class="form-control rounded-3" id="name" placeholder="John Doe" required>
                      </div>
                      <div class="col-md-6 form-group mb-3">
                          <label for="email" class="form-label small fw-semibold">Email Address</label>
                          <input type="email" name="email" class="form-control rounded-3" id="email" placeholder="example@mail.com" required>
                      </div>
                  </div>
                  <div class="form-group mb-3">
                      <label for="phone" class="form-label small fw-semibold">Phone Number</label>
                      <input type="text" name="phone" class="form-control rounded-3" id="phone" placeholder="+256..." required>
                  </div>
                  <div class="form-group mb-3">
                      <label for="date" class="form-label small fw-semibold">Preferred Starting Date</label>
                      <input type="date" name="date" class="form-control rounded-3" id="date" required>
                  </div>
                  <div class="form-group mb-3">
                      <label for="message" class="form-label small fw-semibold">Additional Notes</label>
                      <textarea name="message" class="form-control rounded-3" id="message" rows="4" placeholder="Tell us about your learning goals..."></textarea>
                  </div>
                  <div class="form-group my-4 text-center">
                      <div class="p-3 price-box rounded-3 border">
                          <p class="mb-0"><strong>Total Course Fee:</strong> <span class="fw-bold fs-5 ms-2" class="ssp-accent-text">UGX <?= number_format(floatval($displayPrice ?? 0)) ?></span></p>
                      </div>
                  </div>
                  <div class="text-center mt-4">
                      <button type="submit" class="btn-ssp-primary w-100 py-3">Confirm Booking</button>
                  </div>
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
          <a href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-footer-brand text-decoration-none mb-3 d-inline-block">
            SmartStudy<span>Pro</span>
          </a>
          <p class="small text-light opacity-75 mb-3">Study Made Simple, Success Made Sure.</p>
          
          <div class="small mb-3">
            <p class="mb-1"><i class="bi bi-geo-alt-fill me-2" class="ssp-accent-text"></i><?= htmlspecialchars($address) ?></p>
            <p class="mb-1"><i class="bi bi-telephone-fill me-2" class="ssp-accent-text"></i><?= htmlspecialchars($phone) ?></p>
            <p class="mb-1"><i class="bi bi-envelope-fill me-2" class="ssp-accent-text"></i><?= htmlspecialchars($email) ?></p>
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
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/aos/aos.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>