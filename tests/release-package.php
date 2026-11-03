<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/composer.json'), true);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);

if (!is_array($manifest) || !is_array($lock) || !isset($manifest['require'], $lock['packages'])) {
    throw new RuntimeException('The Composer dependency manifests must remain valid and available to the installer.');
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
    if (in_array('vendor', $segments, true) && $relative !== 'assets/vendor' && !str_starts_with($relative, 'assets/vendor/')) {
        throw new RuntimeException('Installed Composer dependencies must not be committed: ' . $relative);
    }
    if (strtolower(basename($relative)) === 'composer.phar') {
        throw new RuntimeException('The Composer executable must be downloaded by the installer, not committed.');
    }
}

$ignore = (string) file_get_contents($root . '/.gitignore');
foreach (['**/vendor/', '**/node_modules/', '**/composer.phar', '!/assets/vendor/', '!/assets/vendor/**'] as $rule) {
    if (!str_contains($ignore, $rule)) {
        throw new RuntimeException('The dependency ignore policy is missing: ' . $rule);
    }
}

foreach (['assets/vendor/bootstrap/css/bootstrap.min.css', 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js'] as $runtimeAsset) {
    if (!is_file($root . '/' . $runtimeAsset)) {
        throw new RuntimeException('A browser runtime asset required by the existing website is missing: ' . $runtimeAsset);
    }
}

echo "PASS Composer manifests retained for deterministic installer setup\n";
echo "PASS installed Composer and Node dependency trees excluded from source\n";
echo "PASS Composer executable excluded from source\n";
echo "PASS browser runtime assets retained\n";
