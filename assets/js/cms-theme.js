(function() {
  "use strict";

  const root = document.documentElement;
  let savedTheme = null;
  let prefersDark = false;

  try {
    savedTheme = window.localStorage.getItem("ssp-theme");
  } catch (error) {}

  try {
    prefersDark = typeof window.matchMedia === "function" && window.matchMedia("(prefers-color-scheme: dark)").matches;
  } catch (error) {}

  const initialTheme = savedTheme === "dark" || savedTheme === "light" ? savedTheme : prefersDark ? "dark" : "light";

  if (initialTheme === "dark") root.setAttribute("data-theme", "dark");
  else root.removeAttribute("data-theme");

  function initializeToggle() {
    const button = document.getElementById("cms-theme-toggle");
    if (!button) return;

    function updateButton(isDark) {
      button.setAttribute("aria-pressed", String(isDark));
      button.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
      button.textContent = isDark ? "Light mode" : "Dark mode";
    }

    updateButton(root.getAttribute("data-theme") === "dark");

    button.addEventListener("click", function() {
      const isDark = root.getAttribute("data-theme") !== "dark";
      if (isDark) root.setAttribute("data-theme", "dark");
      else root.removeAttribute("data-theme");

      try {
        window.localStorage.setItem("ssp-theme", isDark ? "dark" : "light");
      } catch (error) {}

      updateButton(isDark);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeToggle, { once: true });
  } else {
    initializeToggle();
  }
})();
