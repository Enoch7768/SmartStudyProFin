<?php

declare(strict_types=1);

$root = __DIR__;
$rootPath = realpath($root) ?: $root;
require_once $root . '/app/Core/Url.php';
$basePath = PHP_SAPI === 'cli-server' ? '' : \App\Core\Url::basePath();
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$path = '/' . trim(preg_replace('#/+#', '/', $requestPath) ?: '/', '/');
if ($path !== '/') $path = rtrim($path, '/');

if (preg_match('#^/install(?:/|$)#i', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Page not found');
}

if (
    preg_match('#^/(?:app|bootstrap|config|database|scripts|storage|cms|docs|tests|vendor|\.git|\.github)(?:/|$)#i', $path)
    || preg_match('#(?:^|/)\.(?!well-known(?:/|$))#i', $path)
    || preg_match('/\.(?:sqlite|sqlite3|db|log|key|sql|bak|backup|old|orig|swp|zip|tar|gz|dump|sqlite-wal|sqlite-shm|db-wal|db-shm)$/i', $path)
    || in_array($path, ['/composer.json', '/composer.lock', '/phpunit.xml', '/nav.php', '/auth.php'], true)
) {
    http_response_code(404);
    exit('Page not found');
}

if (PHP_SAPI === 'cli-server' && $path !== '/' && $path !== '/index.php' && $path !== '/favicon.ico') {
    $requestedFile = realpath($root . $path);
    if ($requestedFile !== false && str_starts_with($requestedFile, $rootPath . DIRECTORY_SEPARATOR) && is_file($requestedFile)) {
        if (strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) === 'php') {
            $requestedUrl = $basePath . $path;
            $_SERVER['SCRIPT_NAME'] = $requestedUrl;
            $_SERVER['PHP_SELF'] = $requestedUrl;
            $_SERVER['SCRIPT_FILENAME'] = $requestedFile;
            require $requestedFile;
            return;
        }
        return false;
    }
}

$aliases = [
    '/learning' => '/learn',
    '/study' => '/learn',
    '/course-details' => '/courses',
    '/product-details' => '/products',
];

if (isset($aliases[$path]) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    $destination = $basePath . $aliases[$path];
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') $destination .= '?' . $query;
    header('Location: ' . $destination, true, 301);
    exit;
}

$routes = [
    '/about' => 'about.php',
    '/accessibility' => 'accessibility.php',
    '/achievements' => 'achievements.php',
    '/admin' => 'admin.php',
    '/assistant' => 'assistant.php',
    '/booking' => 'booking.php',
    '/cart' => 'cart.php',
    '/cart/add' => 'cart-add.php',
    '/certificates' => 'certificates.php',
    '/checkout' => 'checkout.php',
    '/contact' => 'contact.php',
    '/cookies' => 'cookies.php',
    '/courses' => 'courses.php',
    '/download' => 'download.php',
    '/eula' => 'eula.php',
    '/favicon.ico' => 'favicon.php',
    '/legal' => 'legal.php',
    '/legal-consent' => 'legal-consent.php',
    '/login' => 'login.php',
    '/logout' => 'logout.php',
    '/learn' => 'learning.php',
    '/media' => 'media.php',
    '/notifications' => 'notifications.php',
    '/meetings' => 'meetings.php',
    '/instructor' => 'instructor.php',
    '/admin/meetings' => 'admin/meetings.php',
    '/admin/shipping' => 'admin/shipping.php',
    '/admin/messages' => 'admin/messages.php',
    '/orders' => 'orders.php',
    '/payment-choice' => 'payment-choice.php',
    '/payment-demo' => 'payment-demo.php',
    '/payment-return' => 'payment-return.php',
    '/payment-start' => 'payment-start.php',
    '/privacy' => 'privacy.php',
    '/products' => 'products.php',
    '/profile' => 'profile.php',
    '/receipt' => 'receipt.php',
    '/refunds' => 'refunds.php',
    '/register' => 'register.php',
    '/reset' => 'reset.php',
    '/stream' => 'stream.php',
    '/terms' => 'terms.php',
    '/theme-preference' => 'theme-preference.php',
    '/verify-certificate' => 'verify-certificate.php',
    '/view' => 'view.php',
];

$script = $routes[$path] ?? null;


if ($script === null && preg_match('#^/courses/([a-z0-9][a-z0-9-]*)$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $script = 'course-details.php';
}

if ($script === null && preg_match('#^/products/([a-z0-9][a-z0-9-]*)$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $script = 'product-details.php';
}

if ($script === null && preg_match('#^/learn/([a-z0-9][a-z0-9-]*)(?:/([0-9]+))?$#i', $path, $matches)) {
    $_GET['course_slug'] = $matches[1];
    if (isset($matches[2])) $_GET['lesson_id'] = $matches[2];
    $script = 'study.php';
}

if ($script !== null) {
    $target = $root . DIRECTORY_SEPARATOR . $script;
    if (!is_file($target)) {
        http_response_code(404);
        exit('Page not found');
    }

    $targetUrl = $basePath . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $script);
    $_SERVER['SCRIPT_NAME'] = $targetUrl;
    $_SERVER['PHP_SELF'] = $targetUrl;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    require $target;
    return;
}

if ($path !== '/' && $path !== '/index.php') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Page not found');
}

$_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;


require_once __DIR__ . '/bootstrap/app.php';

$pdo = \App\Core\Database::connect();
$content = new \App\Cms\PublicContent($pdo);
$home = $content->home();
$courses = $content->courses();
$products = $content->products();
$contact = $content->contact();

$phone = $contact['phone'];
$email = $contact['email'];
$address = strip_tags($contact['address']);

$seoTitle = $home['SEO-Title'] ?? ($home['Hero-Title'] ?: 'SmartStudyPro | Study Made Simple, Success Made Sure');
$seoDescription = $home['SEO-Description'] ?? 'Empowering students in Uganda with innovative learning solutions, professional courses, and academic guidance.';
$siteUrl = \App\Core\Url::site();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <base href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta name="keywords" content="online courses Uganda, e-learning, SmartStudyPro, education, tutoring Kampala">
  <meta name="author" content="SmartStudyPro">

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $siteUrl ?>">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:image" content="<?= $siteUrl ?>/Smart_Study_Logo_Fin-removebg-preview.png">

  <script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'EducationalOrganization',
    'name' => 'SmartStudyPro',
    'url' => $siteUrl,
    'logo' => $siteUrl . '/Smart_Study_Logo_Fin-removebg-preview.png',
    'slogan' => 'Study Made Simple, Success Made Sure',
    'contactPoint' => [
      '@type' => 'ContactPoint',
      'telephone' => $phone,
      'contactType' => 'customer service',
      'email' => $email
    ],
    'address' => [
      '@type' => 'PostalAddress',
      'addressLocality' => 'Kampala',
      'addressCountry' => 'UG'
    ]
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>

  <link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/Smart_Study_Logo_Fin-removebg-preview.png'), ENT_QUOTES, 'UTF-8') ?>" rel="apple-touch-icon">
  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/homepage.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet"><script>
    (function () {
      try {
        const savedTheme = window.localStorage.getItem('ssp-theme');
        const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (error) {}
    })();
  </script>
<link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>

<body class="index-page">

  <?php include __DIR__ . '/nav.php'; ?>

  <main id="main-content" class="main ssp-home">
    <?php
      $heroMedia = $home['Hero-Image'] ?? null;
      $heroImage = !empty($heroMedia['url']) ? \App\Core\Url::to((string) $heroMedia['url']) : \App\Core\Url::to('/assets/img/IMG-20241120-WA0018.jpg');
      $courseModeLabels = ['self_paced' => 'Self-paced', 'live_online' => 'Live online', 'in_person' => 'In person'];
    ?>
    <section class="home-hero" aria-labelledby="home-title">
      <div class="home-hero-inner">
        <div class="home-hero-copy">
          <p class="home-eyebrow">A smarter way to learn in Uganda</p>
          <h1 id="home-title"><?= $home['Hero-Title'] ?? $home['Title'] ?? 'Learn with purpose.<br>Build what comes next.' ?></h1>
          <p class="home-hero-description"><?= $home['Hero-Subtitle'] ?? 'Flexible courses, expert guidance and practical resources to help you move forward with confidence.' ?></p>
          <div class="home-hero-actions">
            <a class="home-button home-button-primary" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">Explore courses <span aria-hidden="true">↗</span></a>
            <a class="home-text-link" href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>">Discover our approach <span aria-hidden="true">→</span></a>
          </div>
          <div class="home-hero-note"><span class="home-note-mark" aria-hidden="true">✳</span><span>Learn at your pace, in your place, with support when you need it.</span></div>
        </div>
        <div class="home-hero-visual">
          <div class="home-hero-image">
            <img src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="Learning at SmartStudyPro" fetchpriority="high">
          </div>
          <div class="home-image-caption"><span>01 / YOUR NEXT CHAPTER</span><strong>Make learning<br>work for you.</strong></div>
          <div class="home-image-stamp" aria-hidden="true"><span>SMART</span><strong>+</strong><span>STUDY</span></div>
        </div>
      </div>
      <div class="home-hero-index" aria-hidden="true"><span>SMARTSTUDYPRO</span><span>STUDY MADE SIMPLE · SUCCESS MADE SURE</span></div>
    </section>

    <section class="home-intro home-section" aria-labelledby="intro-title">
      <div class="home-container home-intro-grid">
        <div class="home-intro-media">
          <img src="<?= htmlspecialchars(\App\Core\Url::to('/assets/img/20240102_164438.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Students learning together at SmartStudyPro" loading="lazy">
          <span class="home-image-label">Learning that moves with you</span>
        </div>
        <div class="home-intro-copy">
          <p class="home-eyebrow">More than a course library</p>
          <h2 id="intro-title">A learning journey shaped around <em>real life.</em></h2>
          <p>Build knowledge, develop practical skills and get the guidance you need through one connected learning experience.</p>
          <ul class="home-check-list">
            <li><span aria-hidden="true">↗</span><div><strong>Learn with structure</strong><small>Clear courses, useful resources and a path you can follow.</small></div></li>
            <li><span aria-hidden="true">↗</span><div><strong>Get support when it matters</strong><small>Connect with learning support and live sessions where available.</small></div></li>
            <li><span aria-hidden="true">↗</span><div><strong>Keep your progress together</strong><small>Return to your learning, bookings and resources from one account.</small></div></li>
          </ul>
          <a class="home-inline-link" href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>">Our story and approach <span aria-hidden="true">→</span></a>
        </div>
      </div>
    </section>

    <section class="home-pathways home-section" aria-labelledby="pathways-title">
      <div class="home-container">
        <div class="home-section-heading home-heading-split">
          <div><p class="home-eyebrow">Find your learning rhythm</p><h2 id="pathways-title">Choose how you<br><em>want to grow.</em></h2></div>
          <p>Different goals need different ways to learn. Explore the format that fits your schedule, your subject and the way you work best.</p>
        </div>
        <div class="home-pathway-list">
          <a class="home-pathway" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="home-pathway-no">01</span><span class="home-pathway-icon" aria-hidden="true">◷</span><span class="home-pathway-copy"><strong>Self-paced learning</strong><small>Study lessons and resources on your own schedule.</small></span><span class="home-pathway-arrow" aria-hidden="true">↗</span>
          </a>
          <a class="home-pathway" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="home-pathway-no">02</span><span class="home-pathway-icon" aria-hidden="true">◎</span><span class="home-pathway-copy"><strong>Live online sessions</strong><small>Learn with an instructor through scheduled online classes.</small></span><span class="home-pathway-arrow" aria-hidden="true">↗</span>
          </a>
          <a class="home-pathway" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="home-pathway-no">03</span><span class="home-pathway-icon" aria-hidden="true">⌖</span><span class="home-pathway-copy"><strong>In-person learning</strong><small>Explore practical, face-to-face learning opportunities.</small></span><span class="home-pathway-arrow" aria-hidden="true">↗</span>
          </a>
        </div>
      </div>
    </section>

    <section id="courses" class="home-courses home-section" aria-labelledby="courses-title">
      <div class="home-container">
        <div class="home-section-heading home-heading-split">
          <div><p class="home-eyebrow">Explore the catalogue</p><h2 id="courses-title">Find a course<br><em>worth your time.</em></h2></div>
          <div class="home-heading-aside"><p>Browse learning programmes and find the next skill, subject or qualification to work towards.</p><a class="home-inline-link" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">View all courses <span aria-hidden="true">→</span></a></div>
        </div>
        <?php if ($courses): ?>
          <div class="home-course-grid">
            <?php foreach (array_slice($courses, 0, 3) as $course): ?>
              <?php
                $courseImage = $course['Image']['url'] ?? '/assets/img/course-1.jpg';
                $courseUrl = \App\Core\Url::to('/courses/' . rawurlencode((string) ($course['slug'] ?? $course['_id'])));
                $modes = array_values(array_unique(array_map(static fn (array $offering): string => $courseModeLabels[$offering['mode']] ?? '', $course['Offerings'] ?? [])));
                $modes = array_values(array_filter($modes));
              ?>
              <article class="home-course">
                <a class="home-course-image" href="<?= htmlspecialchars($courseUrl, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars(\App\Core\Url::to((string) $courseImage), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($course['Title'] ?? 'Course'), ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><span class="home-course-category"><?= htmlspecialchars((string) ($course['Category'] ?? 'Course'), ENT_QUOTES, 'UTF-8') ?></span></a>
                <div class="home-course-content">
                  <div class="home-course-meta"><span><?= $modes ? htmlspecialchars(implode(' · ', $modes), ENT_QUOTES, 'UTF-8') : 'Explore programme' ?></span><strong>UGX <?= number_format((int) ($course['Price'] ?? 0)) ?></strong></div>
                  <h3><a href="<?= htmlspecialchars($courseUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($course['Title'] ?? 'Untitled course'), ENT_QUOTES, 'UTF-8') ?></a></h3>
                  <p><?= htmlspecialchars(mb_strimwidth(trim(strip_tags((string) ($course['Description'] ?? ''))), 0, 132, '…'), ENT_QUOTES, 'UTF-8') ?></p>
                  <a class="home-course-link" href="<?= htmlspecialchars($courseUrl, ENT_QUOTES, 'UTF-8') ?>">Explore course <span aria-hidden="true">↗</span></a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="home-empty"><strong>New learning opportunities are on the way.</strong><p>Visit the course catalogue to see the latest programmes.</p><a class="home-inline-link" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">Browse courses <span aria-hidden="true">→</span></a></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="home-store home-section" aria-labelledby="store-title">
      <div class="home-container">
        <div class="home-store-heading">
          <div><p class="home-eyebrow">The learning store</p><h2 id="store-title">Tools and resources<br><em>for the work ahead.</em></h2></div>
          <p>Explore digital learning materials and physical products selected to support your study and everyday progress.</p>
          <a class="home-button home-button-outline" href="<?= htmlspecialchars(\App\Core\Url::to('/products'), ENT_QUOTES, 'UTF-8') ?>">Explore the store <span aria-hidden="true">↗</span></a>
        </div>
        <?php if ($products): ?>
          <div class="home-product-rail">
            <?php foreach (array_slice($products, 0, 4) as $product): ?>
              <?php
                $productImage = $product['Image']['url'] ?? '/assets/img/course-2.jpg';
                $productUrl = \App\Core\Url::to('/products/' . rawurlencode((string) ($product['slug'] ?? $product['_id'])));
                $productType = strtolower((string) ($product['ProductType'] ?? 'digital'));
              ?>
              <article class="home-product">
                <a class="home-product-image" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars(\App\Core\Url::to((string) $productImage), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($product['Title'] ?? 'Product'), ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><span><?= htmlspecialchars((string) ($product['ProductType'] ?? 'Resource'), ENT_QUOTES, 'UTF-8') ?></span></a>
                <div class="home-product-content"><h3><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($product['Title'] ?? 'Learning resource'), ENT_QUOTES, 'UTF-8') ?></a></h3><p><?= htmlspecialchars(mb_strimwidth(trim(strip_tags((string) ($product['Description'] ?? ''))), 0, 104, '…'), ENT_QUOTES, 'UTF-8') ?></p><div class="home-product-bottom"><strong>UGX <?= number_format((int) ($product['Price'] ?? 0)) ?></strong><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="View <?= htmlspecialchars((string) ($product['Title'] ?? 'product'), ENT_QUOTES, 'UTF-8') ?>">View <span aria-hidden="true">↗</span></a></div></div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="home-store-empty">The store is being refreshed. Browse the catalogue for available resources.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="home-closing">
      <div class="home-container home-closing-inner"><div><p class="home-eyebrow">Your next step starts here</p><h2>Ready to make<br><em>progress?</em></h2></div><div><p>Find a course, discover a resource or get in touch with the SmartStudyPro team.</p><div class="home-closing-actions"><a class="home-button home-button-primary" href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>">Start exploring <span aria-hidden="true">↗</span></a><a class="home-text-link" href="<?= htmlspecialchars(\App\Core\Url::to('/contact'), ENT_QUOTES, 'UTF-8') ?>">Talk to us <span aria-hidden="true">→</span></a></div></div></div>
    </section>
  </main>

  <footer id="footer" class="ssp-footer pt-5 pb-3">
    <div class="container footer-top mb-4">
      <div class="row gy-4">
        
        <div class="col-12 col-md-6 col-lg-4">
          <a href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-footer-brand text-decoration-none mb-3 d-inline-block">
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

        <div class="col-12 col-sm-6 col-md-6 col-lg-3">
          <h5 class="text-white fw-bold mb-3">Quick Navigation</h5>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-white opacity-90">Home</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-white opacity-90">About Us</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-white opacity-90">Courses</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/products'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-white opacity-90">Products & Materials</a></li>
            <li class="mb-2"><a href="<?= htmlspecialchars(\App\Core\Url::to('/contact'), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-white opacity-90">Contact Us</a></li>
          </ul>
        </div>

        <div class="col-12 col-sm-6 col-md-12 col-lg-5">
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
      <div class="small mb-3 d-flex flex-wrap justify-content-center gap-3">
        <a href="<?= htmlspecialchars(\App\Core\Url::to('/privacy'), ENT_QUOTES, 'UTF-8') ?>" class="text-white text-decoration-none">Privacy</a>
        <a href="<?= htmlspecialchars(\App\Core\Url::to('/terms'), ENT_QUOTES, 'UTF-8') ?>" class="text-white text-decoration-none">Terms of Use</a>
        <a href="<?= htmlspecialchars(\App\Core\Url::to('/cookies'), ENT_QUOTES, 'UTF-8') ?>" class="text-white text-decoration-none">Cookies</a>
        <a href="<?= htmlspecialchars(\App\Core\Url::to('/refunds'), ENT_QUOTES, 'UTF-8') ?>" class="text-white text-decoration-none">Refunds</a>
        <a href="<?= htmlspecialchars(\App\Core\Url::to('/accessibility'), ENT_QUOTES, 'UTF-8') ?>" class="text-white text-decoration-none">Accessibility</a>
        <a href="<?= htmlspecialchars(\App\Core\Url::to('/eula'), ENT_QUOTES, 'UTF-8') ?>" class="text-white text-decoration-none">Client Software Licence</a>
      </div>
      <p class="mb-0">&copy; <?= date('Y') ?> <strong>SmartStudyPro</strong>. All Rights Reserved. Empowering Education in Uganda.</p>
    </div>
  </footer>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center text-decoration-none"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>



</body>
</html>