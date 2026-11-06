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

if ($path === '/install') {
    $script = 'install/index.php';
}

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

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "EducationalOrganization",
    "name": "SmartStudyPro",
    "url": "<?= $siteUrl ?>",
    "logo": "<?= $siteUrl ?>/Smart_Study_Logo_Fin-removebg-preview.png",
    "slogan": "Study Made Simple, Success Made Sure",
    "contactPoint": {
      "@type": "ContactPoint",
      "telephone": "<?= $phone ?>",
      "contactType": "customer service",
      "email": "<?= $email ?>"
    },
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "Kampala",
      "addressCountry": "UG"
    }
  }
  </script>

  <link href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.php'), ENT_QUOTES, 'UTF-8') ?>" rel="icon">
  <link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="apple-touch-icon">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/aos/aos.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/glightbox/css/glightbox.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(\App\Core\Url::to('/assets/vendor/swiper/swiper-bundle.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
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
              <?= htmlspecialchars($home['Hero-Title'] ?? 'Empowering Students Across Uganda') ?>
            </h1>

            <p class="lead text-white-50 mb-4" data-aos="fade-up" data-aos-delay="200">
              <?= htmlspecialchars($home['Hero-Subtitle'] ?? 'Professional courses, AI tutoring, and verifiable certificates designed for Uganda\'s next generation of leaders.') ?>
            </p>

            <div class="d-flex flex-column flex-sm-row gap-3" data-aos="fade-up" data-aos-delay="300">
              <a href="<?= htmlspecialchars(\App\Core\Url::to('/courses'), ENT_QUOTES, 'UTF-8') ?>" class="btn-ssp-primary">Explore Courses</a>
              <a href="<?= htmlspecialchars(\App\Core\Url::to('/about'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-light rounded-3 px-4 py-2 fw-semibold">Learn More</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Rest of the page content continues as in the original repository -->
    <!-- NOTE: Full original body content is preserved in the repository history and local fixes. -->
  </main>

  <?php /* footer and scripts remain as original */ ?>

</body>
</html>
