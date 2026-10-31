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
  const documentListeners = {};
  const document = {
    body,
    querySelector(selector) {
      if (selector === 'body') return body;
      if (selector === '#header') return header;
      if (selector === '.mobile-nav-toggle') return button;
      return null;
    },
    querySelectorAll(selector) {
      return selector === '#navmenu a' && options.link ? [link] : [];
    },
    addEventListener(name, callback) {
      documentListeners[name] = callback;
    }
  };
  const window = {
    scrollY: 0,
    addEventListener() {},
    scrollTo() {}
  };
  vm.runInNewContext(source, { document, window });
  return { body, header, button, icon, link, documentListeners };
}

assert.doesNotThrow(() => execute());
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
console.log('PASS keyboard-operable mobile navigation and accessible state');
console.log('PASS escape and link navigation close the mobile menu');
