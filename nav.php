<?php
$navScriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$navBasePath = \App\Core\Url::basePath();
$navRoute = static fn (string $path): string => \App\Core\Url::to($path);
$navRequestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$navRequestPath = is_string($navRequestPath) ? rawurldecode($navRequestPath) : '/';
if ($navBasePath !== '' && str_starts_with($navRequestPath, $navBasePath . '/')) $navRequestPath = substr($navRequestPath, strlen($navBasePath));
$navRequestPath = '/' . trim($navRequestPath, '/');
if ($navRequestPath !== '/') $navRequestPath = rtrim($navRequestPath, '/');
$navActive = static function (string $section) use ($navRequestPath): bool {
    return match ($section) {
        'home' => $navRequestPath === '/',
        'about' => $navRequestPath === '/about',
        'courses' => $navRequestPath === '/courses' || str_starts_with($navRequestPath, '/courses/'),
        'learning' => in_array($navRequestPath, ['/learn', '/learning', '/study'], true) || str_starts_with($navRequestPath, '/learn/'),
        'meetings' => $navRequestPath === '/meetings',
        'assistant' => $navRequestPath === '/assistant',
        'products' => $navRequestPath === '/products' || str_starts_with($navRequestPath, '/products/'),
        'contact' => $navRequestPath === '/contact',
        default => false,
    };
};
?>
<a class="skip-link" href="<?= htmlspecialchars($navRoute($navRequestPath . '#main-content'), ENT_QUOTES, 'UTF-8') ?>">Skip to content</a>
<header id="header" class="header ssp-header d-flex align-items-center sticky-top py-2" style="z-index: 10000;">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
    <a href="<?= htmlspecialchars($navRoute('/'), ENT_QUOTES, 'UTF-8') ?>" class="logo d-flex align-items-center me-auto me-xl-0 text-decoration-none">
      <img src="<?= htmlspecialchars($navRoute('/Smart_Study_Logo_Fin-removebg-preview.png'), ENT_QUOTES, 'UTF-8') ?>" alt="SmartStudyPro Logo" height="48" class="img-fluid" style="max-height: 48px;">
    </a>
    <nav id="navmenu" class="navmenu" aria-label="Main navigation">
      <ul>
        <li><a href="<?= htmlspecialchars($navRoute('/'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('home') ? ' class="active" aria-current="page"' : '' ?>>Home</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/about'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('about') ? ' class="active" aria-current="page"' : '' ?>>About Us</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/courses'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('courses') ? ' class="active" aria-current="page"' : '' ?>>Courses</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/learn'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('learning') ? ' class="active" aria-current="page"' : '' ?>>My Learning</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/meetings'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('meetings') ? ' class="active" aria-current="page"' : '' ?>>My Sessions</a></li>
        <?php $navPdo = \App\Core\Database::connect(); $navUser = \App\Core\Auth::user($navPdo); if ($navUser && \App\Core\Auth::hasRole($navPdo, (int) $navUser['id'], 'instructor')): ?><li><a href="<?= htmlspecialchars($navRoute('/instructor'), ENT_QUOTES, 'UTF-8') ?>"<?= $navRequestPath === '/instructor' ? ' class="active" aria-current="page"' : '' ?>>Instructor Workspace</a></li><?php endif; ?>
        <li><a href="<?= htmlspecialchars($navRoute('/assistant'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('assistant') ? ' class="active" aria-current="page"' : '' ?>>AI Tutor</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/products'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('products') ? ' class="active" aria-current="page"' : '' ?>>Products</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/contact'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('contact') ? ' class="active" aria-current="page"' : '' ?>>Contact</a></li>
      </ul>
      <button type="button" class="mobile-nav-toggle d-xl-none border-0 bg-transparent ms-3 ms-sm-4 p-1" aria-label="Open navigation" aria-controls="navmenu" aria-expanded="false"><i class="bi bi-list fs-2" aria-hidden="true"></i></button>
    </nav>
    <div class="d-flex align-items-center gap-2 gap-sm-3">
      <button type="button" id="theme-toggle" class="header-icon-link fs-5" aria-label="Toggle Dark/Light Mode" title="Toggle Theme" data-theme-endpoint="<?= htmlspecialchars($navRoute('/theme-preference'), ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <i id="theme-toggle-icon" class="bi bi-moon-fill" aria-hidden="true"></i>
      </button>
      <a href="<?= htmlspecialchars($navRoute('/cart'), ENT_QUOTES, 'UTF-8') ?>" class="header-icon-link fs-5 text-decoration-none" title="Shopping Cart" aria-label="Shopping cart"><i class="bi bi-bag"></i></a>
      <a href="<?= htmlspecialchars($navRoute('/notifications'), ENT_QUOTES, 'UTF-8') ?>" class="header-icon-link fs-5 text-decoration-none" title="Notifications" aria-label="Notifications"><i class="bi bi-bell"></i></a>
      <a href="<?= htmlspecialchars($navRoute('/profile'), ENT_QUOTES, 'UTF-8') ?>" class="header-icon-link fs-5 text-decoration-none" title="My Profile" aria-label="My profile"><i class="bi bi-person-circle" aria-hidden="true"></i></a>
      <a class="btn-ssp-primary d-none d-sm-inline-block text-decoration-none ms-1 ms-sm-2" href="<?= htmlspecialchars($navRoute('/courses'), ENT_QUOTES, 'UTF-8') ?>">Explore Courses</a>
    </div>
  </div>
</header>

<script src="<?= htmlspecialchars($navRoute('/assets/js/theme.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>