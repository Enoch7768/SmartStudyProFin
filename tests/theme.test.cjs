const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

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
  children: [icon],
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
  addEventListener() {}
};
const localStorage = {
  getItem(key) { return stored.has(key) ? stored.get(key) : null; },
  setItem(key, value) { stored.set(key, value); }
};
const window = {
  localStorage,
  matchMedia() { return { matches: false, addEventListener() {} }; }
};
const sandbox = {
  document,
  window,
  localStorage,
  fetch: async (url, options) => { requests.push({ url, options }); return { ok: true }; }
};
vm.runInNewContext(fs.readFileSync("assets/js/theme.js", "utf8"), sandbox);

assert.equal(root.getAttribute("data-theme"), null);
assert.equal(root.style.colorScheme, "light");
assert.equal(button.getAttribute("aria-pressed"), "false");
assert.equal(classes.has("bi-moon-fill"), true);
assert.equal(typeof handlers.click, "function");

handlers.click();
assert.equal(root.getAttribute("data-theme"), "dark");
assert.equal(root.style.colorScheme, "dark");
assert.equal(stored.get("ssp-theme"), "dark");
assert.equal(button.getAttribute("aria-pressed"), "true");
assert.equal(classes.has("bi-sun-fill"), true);
assert.equal(requests.length, 1);
assert.equal(requests[0].url, "/theme-preference");
assert.equal(requests[0].options.headers["X-CSRF-Token"], "test-csrf");
assert.equal(requests[0].options.body, JSON.stringify({ theme: "dark" }));

console.log("PASS shared theme initialization, toggle state and account synchronization");
