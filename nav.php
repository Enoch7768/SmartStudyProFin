<?php
declare(strict_types=1);

$navRoute = static fn (string $path): string => \App\Core\Url::to($path);
$navBasePath = \App\Core\Url::basePath();
$navRequestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$navRequestPath = is_string($navRequestPath) ? rawurldecode($navRequestPath) : '/';
if ($navBasePath !== '' && ($navRequestPath === $navBasePath || str_starts_with($navRequestPath, $navBasePath . '/'))) {
    $navRequestPath = substr($navRequestPath, strlen($navBasePath)) ?: '/';
}
$navRequestPath = '/' . trim($navRequestPath, '/');
if ($navRequestPath !== '/') $navRequestPath = rtrim($navRequestPath, '/');
$navActive = static fn (string $section): bool => match ($section) {
    'home' => $navRequestPath === '/',
    'about' => $navRequestPath === '/about',
    'courses' => $navRequestPath === '/courses' || str_starts_with($navRequestPath, '/courses/'),
    'learning' => in_array($navRequestPath, ['/learn', '/learning', '/study', '/meetings'], true) || str_starts_with($navRequestPath, '/learn/'),
    'products' => $navRequestPath === '/products' || str_starts_with($navRequestPath, '/products/'),
    'contact' => $navRequestPath === '/contact',
    default => false,
};

\App\Core\Session::start();
$navPdo = \App\Core\Database::connect();
$navUser = \App\Core\Auth::user($navPdo);
$navCartCount = 0;
foreach ($_SESSION['ssp_cart'] ?? [] as $navCartItem) {
    $navCartCount += max(1, min(20, (int) ($navCartItem['quantity'] ?? 1)));
}
$navIsInstructor = $navUser && \App\Core\Auth::hasRole($navPdo, (int) $navUser['id'], 'instructor');
?>
<a class="skip-link" href="#main-content">Skip to content</a>
<header id="header" class="header ssp-header sticky-top">
  <div class="ssp-header-inner">
    <a href="<?= htmlspecialchars($navRoute('/'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-brand" aria-label="SmartStudyPro home">
      <img src="<?= htmlspecialchars($navRoute('/Smart_Study_Logo_Fin-removebg-preview.png'), ENT_QUOTES, 'UTF-8') ?>" alt="" width="174" height="52">
    </a>
    <nav id="navmenu" class="navmenu" aria-label="Main navigation">
      <ul id="primary-navigation">
        <li><a href="<?= htmlspecialchars($navRoute('/'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('home') ? ' class="active" aria-current="page"' : '' ?>>Home</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/courses'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('courses') ? ' class="active" aria-current="page"' : '' ?>>Courses</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/learn'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('learning') ? ' class="active" aria-current="page"' : '' ?>>My learning</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/products'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('products') ? ' class="active" aria-current="page"' : '' ?>>Shop</a></li>
        <li><a href="<?= htmlspecialchars($navRoute('/about'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('about') ? ' class="active" aria-current="page"' : '' ?>>About</a></li>
        <?php if ($navIsInstructor): ?><li><a href="<?= htmlspecialchars($navRoute('/instructor'), ENT_QUOTES, 'UTF-8') ?>"<?= $navRequestPath === '/instructor' ? ' class="active" aria-current="page"' : '' ?>>Instructor</a></li><?php endif; ?>
        <li class="ssp-mobile-contact"><a href="<?= htmlspecialchars($navRoute('/contact'), ENT_QUOTES, 'UTF-8') ?>"<?= $navActive('contact') ? ' class="active" aria-current="page"' : '' ?>>Contact</a></li>
        <?php if ($navUser): ?>
          <li class="ssp-mobile-contact"><a href="<?= htmlspecialchars($navRoute('/meetings'), ENT_QUOTES, 'UTF-8') ?>">My sessions</a></li>
          <li class="ssp-mobile-contact"><a href="<?= htmlspecialchars($navRoute('/assistant'), ENT_QUOTES, 'UTF-8') ?>">AI tutor</a></li>
        <?php endif; ?>
      </ul>
    </nav>
    <div class="ssp-header-actions">
      <button type="button" id="theme-toggle" class="header-icon-link ssp-header-action" aria-label="Switch to dark mode" title="Switch theme" data-theme-endpoint="<?= htmlspecialchars($navRoute('/theme-preference'), ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars(\App\Core\Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <i id="theme-toggle-icon" class="bi bi-moon-fill" aria-hidden="true"></i>
      </button>
      <a href="<?= htmlspecialchars($navRoute('/cart'), ENT_QUOTES, 'UTF-8') ?>" class="header-icon-link ssp-header-action ssp-cart-link" aria-label="Shopping cart, <?= $navCartCount ?> items" title="Shopping cart">
        <i class="bi bi-bag" aria-hidden="true"></i>
        <span class="ssp-cart-count"<?= $navCartCount === 0 ? ' hidden' : '' ?>><?= $navCartCount > 99 ? '99+' : $navCartCount ?></span>
      </a>
      <?php if ($navUser): ?>
        <a href="<?= htmlspecialchars($navRoute('/notifications'), ENT_QUOTES, 'UTF-8') ?>" class="header-icon-link ssp-header-action ssp-desktop-utility" aria-label="Notifications" title="Notifications"><i class="bi bi-bell" aria-hidden="true"></i></a>
        <a href="<?= htmlspecialchars($navRoute('/profile'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-account-link"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Account</span></a>
      <?php else: ?>
        <a href="<?= htmlspecialchars($navRoute('/login'), ENT_QUOTES, 'UTF-8') ?>" class="ssp-account-link"><i class="bi bi-person" aria-hidden="true"></i><span>Sign in</span></a>
      <?php endif; ?>
      <a class="ssp-header-cta" href="<?= htmlspecialchars($navRoute('/courses'), ENT_QUOTES, 'UTF-8') ?>">Explore courses <span aria-hidden="true">↗</span></a>
      <button type="button" class="mobile-nav-toggle" aria-label="Open navigation" aria-controls="primary-navigation" aria-expanded="false"><i class="bi bi-list" aria-hidden="true"></i></button>
    </div>
  </div>
</header>
<script src="<?= htmlspecialchars($navRoute('/assets/js/theme.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>