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
    '/admin/meetings' => 'admin/meetings.php',
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

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/aos/aos.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

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
      --ssp-header-bg: rgba(255, 255, 255, 0.98);
      --ssp-card-bg: #FFFFFF;
      --ssp-card-border: #CBD5E1;
      --ssp-badge-bg: #E0E7FF;
      --ssp-badge-text: #0C086B;
    }

    /* Enhanced Dark Mode Theme Variables (High Contrast) */
    [data-theme="dark"] {
      --ssp-navy: #C7D2FE;
      --ssp-navy-dark: #03021D;
      --ssp-orange: #FF8A1A;
      --ssp-orange-hover: #FF9B3B;
      --ssp-bg-soft: #0F172A;
      --ssp-text-main: #F8FAFC;
      --ssp-text-muted: #E2E8F0;
      --ssp-bg-page: #0B0F17;
      --ssp-header-bg: rgba(11, 15, 23, 0.98);
      --ssp-card-bg: #111827;
      --ssp-card-border: #475569;
      --ssp-badge-bg: #1E1B4B;
      --ssp-badge-text: #E0E7FF;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ssp-text-main);
      background-color: var(--ssp-bg-page);
      overflow-x: hidden;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    h1, h2, h3, h4, h5, .brand-font {
      font-family: 'Outfit', sans-serif;
    }

    .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .ssp-header {
      background: var(--ssp-header-bg);
      backdrop-filter: blur(12px);
      border-bottom: 2px solid rgba(12, 8, 107, 0.12);
      transition: all 0.3s ease;
    }

    /* Base Navigation Styling */
    .navmenu ul {
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
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

    /* Desktop Navigation (XL screens and above) */
    @media (min-width: 1200px) {
      .navmenu ul {
        display: flex !important;
        align-items: center !important;
        gap: 24px;
      }
    }

    /* Keyframes for Mobile Menu Open Animation */
    @keyframes mobileNavSlideDown {
      0% {
        opacity: 0;
        transform: translateY(-18px) scale(0.97);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* Mobile / Tablet Navigation Drawer with Opening Animation */
    @media (max-width: 1199.98px) {
      .navmenu ul {
        display: none;
        position: fixed;
        top: 70px;
        right: 15px;
        left: 15px;
        padding: 24px !important;
        background: var(--ssp-card-bg);
        border-radius: 16px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        border: 1px solid var(--ssp-card-border);
        z-index: 9999;
      }

      .mobile-nav-active .navmenu ul {
        display: flex !important;
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
        animation: mobileNavSlideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      }

      .mobile-nav-toggle {
        cursor: pointer;
        color: var(--ssp-navy);
        transition: transform 0.25s ease, color 0.25s ease;
      }

      .mobile-nav-active .mobile-nav-toggle {
        transform: rotate(90deg);
        color: var(--ssp-orange);
      }
    }

    .header-icon-link {
      color: var(--ssp-navy);
      padding: 6px 10px;
      border-radius: 8px;
      transition: background-color 0.2s ease, color 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: none;
      cursor: pointer;
    }

    .header-icon-link:hover {
      color: var(--ssp-orange);
      background-color: var(--ssp-bg-soft);
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
      display: inline-block;
      text-align: center;
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
      display: inline-block;
      text-align: center;
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

    .ssp-hero {
      position: relative;
      padding: 140px 0 100px;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .ssp-hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(7, 4, 67, 0.95) 0%, rgba(3, 2, 29, 0.92) 100%);
      z-index: 1;
    }

    .ssp-hero .container {
      position: relative;
      z-index: 2;
    }

    .hero-tagline {
      display: inline-block;
      color: #FFB066;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      font-size: 0.875rem;
      background: rgba(255, 122, 0, 0.2);
      padding: 6px 16px;
      border-radius: 30px;
      border: 1px solid rgba(255, 122, 0, 0.5);
      max-width: 100%;
      word-wrap: break-word;
    }

    .pixel-decor {
      display: inline-flex;
      gap: 4px;
      margin-bottom: 12px;
    }

    .pixel-decor span {
      width: 8px;
      height: 8px;
      border-radius: 2px;
    }

    .pixel-decor .px-1 { background-color: var(--ssp-orange); }
    .pixel-decor .px-2 { background-color: #60A5FA; }
    .pixel-decor .px-3 { background-color: #FFFFFF; }

    .ssp-course-card {
      border: 1.5px solid var(--ssp-card-border);
      border-radius: 16px;
      overflow: hidden;
      background: var(--ssp-card-bg);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .ssp-course-card:hover {
      transform: translateY(-8px);
      border-color: var(--ssp-orange);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
    }

    .ssp-card-badge {
      background: var(--ssp-badge-bg);
      color: var(--ssp-badge-text);
      font-size: 0.75rem;
      font-weight: 800;
      padding: 4px 12px;
      border-radius: 20px;
      text-transform: uppercase;
    }

    .ssp-price-tag {
      color: var(--ssp-orange);
      font-weight: 800;
      font-size: 1.15rem;
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

    /* Media Queries for Fine-Tuned Responsiveness */
    @media (max-width: 991.98px) {
      .ssp-hero {
        padding: 100px 0 70px;
      }
      .display-4 {
        font-size: calc(1.475rem + 2.7vw);
      }
    }

    @media (max-width: 575.98px) {
      .ssp-hero {
        padding: 80px 0 50px;
      }
      .display-4 {
        font-size: 1.85rem;
      }
      .hero-tagline {
        font-size: 0.75rem;
        padding: 5px 12px;
      }
      .btn-ssp-primary, 
      .ssp-hero .btn-outline-light {
        width: 100%;
      }
    }
  </style>
  <script>
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

  <main id="main-content" class="main">

    <?php 
      $hImgData = $home['Hero-Image'] ?? $home['Image'] ?? null;
      $heroImg = !empty($hImgData['url']) 
                 ? $hImgData['url'] 
                 : 'assets/img/IMG-20241120-WA0018.jpg';
    ?>
    <section id="hero" class="ssp-hero" style="background-image: url('<?= $heroImg ?>');">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-8 text-center text-lg-start">
            
            <div class="pixel-decor" data-aos="fade-down">
              <span class="px-1"></span>
              <span class="px-2"></span>
              <span class="px-3"></span>
            </div>

            <div class="mb-3" data-aos="fade-up">
              <span class="hero-tagline">Study Made Simple, Success Made Sure</span>
            </div>

            <h1 class="display-4 fw-bold text-white mb-3" data-aos="fade-up" data-aos-delay="100">
              <?= $home['Hero-Title'] ?? $home['Title'] ?? 'Learning Today,<br class="d-none d-sm-inline">Leading Tomorrow' ?>
            </h1>

            <p class="lead text-white opacity-100 mb-4 fw-normal" data-aos="fade-up" data-aos-delay="200">
              <?= $home['Hero-Subtitle'] ?? $home['Description'] ?? 'Empowering students with innovative learning solutions, professional tutoring, and academic excellence in Uganda.' ?>
            </p>

            <div class="d-flex flex-column flex-sm-row flex-wrap gap-3 justify-content-center justify-content-lg-start" data-aos="fade-up" data-aos-delay="300">
              <a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>" class="btn-ssp-primary text-decoration-none">Start Learning Today</a>
              <a href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-light rounded-3 px-4 py-2 fw-bold text-decoration-none">Discover SmartStudyPro</a>
            </div>

          </div>
        </div>
      </div>
    </section>

    <section id="about" class="about section py-4 py-md-5">
      <div class="container" data-aos="fade-up">
        <div class="row gy-4 align-items-center">
          <div class="col-lg-6 order-1 order-lg-2">
            <div class="position-relative">
              <img src="<?= htmlspecialchars(\App\Core\Url::to('/assets/img/20240102_164438.jpg'), ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded-4 shadow-lg w-100" alt="SmartStudyPro Learning Environment">
            </div>
          </div>

          <div class="col-lg-6 order-2 order-lg-1">
            <span class="text-uppercase fw-bold text-primary fs-7 tracking-wider">About SmartStudyPro</span>
            <h2 class="fw-bold mt-1 mb-3" style="color: var(--ssp-navy);">Empowering Education Through Smart & Flexible Learning</h2>
            <p class="text-muted fs-6">
              SmartStudyPro is a complete online educational platform engineered to simplify learning, resource access, and academic booking across Uganda.
            </p>

            <div class="my-4">
              <div class="d-flex gap-3 mb-3">
                <div class="fs-4 flex-shrink-0"><i class="bi bi-patch-check-fill" style="color: var(--ssp-orange);"></i></div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Distraction-Free Platform</h6>
                  <p class="text-muted small mb-0">Clean, intuitive layout structured specifically to keep students focused on academic success.</p>
                </div>
              </div>

              <div class="d-flex gap-3 mb-3">
                <div class="fs-4 flex-shrink-0"><i class="bi bi-patch-check-fill" style="color: var(--ssp-orange);"></i></div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Unified Learning Ecosystem</h6>
                  <p class="text-muted small mb-0">Seamless access to course content, holiday guidance packages, and specialized ICT lessons.</p>
                </div>
              </div>

              <div class="d-flex gap-3">
                <div class="fs-4 flex-shrink-0"><i class="bi bi-patch-check-fill" style="color: var(--ssp-orange);"></i></div>
                <div>
                  <h6 class="fw-bold mb-1" style="color: var(--ssp-navy);">Reliable & Secured Infrastructure</h6>
                  <p class="text-muted small mb-0">Built on modern, fast web standards to ensure student data safety and fast dynamic loading.</p>
                </div>
              </div>
            </div>

            <a href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>" class="btn-ssp-navy text-decoration-none d-inline-block">Read More About Us <i class="bi bi-arrow-right ms-1"></i></a>
          </div>
        </div>
      </div>
    </section>

    <section id="courses" class="courses section py-4 py-md-5" style="background-color: var(--ssp-bg-soft);">
      <div class="container text-center mb-4 mb-md-5" data-aos="fade-up">
        <span class="badge px-3 py-2 mb-2" style="background-color: var(--ssp-badge-bg); color: var(--ssp-badge-text);">Featured Content</span>
        <h2 class="fw-bold" style="color: var(--ssp-navy);">Explore Popular Courses</h2>
        <p class="text-muted fs-6">Explore structured learning programs designed to boost academic growth.</p>
      </div>

      <div class="container">
        <div class="row g-4">
          <?php if (!empty($courses)): ?>
            <?php foreach(array_slice($courses, 0, 3) as $course): ?>
              <div class="col-12 col-md-6 col-lg-4 d-flex align-items-stretch" data-aos="zoom-in">
                <div class="ssp-course-card w-100 d-flex flex-column">
                  <?php 
                    $cImgData = $course['Image'] ?? $course['image'] ?? null;
                    $cImg = !empty($cImgData['url']) 
                            ? $cImgData['url'] 
                            : 'assets/img/course-1.jpg'; 
                  ?>
                  <div class="position-relative">
                    <img src="<?= $cImg ?>" class="img-fluid w-100" style="height: 210px; object-fit: cover;" alt="<?= htmlspecialchars($course['Title'] ?? 'Course') ?>">
                  </div>

                  <div class="p-3 p-sm-4 d-flex flex-column flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                      <span class="ssp-card-badge"><?= htmlspecialchars($course['Category'] ?? 'General') ?></span>
                      <span class="ssp-price-tag">UGX <?= number_format(floatval($course['Price'] ?? 0)) ?></span>
                    </div>

                    <h3 class="h5 fw-bold mb-2">
                      <a href="<?= htmlspecialchars(\App\Core\Url::to('/courses/' . rawurlencode((string) ($course['slug'] ?? $course['_id']))), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none" style="color: var(--ssp-navy);">
                        <?= htmlspecialchars($course['Title'] ?? 'Untitled Course') ?>
                      </a>
                    </h3>

                    <p class="text-muted small mb-4 flex-grow-1">
                      <?= htmlspecialchars(substr(strip_tags($course['Description'] ?? $course['description'] ?? ''), 0, 110)) ?>...
                    </p>

                    <a href="<?= htmlspecialchars(\App\Core\Url::to('/courses/' . rawurlencode((string) ($course['slug'] ?? $course['_id']))), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary w-100 mt-auto rounded-3 fw-bold text-decoration-none" style="color: var(--ssp-navy); border-color: var(--ssp-navy);">
                      View Details
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center text-muted py-5">
              <p>No courses currently featured. Please check back shortly!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
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
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/aos/aos.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>



</body>
</html>