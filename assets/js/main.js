(function() {
  "use strict";

  const body = document.body;
  const header = document.querySelector("#header");

  function toggleScrolled() {
    if (body && header) body.classList.toggle("scrolled", window.scrollY > 12);
  }

  window.addEventListener("scroll", toggleScrolled, { passive: true });
  toggleScrolled();

  const toggle = document.querySelector(".mobile-nav-toggle");
  const menu = document.querySelector("#primary-navigation");
  const nav = document.querySelector("#navmenu");
  const mobileQuery = window.matchMedia("(max-width: 1199.98px)");

  function setMobileNav(open, restoreFocus) {
    if (!body || !toggle || !menu) return;
    const next = Boolean(open && mobileQuery.matches);
    body.classList.toggle("mobile-nav-active", next);
    toggle.setAttribute("aria-expanded", String(next));
    toggle.setAttribute("aria-label", next ? "Close navigation" : "Open navigation");
    const icon = toggle.querySelector("i");
    if (icon) {
      icon.classList.toggle("bi-list", !next);
      icon.classList.toggle("bi-x", next);
    }
    if (next) {
      const firstLink = menu.querySelector("a");
      if (firstLink) firstLink.focus({ preventScroll: true });
    } else if (restoreFocus) {
      toggle.focus({ preventScroll: true });
    }
  }

  if (toggle && menu && nav) {
    toggle.addEventListener("click", function() {
      setMobileNav(toggle.getAttribute("aria-expanded") !== "true", false);
    });
    menu.addEventListener("click", function(event) {
      if (event.target.closest("a")) setMobileNav(false, false);
    });
    document.addEventListener("pointerdown", function(event) {
      if (body.classList.contains("mobile-nav-active") && !header.contains(event.target)) setMobileNav(false, false);
    });
    document.addEventListener("keydown", function(event) {
      if (!body.classList.contains("mobile-nav-active")) return;
      if (event.key === "Escape") {
        event.preventDefault();
        setMobileNav(false, true);
      }
      if (event.key === "Tab") {
        const focusable = Array.from(menu.querySelectorAll("a[href]"));
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          toggle.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          toggle.focus();
        }
      }
    });
    mobileQuery.addEventListener("change", function() {
      setMobileNav(false, false);
    });
  }

  const preloader = document.querySelector("#preloader");
  if (preloader) window.addEventListener("load", function() { preloader.remove(); }, { once: true });

  const scrollTop = document.querySelector(".scroll-top");
  function toggleScrollTop() {
    if (scrollTop) scrollTop.classList.toggle("active", window.scrollY > 100);
  }
  if (scrollTop) scrollTop.addEventListener("click", function(event) {
    event.preventDefault();
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
  });
  window.addEventListener("load", toggleScrollTop);
  window.addEventListener("scroll", toggleScrollTop, { passive: true });

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  function initializeAnimations() {
    if (typeof AOS !== "undefined" && AOS && typeof AOS.init === "function") {
      AOS.init({ duration: 600, easing: "ease-in-out", once: true, mirror: false, disable: reducedMotion });
    } else {
      document.querySelectorAll("[data-aos]").forEach(function(element) { element.classList.add("aos-animate"); });
    }
  }
  if (document.readyState === "complete") initializeAnimations();
  else window.addEventListener("load", initializeAnimations, { once: true });
})();