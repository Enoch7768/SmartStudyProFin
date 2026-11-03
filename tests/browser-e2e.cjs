const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn, spawnSync } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'ssp-browser-e2e-'));
const storage = path.join(temporary, 'private');
fs.mkdirSync(storage, { recursive: true, mode: 0o700 });
const key = require('node:crypto').randomBytes(32).toString('hex');
const environment = {
  ...process.env,
  APP_STORAGE_PATH: storage,
  DB_PATH: path.join(storage, 'smartstudypro.sqlite'),
  APP_KEY: key,
  APP_ENV: 'local',
  APP_DEBUG: 'false',
  DEMO_PAYMENTS_ALLOWED: 'true',
  APP_URL: 'http://127.0.0.1'
};

let server;
let browser;
let serverOutput = '';

async function reservePort() {
  return new Promise((resolve, reject) => {
    const socket = net.createServer();
    socket.once('error', reject);
    socket.listen(0, '127.0.0.1', () => {
      const port = socket.address().port;
      socket.close(error => error ? reject(error) : resolve(port));
    });
  });
}

async function waitForServer(url) {
  for (let attempt = 0; attempt < 80; attempt++) {
    try {
      const response = await fetch(url);
      if (response.status > 0) return;
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  throw new Error('The isolated PHP server did not become ready.');
}

async function run() {
  const bootstrap = [
    '$root = ' + JSON.stringify(root) + ';',
    'require $root . "/bootstrap/app.php";',
    'App\\Core\\Installer::install($root, "E2E Administrator", "e2e-admin@example.test", "E2E-Admin-Password-2026!");',
    '$pdo = App\\Core\\Database::connect();',
    '$content = new App\\Cms\\ContentRepository($pdo);',
    '$courseId = $content->save("courses", null, ["title" => "Browser Test Course", "description_html" => "A course used by browser tests.", "content_html" => "Course content.", "price_ugx" => "0", "category" => "Testing", "status" => "published", "sort_order" => "1"]);',
    '$chapterId = $content->save("chapters", null, ["course_id" => $courseId, "title" => "Getting Started", "sort_order" => "1"]);',
    '$content->save("lessons", null, ["chapter_id" => $chapterId, "title" => "First lesson", "content_html" => "This lesson confirms course enrolment and progress persistence.", "video_url" => "", "sort_order" => "1", "status" => "published"]);',
    '$content->save("courses", null, ["title" => "Paid Browser Course", "description_html" => "A paid course used by checkout browser tests.", "content_html" => "Paid course content.", "price_ugx" => "5000", "category" => "Testing", "status" => "published", "sort_order" => "2"]);',
    '$adminId = (int) $pdo->query("SELECT user_id FROM user_roles LIMIT 1")->fetchColumn();',
    '(new App\\\\Commerce\\\\DemoPaymentService($pdo))->setEnabled(true, $adminId);'
  ].join(' ');
  const setup = spawnSync('php', ['-r', bootstrap], { cwd: root, env: environment, encoding: 'utf8' });
  if (setup.status !== 0) throw new Error('Browser test database setup failed: ' + setup.stderr + setup.stdout);

  const port = await reservePort();
  const base = 'http://127.0.0.1:' + port;
  server = spawn('php', ['-S', '127.0.0.1:' + port, '-t', root, path.join(root, 'index.php')], {
    cwd: root,
    env: environment,
    stdio: ['ignore', 'pipe', 'pipe']
  });
  server.stdout.on('data', chunk => { serverOutput += chunk.toString(); });
  server.stderr.on('data', chunk => { serverOutput += chunk.toString(); });
  await waitForServer(base + '/login');

  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  page.setDefaultTimeout(12000);
  page.setDefaultNavigationTimeout(12000);
  const pageErrors = [];
  const localRequestFailures = [];
  const artifactDirectory = process.env.BROWSER_ARTIFACT_DIR ? path.resolve(process.env.BROWSER_ARTIFACT_DIR) : path.join(temporary, 'artifacts');
  fs.mkdirSync(artifactDirectory, { recursive: true });
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('requestfailed', request => {
    if (request.url().startsWith(base)) localRequestFailures.push(request.url() + ': ' + request.failure()?.errorText);
  });

  console.log('BROWSER CHECK: public page rendering');
  const publicRoutes = ['/', '/about', '/courses', '/products', '/contact', '/login', '/register', '/privacy', '/terms', '/cookies', '/accessibility', '/refunds', '/eula'];
  for (const route of publicRoutes) {
    const response = await page.goto(base + route, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, route + ' should render successfully');
    assert.ok(await page.locator('body').innerText().then(text => text.trim().length > 0), route + ' should not render a blank page');
    if (['/', '/about', '/courses', '/products', '/contact'].includes(route)) {
      assert.equal(await page.locator('#navmenu').count(), 1, route + ' should use the shared navigation');
      assert.equal(await page.locator('#theme-toggle').count(), 1, route + ' should expose the shared theme control');
    }
  }

  console.log('BROWSER CHECK: responsive navigation and theme controls');
  await page.goto(base + '/', { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: path.join(artifactDirectory, 'home-desktop.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(base + '/', { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: path.join(artifactDirectory, 'home-mobile.png'), fullPage: true });
  const mobileToggle = page.locator('.mobile-nav-toggle');
  await mobileToggle.click();
  assert.equal(await mobileToggle.getAttribute('aria-expanded'), 'true');
  await page.keyboard.press('Escape');
  assert.equal(await mobileToggle.getAttribute('aria-expanded'), 'false');
  await mobileToggle.click();
  await page.locator('#navmenu a[href="/courses"]').click();
  assert.equal(await mobileToggle.getAttribute('aria-expanded'), 'false');
  assert.equal(new URL(page.url()).pathname, '/courses');
  await page.goto(base + '/', { waitUntil: 'domcontentloaded' });

  const themeToggle = page.locator('#theme-toggle');
  await themeToggle.click();
  assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');
  assert.equal(await themeToggle.getAttribute('aria-pressed'), 'true');
  assert.equal(await page.evaluate(() => localStorage.getItem('ssp-theme')), 'dark');
  await page.reload({ waitUntil: 'domcontentloaded' });
  assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');
  await themeToggle.click();
  assert.equal(await page.locator('html').getAttribute('data-theme'), null);

  console.log('BROWSER CHECK: responsive viewport overflow');
  const responsiveRoutes = ['/', '/courses', '/products', '/login', '/register'];
  const viewports = [{ width: 320, height: 740 }, { width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1440, height: 1000 }];
  for (const viewport of viewports) {
    await page.setViewportSize(viewport);
    for (const route of responsiveRoutes) {
      await page.goto(base + route, { waitUntil: 'domcontentloaded' });
      const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        content: document.documentElement.scrollWidth
      }));
      assert.ok(dimensions.content <= dimensions.viewport + 1, route + ' overflows horizontally at ' + viewport.width + 'px: ' + JSON.stringify(dimensions));
    }
  }

  await page.setViewportSize({ width: 1440, height: 1000 });
  console.log('BROWSER CHECK: registration and profile persistence');
  const email = 'student-' + Date.now() + '@example.test';
  const password = 'Student-E2E-Password-2026!';
  await page.goto(base + '/register', { waitUntil: 'domcontentloaded' });
  await page.locator('#register-name').fill('Browser Test Student');
  await page.locator('#register-email').fill(email);
  await page.locator('#register-password').fill(password);
  await page.locator('#register-confirm-password').fill(password);
  await page.locator('#accept-terms').check();
  await page.locator('#accept-privacy').check();
  await page.getByRole('button', { name: 'Create Account' }).click();
  await page.waitForURL(/profile\.php|\/profile(?:$|[?#])/);
  await page.getByRole('heading', { name: 'Welcome, Browser Test Student' }).waitFor();
  assert.equal(await page.locator('#name').inputValue(), 'Browser Test Student');
  await page.screenshot({ path: path.join(artifactDirectory, 'profile-desktop.png'), fullPage: true });

  await page.locator('#name').fill('Updated Browser Student');
  await page.locator('#phone').fill('+256700000000');
  await page.locator('#location').fill('Kampala');
  await page.locator('#bio').fill('Testing the complete account workflow.');
  await page.locator('#preferred_theme').selectOption('dark');
  await page.getByRole('button', { name: 'Save profile' }).click();
  await page.getByText('Your profile has been updated.').waitFor();
  assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');

  const themeResponsePromise = page.waitForResponse(response => response.url().endsWith('/theme-preference') && response.request().method() === 'POST');
  await page.locator('#theme-toggle').click();
  const themeResponse = await themeResponsePromise;
  assert.equal(themeResponse.status(), 200, 'theme preference should persist through the authenticated backend');
  assert.equal((await themeResponse.json()).theme, 'light');
  await page.reload({ waitUntil: 'domcontentloaded' });
  assert.equal(await page.locator('html').getAttribute('data-theme'), null, 'profile should rehydrate the saved light theme from the database');
  assert.equal(await page.locator('#preferred_theme').inputValue(), 'light', 'profile form should reflect the theme preference changed in the header');
  assert.equal(await page.locator('#name').inputValue(), 'Updated Browser Student');

  console.log('BROWSER CHECK: course discovery, free enrolment and progress persistence');
  await page.goto(base + '/courses', { waitUntil: 'domcontentloaded' });
  await page.getByRole('link', { name: 'Browser Test Course' }).first().click();
  assert.match(new URL(page.url()).pathname, /^\/courses\/browser-test-course$/);
  await page.getByRole('link', { name: 'Start this free course' }).click();
  await page.getByRole('button', { name: 'Enrol for free' }).click();
  await page.getByRole('heading', { name: 'First lesson' }).waitFor();
  await page.getByRole('button', { name: /Mark complete/ }).click();
  await page.getByText('Lesson marked as complete. Your progress has been saved.').waitFor();
  await page.goto(base + '/learn', { waitUntil: 'domcontentloaded' });
  await page.getByText('Browser Test Course').first().waitFor();

  console.log('BROWSER CHECK: paid course checkout and simulated payment');
  await page.goto(base + '/courses', { waitUntil: 'domcontentloaded' });
  await page.getByRole('link', { name: 'Paid Browser Course' }).first().click();
  await page.getByRole('button', { name: 'Add course to cart' }).click();
  await page.getByRole('heading', { name: 'Your selected items' }).waitFor();
  await page.getByText('Paid Browser Course').waitFor();
  await page.getByRole('link', { name: 'Proceed to checkout' }).click();
  await page.getByRole('heading', { name: 'Review your order' }).waitFor();
  await page.getByRole('button', { name: 'Create order and choose payment' }).click();
  await page.waitForURL(/payment-choice\\.php/);
  await page.getByRole('button', { name: 'Simulate successful payment' }).click();
  await page.waitForURL(/orders\\.php/);
  await page.getByText('Demo payment: Success').waitFor();
  await page.getByText('Paid Browser Course').waitFor();

  console.log('BROWSER CHECK: authenticated learning and shopping pages');
  await page.goto(base + '/assistant', { waitUntil: 'domcontentloaded' });
  assert.equal(new URL(page.url()).pathname, '/assistant');
  await page.locator('#composer').waitFor();
  await page.goto(base + '/cart', { waitUntil: 'domcontentloaded' });
  assert.equal(new URL(page.url()).pathname, '/cart');
  await page.locator('main').waitFor();

  console.log('BROWSER CHECK: logout and login round trip');
  await page.goto(base + '/profile', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForURL(/index\.php|\/$/);
  await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
  await page.locator('#login-email').fill(email);
  await page.locator('#login-password').fill(password);
  await page.getByRole('button', { name: 'Sign In' }).click();
  await page.waitForURL(/profile\.php|\/profile(?:$|[?#])/);
  assert.equal(await page.locator('#name').inputValue(), 'Updated Browser Student');

  for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 1000 }]) {
    await page.setViewportSize(viewport);
    await page.goto(base + '/profile', { waitUntil: 'domcontentloaded' });
    const dimensions = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
    assert.ok(dimensions.content <= dimensions.viewport + 1, 'profile overflows horizontally at ' + viewport.width + 'px');
  }

  assert.deepEqual(pageErrors, [], 'browser JavaScript should not throw uncaught page errors');
  assert.deepEqual(localRequestFailures, [], 'local CSS, scripts and images should load');
  console.log('PASS real Chromium rendering for 13 public pages');
  console.log('PASS mobile navigation, Escape handling and route transitions');
  console.log('PASS theme switching, local persistence and authenticated database synchronization');
  console.log('PASS responsive overflow checks at 320, 390, 768 and 1440 pixels');
  console.log('PASS student registration, profile update, free and paid course enrolment, lesson completion, checkout, demo payment, protected pages, logout and login');
  console.log('PASS no uncaught browser errors or failed local asset requests');
  console.log('Browser screenshots: ' + artifactDirectory);
}

run().catch(error => {
  console.error(error);
  if (serverOutput) console.error(serverOutput);
  process.exitCode = 1;
}).finally(async () => {
  if (browser) await browser.close();
  if (server && !server.killed) server.kill('SIGTERM');
  fs.rmSync(temporary, { recursive: true, force: true });
});
