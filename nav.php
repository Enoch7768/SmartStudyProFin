<style>
  .ssp-header {
    background: var(--ssp-header-bg, rgba(255, 255, 255, 0.98));
    backdrop-filter: blur(12px);
    border-bottom: 2px solid rgba(12, 8, 107, 0.12);
    transition: background-color 0.3s ease, border-color 0.3s ease;
  }

  [data-theme="dark"] .ssp-header {
    background: rgba(11, 15, 23, 0.98);
    border-bottom-color: rgba(255, 255, 255, 0.1);
  }

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
    color: var(--ssp-navy, #0C086B);
    font-weight: 700;
    font-size: 0.95rem;
    text-decoration: none !important;
    display: inline-block;
    white-space: nowrap;
    transition: color 0.2s ease;
  }

  .navmenu ul li a:hover,
  .navmenu ul li a.active {
    color: var(--ssp-orange, #E66A00) !important;
    font-weight: 800;
  }
  @media (min-width: 1200px) {
    .navmenu ul {
      display: flex !important;
      align-items: center !important;
      gap: 24px;
    }
  }

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

  @media (max-width: 1199.98px) {
    .navmenu ul {
      display: none !important;
      position: fixed;
      top: 70px;
      right: 15px;
      left: 15px;
      padding: 20px 24px !important;
      background: var(--ssp-card-bg, #FFFFFF);
      border-radius: 16px;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
      border: 1px solid var(--ssp-card-border, #CBD5E1);
      z-index: 9999;
      flex-direction: column !important;
      align-items: stretch !important;
      gap: 12px;
    }

    body.mobile-nav-active .navmenu ul {
      display: flex !important;
      animation: mobileNavSlideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .navmenu ul li {
      width: 100% !important;
    }

    .navmenu ul li a {
      display: block !important;
      width: 100% !important;
      padding: 8px 0;
      white-space: normal !important;
    }

    .mobile-nav-toggle {
      cursor: pointer !important;
      position: relative !important;
      z-index: 10005 !important;
      pointer-events: auto !important;
      color: var(--ssp-navy, #0C086B);
      transition: transform 0.25s ease, color 0.25s ease;
    }

    body.mobile-nav-active .mobile-nav-toggle {
      transform: rotate(90deg);
      color: var(--ssp-orange, #E66A00);
    }
  }

  .header-icon-link {
    color: var(--ssp-navy, #0C086B);
    padding: 6px 10px;
    border-radius: 8px;
    transition: background-color 0.2s ease, color 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 10005;
    background: transparent;
    border: none;
    cursor: pointer;
  }

  .header-icon-link:hover {
    color: var(--ssp-orange, #E66A00);
    background-color: var(--ssp-bg-soft, #F1F5F9);
  }
</style>

<header id="header" class="header ssp-header d-flex align-items-center sticky-top py-2" style="z-index: 10000;">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
    
    <a href="index.php" class="logo d-flex align-items-center me-auto me-xl-0 text-decoration-none">
      <img src="Smart_Study_Logo_Fin-removebg-preview.png" alt="SmartStudyPro Logo" height="48" class="img-fluid" style="max-height: 48px;">
    </a>

    <nav id="navmenu" class="navmenu">
      <ul>
        <li><a href="index.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>">Home</a></li>
        <li><a href="about.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : '' ?>">About Us</a></li>
        <li><a href="courses.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'courses.php') ? 'active' : '' ?>">Courses</a></li>
        <li><a href="products.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : '' ?>">Products</a></li>
        <li><a href="contact.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : '' ?>">Contact</a></li>
      </ul>
      <i class="mobile-nav-toggle d-xl-none bi bi-list fs-2 ms-3 ms-sm-4 p-1"></i>
    </nav>

    <div class="d-flex align-items-center gap-2 gap-sm-3">
      <button type="button" id="theme-toggle" class="header-icon-link fs-5" aria-label="Toggle Dark/Light Mode" title="Toggle Theme">
        <i id="theme-toggle-icon" class="bi bi-moon-fill"></i>
      </button>

      <a href="cart.php" class="header-icon-link fs-5 text-decoration-none" title="Shopping Cart">
        <i class="bi bi-bag"></i>
      </a>

      <a href="profile.php" class="header-icon-link fs-5 text-decoration-none" title="My Profile">
        <i class="bi bi-person-circle"></i>
      </a>

      <a class="btn-ssp-primary d-none d-sm-inline-block text-decoration-none ms-1 ms-sm-2" href="courses.php">Explore Courses</a>
    </div>

  </div>
</header>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('theme-toggle');
    const toggleIcon = document.getElementById('theme-toggle-icon');

    if (!toggleBtn || !toggleIcon) return;

    function updateIcon(isDark) {
      if (isDark) {
        toggleIcon.classList.remove('bi-moon-fill');
        toggleIcon.classList.add('bi-sun-fill');
      } else {
        toggleIcon.classList.remove('bi-sun-fill');
        toggleIcon.classList.add('bi-moon-fill');
      }
    }

    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    updateIcon(isDarkMode);

    toggleBtn.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      if (currentTheme === 'dark') {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('ssp-theme', 'light');
        updateIcon(false);
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('ssp-theme', 'dark');
        updateIcon(true);
      }
    });
  });
</script>