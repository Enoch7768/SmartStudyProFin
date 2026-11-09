<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/composer.json') || is_file($root . '/composer.lock')) {
    throw new RuntimeException('The production application must not require Composer manifests.');
}


$version = trim((string) file_get_contents($root . '/VERSION'));
if (!preg_match('/^[0-9]+\\.[0-9]+\\.[0-9]+$/', $version)) {
    throw new RuntimeException('VERSION must contain a semantic MAJOR.MINOR.PATCH value.');
}

foreach (glob($root . '/database/migrations/*.php') ?: [] as $migration) {
    if (!is_file($migration) || filesize($migration) < 100) {
        throw new RuntimeException('A database migration is missing or unexpectedly empty: ' . basename($migration));
    }
}
if (count(glob($root . '/database/migrations/*.php') ?: []) !== 21) {
    throw new RuntimeException('The release must contain the complete 21-step migration history.');
}

foreach ([
    'database/migrations/001_identity.php',
    'database/migrations/015_contact_messages.php',
    'database/migrations/016_refund_item_allocations.php',
    'database/migrations/017_physical_shipping_fulfillment.php',
    'database/migrations/018_digital_read_download_entitlements.php',
    'database/migrations/019_course_offering_purchases.php',
    'database/migrations/020_physical_stock_reservations.php',
    'database/migrations/021_offering_completion_certificates.php',
    'instructor.php',
    '.env.example',
    'docs/INSTALLATION.md',
    'docs/RELEASES_UPDATES_BACKUPS.md',
] as $requiredFile) {
    if (!is_file($root . '/' . $requiredFile)) {
        throw new RuntimeException('A required release file is missing: ' . $requiredFile);
    }
}

$environmentExample = (string) file_get_contents($root . '/.env.example');
foreach (['APP_ENV=production', 'APP_DEBUG=false', 'DEMO_PAYMENTS_ALLOWED=false'] as $safeDefault) {
    if (!str_contains($environmentExample, $safeDefault)) {
        throw new RuntimeException('The release environment example is missing a safe default: ' . $safeDefault);
    }
}

if (is_file($root . '/auth.php')) {
    throw new RuntimeException('The retired legacy authentication endpoint must not be shipped.');
}

$releaseWorkflow = (string) file_get_contents($root . '/.github/workflows/release.yml');
foreach ([
    'php-version: ${{ matrix.php }}',
    "php: ['8.2', '8.3']",
    'php tests/config-secrets.php',
    'php tests/contact.php',
    'php tests/commerce-phase5.php',
    'php tests/demo-payments.php',
    'php tests/meetings.php',
    'php tests/instructor-workspace.php',
    'php tests/assistant.php',
    'php tests/recognition.php',
    'php tests/operations.php',
    'php tests/content-security.php',
    'php tests/performance.php',
    'php tests/security.php',
    'php tests/site-smoke.php',
    'node tests/main-js.test.cjs',
    'node tests/cms-theme.test.cjs',
    'node tests/media-library.test.cjs',
] as $requiredCheck) {
    if (!str_contains($releaseWorkflow, $requiredCheck)) {
        throw new RuntimeException('The release workflow is missing a required validation: ' . $requiredCheck);
    }
}

if (is_dir($root . '/cms')) {
    throw new RuntimeException('The retired legacy CMS must not remain in the active source tree.');
}

$bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
if (str_contains($bootstrap, 'vendor/autoload.php')) {
    throw new RuntimeException('The production bootstrap must not load a third-party Composer runtime.');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $entry) {
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($root) + 1));
    $segments = explode('/', $relative);
    if (in_array('node_modules', $segments, true)) {
        throw new RuntimeException('Installed Node dependencies must not be committed: ' . $relative);
    }
    if (str_contains('/' . $relative, '/vendor/autoload.php') || str_contains('/' . $relative, '/vendor/composer/installed.php')) {
        throw new RuntimeException('Installed Composer dependencies must not be committed: ' . $relative);
    }
    if (strtolower(basename($relative)) === 'composer.phar') {
        throw new RuntimeException('The Composer executable must not be committed.');
    }
}

$ignore = (string) file_get_contents($root . '/.gitignore');
foreach (['**/vendor/', '**/node_modules/', '**/composer.phar', '!**/assets/vendor/', '!**/assets/vendor/**'] as $rule) {
    if (!str_contains($ignore, $rule)) {
        throw new RuntimeException('The dependency ignore policy is missing: ' . $rule);
    }
}

foreach (['assets/vendor/bootstrap/css/bootstrap.min.css', 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js'] as $runtimeAsset) {
    if (!is_file($root . '/' . $runtimeAsset)) {
        throw new RuntimeException('A browser runtime asset required by the existing website is missing: ' . $runtimeAsset);
    }
}

echo "PASS no Composer manifests or third-party PHP runtime required\n";
echo "PASS installed Composer and Node dependency trees excluded from source\n";
echo "PASS Composer executable excluded from source\n";
echo "PASS browser runtime assets retained\n";
