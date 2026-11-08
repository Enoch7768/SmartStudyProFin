<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/composer.json') || is_file($root . '/composer.lock')) {
    throw new RuntimeException('The production application must not require Composer manifests.');
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
    if ($relative === 'cms/lib/vendor') {
        throw new RuntimeException('The legacy Composer dependency tree must not be committed.');
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
