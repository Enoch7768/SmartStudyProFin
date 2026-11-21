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
$courseImage = (string) ($cImgData['url'] ?? 'assets/img/course-1.jpg');
$courseCategory = (string) ($course['Category'] ?? 'Learning programme');
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

  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

  <link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">

  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/public-pages.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>

<body class="ssp-course-details course-detail-page">

  <?php include 'nav.php'; ?>

  <main id="main-content" class="main ssp-course-detail-page">
    <?php if ($course): ?>
      <section class="ssp-course-hero">
        <div class="container">
          <div class="ssp-course-hero-copy">
            <a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-course-back">← All courses</a>
            <span class="ssp-eyebrow"><?= htmlspecialchars($courseCategory, ENT_QUOTES, 'UTF-8') ?></span>
            <h1><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($displaySubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="ssp-course-hero-actions">
              <a href="#course-options" class="ssp-course-primary-link"><?= $course['Offerings'] ? 'Choose your learning format' : ((int) $displayPrice === 0 ? 'Start learning' : 'Enrol in this course') ?> <span aria-hidden="true">↓</span></a>
              <span class="ssp-course-format-count"><?= count($course['Offerings']) ?> published <?= count($course['Offerings']) === 1 ? 'option' : 'options' ?></span>
            </div>
          </div>
          <figure class="ssp-course-hero-image"><img src="<?= htmlspecialchars($courseImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>"></figure>
        </div>
      </section>

      <?php if ($course['Offerings']): ?>
        <section id="course-options" class="ssp-course-options section">
          <div class="container">
            <div class="ssp-section-heading"><span class="ssp-eyebrow">Choose your pathway</span><h2>One goal. A learning format that fits.</h2><p>Compare the published ways to take this course and choose the option that works best for you.</p></div>
            <div class="ssp-offering-grid">
              <?php foreach ($course['Offerings'] as $offering): ?>
                <?php
                  $offeringMode = (string) ($offering['mode'] ?? '');
                  $offeringLabel = match ($offeringMode) {'self_paced' => 'Self-paced learning', 'live_online' => 'Live online classes', 'in_person' => 'In-person learning', default => 'Course option'};
                  $offeringIcon = match ($offeringMode) {'self_paced' => 'bi-laptop', 'live_online' => 'bi-camera-video', 'in_person' => 'bi-people', default => 'bi-book'};
                  $offeringDescription = trim((string) ($offering['description'] ?? ''));
                  if ($offeringDescription === '') $offeringDescription = match ($offeringMode) {'self_paced' => 'Work through the course lessons on your own schedule.', 'live_online' => 'Learn through live online sessions with an instructor.', 'in_person' => 'Take part in face-to-face learning sessions.', default => 'Choose this published course option.'};
                ?>
                <article class="ssp-offering-card">
                  <div class="ssp-offering-icon"><i class="bi <?= $offeringIcon ?>" aria-hidden="true"></i></div>
                  <span class="ssp-offering-kicker"><?= htmlspecialchars($offeringLabel, ENT_QUOTES, 'UTF-8') ?></span>
                  <h3><?= htmlspecialchars((string) $offering['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                  <p><?= htmlspecialchars($offeringDescription, ENT_QUOTES, 'UTF-8') ?></p>
                  <div class="ssp-offering-price"><span>Course fee</span><strong><?= (int) $offering['price_ugx'] === 0 ? 'Free' : 'UGX ' . number_format((int) $offering['price_ugx']) ?></strong></div>
                  <form method="post" action="<?= htmlspecialchars(\App\Core\Url::to('/cart/add'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="type" value="course">
                    <input type="hidden" name="id" value="<?= $courseId ?>">
                    <input type="hidden" name="offering_id" value="<?= (int) $offering['id'] ?>">
                    <button type="submit" class="ssp-course-enrol-button"><?= (int) $offering['price_ugx'] === 0 ? 'Choose this option' : 'Add to cart' ?> <span aria-hidden="true">→</span></button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php else: ?>
        <section id="course-options" class="ssp-course-options section">
          <div class="container"><div class="ssp-course-single-offer">
            <div><span class="ssp-eyebrow">Start learning</span><h2>Ready to take the next step?</h2><p>Explore the structured lessons and learning resources for this course.</p></div>
            <div class="ssp-single-offer-action">
              <?php if ((int) $displayPrice === 0): ?>
                <strong>Free course</strong><a href="<?= htmlspecialchars(\App\Core\Url::to('/learn/' . rawurlencode((string) ($course['slug'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>" class="ssp-course-enrol-button">Start this course <span aria-hidden="true">→</span></a>
              <?php else: ?>
                <strong>UGX <?= number_format((int) $displayPrice) ?></strong><form method="post" action="<?= htmlspecialchars(\App\Core\Url::to('/cart/add'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="type" value="course"><input type="hidden" name="id" value="<?= $courseId ?>"><button class="ssp-course-enrol-button" type="submit">Add course to cart <span aria-hidden="true">→</span></button></form>
              <?php endif; ?>
            </div>
          </div></div>
        </section>
      <?php endif; ?>

      <?php if (trim(strip_tags((string) ($course['Description'] ?? '') . (string) ($course['Content'] ?? ''))) !== ''): ?>
        <section class="ssp-course-about section">
          <div class="container">
            <div class="ssp-course-about-inner">
              <div class="ssp-course-about-heading"><span class="ssp-eyebrow">Inside the course</span><h2>Build knowledge you can put to work.</h2></div>
              <div class="ssp-course-about-content">
                <?php if (trim((string) ($course['Description'] ?? '')) !== ''): ?><div class="course-description"><?= $course['Description'] ?></div><?php endif; ?>
                <?php if (trim((string) ($course['Content'] ?? '')) !== ''): ?><div class="course-overview"><?= $course['Content'] ?></div><?php endif; ?>
              </div>
            </div>
          </div>
        </section>
      <?php endif; ?>
    <?php else: ?>
      <section class="ssp-course-not-found"><div class="container"><span class="ssp-eyebrow">Course unavailable</span><h1>We couldn’t find this course.</h1><p>It may have been unpublished or the link may have changed.</p><a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">Browse all courses</a></div></section>
    <?php endif; ?>
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
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>