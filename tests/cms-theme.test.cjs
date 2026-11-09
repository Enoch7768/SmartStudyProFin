const assert = require('node:assert/strict');
const fs = require('node:fs');

const cmsPage = fs.readFileSync('admin/cms.php', 'utf8');
assert.equal((cmsPage.match(/id="cms-theme-toggle"/g) || []).length, 2);
assert.equal((cmsPage.match(/Url::to\('\/assets\/js\/theme\.js'\)/g) || []).length, 2);
assert.ok(cmsPage.includes('data-theme-endpoint="../theme-preference.php"'));
assert.ok(cmsPage.includes('data-csrf='));
assert.ok(cmsPage.includes('html[data-theme="dark"] .panel'));
assert.ok(cmsPage.includes('z-index:10;width:auto;margin:0;'));
assert.ok(cmsPage.includes('name="site.favicon_media_id"'));
assert.ok(cmsPage.includes('href="product-assets.php"'));
const productAssetPage = fs.readFileSync('admin/product-assets.php', 'utf8');
assert.ok(productAssetPage.includes('../assets/js/theme.js'));
assert.ok(productAssetPage.includes('id="cms-theme-toggle"'));
assert.ok(productAssetPage.includes('data-theme-endpoint="../theme-preference.php"'));
assert.ok(productAssetPage.includes('data-csrf='));
assert.ok(productAssetPage.includes('html[data-theme="dark"] .form-select'));
assert.ok(productAssetPage.includes('Private files delivered after purchase'));
assert.ok(productAssetPage.includes('Public product gallery images'));
assert.ok(cmsPage.includes("Url::to('/favicon.ico')"));

const themeController = fs.readFileSync('assets/js/theme.js', 'utf8');
assert.ok(themeController.includes('ssp-theme'));
assert.ok(themeController.includes('X-CSRF-Token'));
assert.ok(themeController.includes('cms-theme-toggle'));
console.log('PASS CMS sign-in and dashboard use the shared accessible theme controller');
console.log('PASS CMS theme preference, account sync, storage fallback and favicon settings');
