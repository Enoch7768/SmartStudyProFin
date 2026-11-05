const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/main.js', 'utf8');

class FakeClassList {
  constructor(values = []) {
    this.values = new Set(values);
  }

  contains(value) {
    return this.values.has(value);
  }

  add(value) {
    this.values.add(value);
  }

  remove(value) {
    this.values.delete(value);
  }

  toggle(value, force) {
    const next = force === undefined ? !this.values.has(value) : Boolean(force);
    if (next) this.values.add(value);
    else this.values.delete(value);
    return next;
  }
}

class FakeElement {
  constructor(classes = []) {
    this.classList = new FakeClassList(classes);
    this.listeners = {};
    this.attributes = {};
    this.children = {};
    this.parentNode = null;
    this.nextElementSibling = null;
  }

  addEventListener(name, callback) {
    this.listeners[name] = callback;
  }

  setAttribute(name, value) {
    this.attributes[name] = value;
  }

  querySelector(selector) {
    return this.children[selector] || null;
  }

  focus() {
    this.focused = true;
  }
}

function execute(options = {}) {
  const body = options.body === false ? null : new FakeElement();
  const header = options.header ? new FakeElement(['sticky-top']) : null;
  const icon = new FakeElement(['bi-list']);
  const button = options.button ? new FakeElement() : null;
  if (button) button.children.i = icon;
  const link = new FakeElement();
  const animated = new FakeElement();
  const documentListeners = {};
  const windowListeners = {};
  const document = {
    readyState: 'loading',
    body,
    querySelector(selector) {
      if (selector === 'body') return body;
      if (selector === '#header') return header;
      if (selector === '.mobile-nav-toggle') return button;
      return null;
    },
    querySelectorAll(selector) {
      if (selector === '#navmenu a' && options.link) return [link];
      if (selector === '[data-aos]') return [animated];
      return [];
    },
    addEventListener(name, callback) {
      documentListeners[name] = callback;
    }
  };
  const window = {
    scrollY: 0,
    addEventListener(name, callback) {
      windowListeners[name] = windowListeners[name] || [];
      windowListeners[name].push(callback);
    },
    matchMedia() {
      return { matches: Boolean(options.reducedMotion) };
    },
    scrollTo() {}
  };
  const animationLibrary = options.animationLibrary ? { init(config) { options.animationConfig = config; } } : undefined;
  vm.runInNewContext(source, { document, window, AOS: animationLibrary });
  function fireWindowEvent(name) {
    for (const callback of windowListeners[name] || []) callback();
  }
  return { body, header, button, icon, link, animated, documentListeners, windowListeners, fireWindowEvent, options };
}

assert.doesNotThrow(() => execute());
const noAnimationLibrary = execute();
noAnimationLibrary.fireWindowEvent('load');
assert.equal(noAnimationLibrary.animated.classList.contains('aos-animate'), true);
const reducedMotion = execute({ reducedMotion: true, animationLibrary: true });
reducedMotion.fireWindowEvent('load');
assert.equal(reducedMotion.options.animationConfig.disable, true);
const animationReady = execute({ animationLibrary: true });
animationReady.fireWindowEvent('load');
assert.equal(animationReady.options.animationConfig.once, true);
const app = execute({ header: true, button: true, link: true });
assert.equal(app.body.classList.contains('mobile-nav-active'), false);
app.button.listeners.click();
assert.equal(app.body.classList.contains('mobile-nav-active'), true);
assert.equal(app.button.attributes['aria-expanded'], 'true');
assert.equal(app.button.attributes['aria-label'], 'Close navigation');
assert.equal(app.icon.classList.contains('bi-x'), true);
app.documentListeners.keydown({ key: 'Escape' });
assert.equal(app.body.classList.contains('mobile-nav-active'), false);
assert.equal(app.button.attributes['aria-expanded'], 'false');
assert.equal(app.button.focused, true);
app.button.listeners.click();
app.link.listeners.click();
assert.equal(app.body.classList.contains('mobile-nav-active'), false);
console.log('PASS optional frontend dependencies and missing elements');
console.log('PASS animated content remains visible without AOS and with reduced motion');
console.log('PASS keyboard-operable mobile navigation and accessible state');
console.log('PASS escape and link navigation close the mobile menu');

const homepage = fs.readFileSync('index.php', 'utf8');
const sharedStylesheet = fs.readFileSync('assets/css/main.css', 'utf8');
assert.ok(homepage.includes('assets/css/main.css'));
assert.match(sharedStylesheet, /:where\(a, button, input, select, textarea, summary, \[tabindex\]\):focus-visible/);
assert.match(sharedStylesheet, /prefers-reduced-motion: reduce/);
console.log('PASS homepage loads the shared accessibility stylesheet');

const profilePage = fs.readFileSync('profile.php', 'utf8');
assert.ok(profilePage.includes('const preferredTheme ='));
assert.ok(profilePage.includes("localStorage.setItem('ssp-theme', preferredTheme)"));
const sharedNavigation = fs.readFileSync('nav.php', 'utf8');
assert.ok(homepage.includes("include __DIR__ . '/nav.php';"));
assert.doesNotMatch(homepage, /id="header"/);
assert.ok(sharedNavigation.includes("$navRoute('/courses')"));
assert.ok(sharedNavigation.includes("$navActive('courses')"));
assert.ok(sharedNavigation.includes("str_replace('\\\\', '/'") , 'navigation should normalize Windows path separators');
assert.doesNotMatch(sharedNavigation, /href="(?:index|about|courses|learning|assistant|products|contact|notifications|profile|cart)\\.php"/);
assert.match(sharedNavigation, /aria-controls="navmenu"/);
assert.match(sharedNavigation, /aria-expanded="false"/);
assert.ok(sharedNavigation.includes('data-theme-endpoint='));
assert.ok(sharedNavigation.includes('data-csrf='));
assert.ok(sharedNavigation.includes('fetch(endpoint'));
console.log('PASS homepage uses the shared accessible navigation and course entry point');

for (const page of ['index.php', 'about.php', 'contact.php', 'courses.php', 'products.php', 'product-details.php', 'login.php', 'register.php']) {
  const source = fs.readFileSync(page, 'utf8');
  assert.ok(source.includes('assets/css/main.css'), page + ' must load shared accessibility styles through a root-safe URL');
}
console.log('PASS public pages load shared accessibility and reduced-motion styles');

for (const page of ['index.php', 'about.php', 'contact.php', 'courses.php', 'products.php', 'product-details.php', 'login.php', 'register.php', 'certificates.php', 'achievements.php', 'notifications.php', 'reset.php', 'learning.php', 'study.php', 'profile.php', 'assistant.php', 'course-details.php', 'cart.php', 'checkout.php', 'orders.php', 'legal.php', 'payment-choice.php', 'payment-return.php']) {
  const source = fs.readFileSync(page, 'utf8');
  assert.ok(source.includes("'/favicon.php'"), page + ' must use the CMS-managed favicon');
  assert.ok(source.includes("Url::to('/favicon.php')"), page + ' must resolve the favicon through the central URL helper');
}
console.log('PASS CMS-managed favicon is used across public, learning, account and checkout pages');

const deviceResponsiveStyles = fs.readFileSync('assets/css/device-responsive.css', 'utf8');
assert.match(deviceResponsiveStyles, /max-width:\s*280px/);
assert.match(deviceResponsiveStyles, /min-width:\s*1920px/);
assert.match(deviceResponsiveStyles, /horizontal-viewport-segments:\s*2/);
assert.match(deviceResponsiveStyles, /horizontal-viewport-segments:\s*3/);
assert.match(deviceResponsiveStyles, /vertical-viewport-segments:\s*2/);
assert.match(deviceResponsiveStyles, /pointer:\s*coarse/);
assert.match(deviceResponsiveStyles, /prefers-reduced-motion:\s*reduce/);
assert.doesNotMatch(deviceResponsiveStyles, /header-icon-link\[aria-label="(?:Notifications|My profile)"\]\s*\{\s*display:\s*none/);
console.log('PASS compact, tablet, large-display, foldable and reduced-motion responsive rules');

for (const page of ['index.php', 'about.php', 'contact.php', 'courses.php', 'products.php', 'product-details.php', 'login.php', 'register.php', 'certificates.php', 'achievements.php', 'notifications.php', 'reset.php', 'learning.php', 'study.php', 'profile.php', 'assistant.php', 'course-details.php', 'cart.php', 'checkout.php', 'orders.php', 'legal.php', 'payment-choice.php', 'payment-return.php']) {
  const source = fs.readFileSync(page, 'utf8');
  assert.ok(source.includes('assets/css/device-responsive.css'), page + ' must load device-responsive styles');
}
const cmsSource = fs.readFileSync('admin/cms.php', 'utf8');
assert.ok(cmsSource.includes('../assets/css/device-responsive.css'));
console.log('PASS responsive stylesheet coverage across public pages and CMS');


