(function () {
  "use strict";
  const root = document.documentElement;
  const key = "ssp-theme";
  let userChangedTheme = false;
  const selector = "#theme-toggle,#study-theme-toggle,#cms-theme-toggle,[data-admin-theme-toggle],[data-theme-toggle]";
  function saved() {
    try { const value = localStorage.getItem(key); return value === "dark" || value === "light" ? value : null; }
    catch (error) { return null; }
  }
  function system() {
    try { return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"; }
    catch (error) { return "light"; }
  }
  function current() { return root.getAttribute("data-theme") === "dark" ? "dark" : "light"; }
  function apply(theme) {
    if (theme === "dark") root.setAttribute("data-theme", "dark");
    else root.removeAttribute("data-theme");
    root.style.colorScheme = theme;
    document.querySelectorAll(selector).forEach(function (button) {
      const dark = theme === "dark";
      const label = dark ? "Switch to light mode" : "Switch to dark mode";
      button.setAttribute("aria-pressed", String(dark));
      button.setAttribute("aria-label", label);
      button.setAttribute("title", label);
      const icon = button.querySelector("i");
      if (icon) {
        icon.classList.remove("bi-moon-fill", "bi-moon-stars", "bi-sun-fill");
        icon.classList.add(dark ? "bi-sun-fill" : (button.id === "study-theme-toggle" ? "bi-moon-stars" : "bi-moon-fill"));
      } else if (button.textContent.trim()) {
        button.textContent = dark ? "Light mode" : "Dark mode";
      }
    });
  }
  function save(theme, button) {
    try { localStorage.setItem(key, theme); } catch (error) {}
    const endpoint = button && button.dataset ? button.dataset.themeEndpoint : "";
    const csrf = button && button.dataset ? button.dataset.csrf : "";
    if (!endpoint || !csrf) return;
    fetch(endpoint, {method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-CSRF-Token":csrf},body:JSON.stringify({theme:theme})}).catch(function () {});
  }
  apply(saved() || system());
  function syncFromAccount() {
    const button = document.querySelector("[data-theme-endpoint]");
    const endpoint = button && button.dataset ? button.dataset.themeEndpoint : "";
    if (!endpoint) return;
    fetch(endpoint, {credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}})
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (payload) {
        if (userChangedTheme || !payload || !["system", "light", "dark"].includes(payload.theme)) return;
        if (payload.theme === "system") {
          try { localStorage.removeItem(key); } catch (error) {}
          apply(system());
        } else {
          try { localStorage.setItem(key, payload.theme); } catch (error) {}
          apply(payload.theme);
        }
      })
      .catch(function () {});
  }

  function initialize() {
    document.querySelectorAll(selector).forEach(function (button) {
      if (button.dataset.themeBound === "true") return;
      button.dataset.themeBound = "true";
      button.addEventListener("click", function () {
        userChangedTheme = true;
        const next = current() === "dark" ? "light" : "dark";
        apply(next);
        save(next, button);
      });
    });
    apply(current());
    syncFromAccount();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize, {once:true});
  else initialize();
  try {
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    if (media && typeof media.addEventListener === "function") media.addEventListener("change", function () { if (!saved()) apply(system()); });
  } catch (error) {}
})();
