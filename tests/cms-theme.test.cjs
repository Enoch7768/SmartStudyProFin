const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/cms-theme.js', 'utf8');

function execute(options = {}) {
  const attributes = {};
  const listeners = {};
  const storage = { value: options.savedTheme ?? null };
  const button = {
    attributes: {},
    textContent: '',
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, callback) { listeners[name] = callback; }
  };
  const root = {
    setAttribute(name, value) { attributes[name] = value; },
    removeAttribute(name) { delete attributes[name]; },
    getAttribute(name) { return attributes[name] ?? null; }
  };
  const localStorage = {
    getItem() {
      if (options.storageThrows) throw new Error('Storage unavailable');
      return storage.value;
    },
    setItem(name, value) {
      if (options.storageThrows) throw new Error('Storage unavailable');
      storage.value = value;
    }
  };
  const documentListeners = {};
  const document = {
    documentElement: root,
    readyState: options.readyState ?? 'complete',
    getElementById(id) { return id === 'cms-theme-toggle' ? button : null; },
    addEventListener(name, callback) { documentListeners[name] = callback; }
  };
  const window = {
    localStorage,
    matchMedia: options.matchMedia === false ? undefined : () => ({ matches: Boolean(options.prefersDark) })
  };
  vm.runInNewContext(source, { document, window });
  if (documentListeners.DOMContentLoaded) documentListeners.DOMContentLoaded();
  return { attributes, storage, button, listeners };
}

const savedDark = execute({ savedTheme: 'dark' });
assert.equal(savedDark.attributes['data-theme'], 'dark');
assert.equal(savedDark.button.attributes['aria-pressed'], 'true');
assert.equal(savedDark.button.textContent, 'Light mode');
savedDark.listeners.click();
assert.equal(savedDark.attributes['data-theme'], undefined);
assert.equal(savedDark.storage.value, 'light');
assert.equal(savedDark.button.attributes['aria-pressed'], 'false');

const systemDark = execute({ prefersDark: true });
assert.equal(systemDark.attributes['data-theme'], 'dark');

const storageUnavailable = execute({ storageThrows: true, matchMedia: false });
assert.equal(storageUnavailable.attributes['data-theme'], undefined);
assert.doesNotThrow(() => storageUnavailable.listeners.click());
assert.equal(storageUnavailable.attributes['data-theme'], 'dark');

const invalidPreference = execute({ savedTheme: 'unknown', prefersDark: false });
assert.equal(invalidPreference.attributes['data-theme'], undefined);
const cmsPage = fs.readFileSync('admin/cms.php', 'utf8');
assert.equal((cmsPage.match(/id="cms-theme-toggle"/g) || []).length, 2);
assert.equal((cmsPage.match(/Url::to\('\/assets\/js\/cms-theme\.js'\)/g) || []).length, 2);
assert.ok(cmsPage.includes('html[data-theme="dark"] .panel'));
assert.ok(cmsPage.includes('z-index:10;width:auto;margin:0;'));
assert.ok(cmsPage.includes('name="site.favicon_media_id"'));
assert.ok(cmsPage.includes('href="product-assets.php"'));
const productAssetPage = fs.readFileSync('admin/product-assets.php', 'utf8');
assert.ok(productAssetPage.includes('../assets/js/cms-theme.js'));
assert.ok(productAssetPage.includes('id="cms-theme-toggle"'));
assert.ok(productAssetPage.includes('html[data-theme="dark"] .form-select'));
assert.ok(productAssetPage.includes('Private files delivered after purchase'));
assert.ok(productAssetPage.includes('Public product gallery images'));
assert.ok(cmsPage.includes('href="../favicon.php"'));
console.log('PASS CMS sign-in and dashboard expose dark mode and site favicon settings');

console.log('PASS CMS theme preference, system fallback, accessible toggle and storage failure handling');
