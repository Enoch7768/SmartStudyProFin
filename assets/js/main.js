(function() {
  "use strict";

  const body = document.body || document.querySelector('body');
  const header = document.querySelector('#header');

  function toggleScrolled() {
    if (!body || !header) return;
    if (!header.classList.contains('scroll-up-sticky') && !header.classList.contains('sticky-top') && !header.classList.contains('fixed-top')) return;
    body.classList.toggle('scrolled', window.scrollY > 100);
  }

  document.addEventListener('scroll', toggleScrolled, { passive: true });
  window.addEventListener('load', toggleScrolled);

  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');
  const mobileNavToggleIcon = mobileNavToggleBtn ? mobileNavToggleBtn.querySelector('i') : null;

  function setMobileNav(open) {
    if (!body || !mobileNavToggleBtn) return;
    body.classList.toggle('mobile-nav-active', open);
    mobileNavToggleBtn.setAttribute('aria-expanded', String(open));
    mobileNavToggleBtn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    if (mobileNavToggleIcon) {
      mobileNavToggleIcon.classList.toggle('bi-list', !open);
      mobileNavToggleIcon.classList.toggle('bi-x', open);
    }
  }

  if (mobileNavToggleBtn && body) {
    mobileNavToggleBtn.addEventListener('click', function() {
      setMobileNav(!body.classList.contains('mobile-nav-active'));
    });
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape' && body && body.classList.contains('mobile-nav-active')) {
        setMobileNav(false);
        mobileNavToggleBtn.focus();
      }
    });
  }

  document.querySelectorAll('#navmenu a').forEach(function(link) {
    link.addEventListener('click', function() {
      if (body && body.classList.contains('mobile-nav-active')) setMobileNav(false);
    });
  });

  document.querySelectorAll('.navmenu .toggle-dropdown').forEach(function(link) {
    link.addEventListener('click', function(event) {
      event.preventDefault();
      const parent = this.parentNode;
      const submenu = parent ? parent.nextElementSibling : null;
      if (!parent || !submenu) return;
      parent.classList.toggle('active');
      submenu.classList.toggle('dropdown-active');
      this.setAttribute('aria-expanded', String(parent.classList.contains('active')));
      event.stopImmediatePropagation();
    });
  });

  const preloader = document.querySelector('#preloader');
  if (preloader) window.addEventListener('load', function() {
    preloader.remove();
  });

  const scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) scrollTop.classList.toggle('active', window.scrollY > 100);
  }

  if (scrollTop) scrollTop.addEventListener('click', function(event) {
    event.preventDefault();
    const reducedMotion = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
  });

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop, { passive: true });

  const reducedMotion = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!reducedMotion && typeof AOS !== 'undefined' && AOS && typeof AOS.init === 'function') {
    window.addEventListener('load', function() {
      AOS.init({ duration: 600, easing: 'ease-in-out', once: true, mirror: false });
    });
  }

  if (!reducedMotion && typeof GLightbox === 'function') GLightbox({ selector: '.glightbox' });
  if (!reducedMotion && typeof PureCounter === 'function') new PureCounter();

  function initSwiper() {
    if (typeof Swiper !== 'function') return;
    document.querySelectorAll('.init-swiper').forEach(function(element) {
      const configElement = element.querySelector('.swiper-config');
      if (!configElement) return;
      let config;
      try {
        config = JSON.parse(configElement.textContent.trim());
      } catch (error) {
        return;
      }
      if (element.classList.contains('swiper-tab') && typeof initSwiperWithCustomPagination === 'function') {
        initSwiperWithCustomPagination(element, config);
      } else {
        new Swiper(element, config);
      }
    });
  }

  window.addEventListener('load', initSwiper);
})();
