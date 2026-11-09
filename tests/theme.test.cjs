const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

(async function () {
  const handlers = {};
  const stored = new Map([["ssp-theme", "light"]]);
  const requests = [];
  const classes = new Set(["bi-moon-fill"]);
  const icon = {
    classList: {
      add: (...names) => names.forEach(name => classes.add(name)),
      remove: (...names) => names.forEach(name => classes.delete(name))
    }
  };
  const button = {
    id: "theme-toggle",
    dataset: { themeEndpoint: "/theme-preference", csrf: "test-csrf" },
    textContent: "",
    attributes: {},
    getAttribute(name) { return this.attributes[name] ?? null; },
    setAttribute(name, value) { this.attributes[name] = value; },
    querySelector(selector) { return selector === "i" ? icon : null; },
    addEventListener(name, handler) { handlers[name] = handler; }
  };
  const rootAttributes = new Map();
  const root = {
    style: {},
    getAttribute(name) { return rootAttributes.get(name) ?? null; },
    setAttribute(name, value) { rootAttributes.set(name, value); },
    removeAttribute(name) { rootAttributes.delete(name); }
  };
  const document = {
    documentElement: root,
    readyState: "complete",
    querySelectorAll() { return [button]; },
    querySelector() { return button; },
    addEventListener() {}
  };
  const localStorage = {
    getItem(key) { return stored.has(key) ? stored.get(key) : null; },
    setItem(key, value) { stored.set(key, value); },
    removeItem(key) { stored.delete(key); }
  };
  const window = {
    localStorage,
    matchMedia() { return { matches: false, addEventListener() {} }; }
  };
  const sandbox = {
    document,
    window,
    localStorage,
    fetch: async (url, options = {}) => {
      requests.push({ url, options });
      if (!options.method) return { ok: true, json: async () => ({ theme: "dark" }) };
      return { ok: true };
    }
  };

  vm.runInNewContext(fs.readFileSync("assets/js/theme.js", "utf8"), sandbox);
  assert.equal(root.getAttribute("data-theme"), null);
  assert.equal(root.style.colorScheme, "light");
  assert.equal(button.getAttribute("aria-pressed"), "false");
  assert.equal(typeof handlers.click, "function");

  await new Promise(resolve => setImmediate(resolve));
  assert.equal(root.getAttribute("data-theme"), "dark");
  assert.equal(root.style.colorScheme, "dark");
  assert.equal(stored.get("ssp-theme"), "dark");
  assert.equal(button.getAttribute("aria-pressed"), "true");
  assert.equal(classes.has("bi-sun-fill"), true);
  assert.equal(requests[0].url, "/theme-preference");
  assert.equal(requests[0].options.cache, "no-store");

  handlers.click();
  assert.equal(root.getAttribute("data-theme"), null);
  assert.equal(root.style.colorScheme, "light");
  assert.equal(stored.get("ssp-theme"), "light");
  assert.equal(button.getAttribute("aria-pressed"), "false");
  assert.equal(classes.has("bi-moon-fill"), true);
  assert.equal(requests.length, 2);
  assert.equal(requests[1].url, "/theme-preference");
  assert.equal(requests[1].options.headers["X-CSRF-Token"], "test-csrf");
  assert.equal(requests[1].options.body, JSON.stringify({ theme: "light" }));

  console.log("PASS shared theme initialization, cross-device preference loading and account-synced updates");
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
