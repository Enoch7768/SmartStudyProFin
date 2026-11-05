<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\\Config;
use App\Core\\DependencyInstaller;

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($manifest['require'] ?? null) || !is_array($lock['packages'] ?? null) || !is_string($lock['content-hash'] ?? null)) {
    throw new RuntimeException('Composer manifests must provide a valid locked dependency set.');
}

$storage = sys_get_temp_dir() . '/smartstudypro-dependency-test-' . bin2hex(random_bytes(5));
putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_PATH=' . $storage . '/test.sqlite');
Config::load($root);

$cleanup = static function (string $path) use (&$cleanup): void {
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) @unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path) as $entry) $cleanup($entry->getPathname());
    @rmdir($path);
};

try {
    if (DependencyInstaller::isReady($root)) throw new RuntimeException('Fresh private storage must not report dependencies as installed.');
    if (!mkdir($storage, 0700, true)) throw new RuntimeException('Could not create isolated dependency test storage.');

    $vendor = $storage . '/vendor';
    if (!mkdir($vendor . '/composer', 0700, true)) throw new RuntimeException('Could not create simulated Composer metadata.');
    $installedPackages = array_map(
        static fn (array $package): array => ['name' => $package['name'], 'version' => $package['version']],
        $lock['packages']
    );
    file_put_contents($vendor . '/autoload.php', '<?php return new stdClass();');
    file_put_contents($vendor . '/composer/autoload_real.php', '<?php');
    file_put_contents($vendor . '/composer/platform_check.php', '<?php');
    file_put_contents($vendor . '/composer/installed.php', '<?php return [];');
    file_put_contents($vendor . '/composer/installed.json', json_encode(['packages' => $installedPackages], JSON_THROW_ON_ERROR));
    file_put_contents($vendor . '/autoload.php', '<?php class ComposerAutoloaderInit' . $lock['content-hash'] . ' {}');

    if (!DependencyInstaller::isReady($root)) throw new RuntimeException('A complete lock-matched private vendor directory was not recognized.');

    array_pop($installedPackages);
    file_put_contents($vendor . '/composer/installed.json', json_encode(['packages' => $installedPackages], JSON_THROW_ON_ERROR));
    if (DependencyInstaller::isReady($root)) throw new RuntimeException('A partial dependency set was incorrectly marked ready.');

    file_put_contents($vendor . '/composer/installed.json', json_encode(['packages' => array_merge($installedPackages, [['name' => 'unexpected/package', 'version' => '1.0.0']])], JSON_THROW_ON_ERROR));
    if (DependencyInstaller::isReady($root)) throw new RuntimeException('An extra dependency was incorrectly accepted.');

    file_put_contents($vendor . '/composer/installed.json', json_encode(['packages' => array_map(
        static fn (array $package): array => ['name' => $package['name'], 'version' => $package['version'] . '-changed'],
        $lock['packages']
    )], JSON_THROW_ON_ERROR));
    if (DependencyInstaller::isReady($root)) throw new RuntimeException('Mismatched dependency versions were incorrectly accepted.');

    file_put_contents($vendor . '/composer/installed.json', json_encode(['packages' => $installedPackages], JSON_THROW_ON_ERROR));
    file_put_contents($vendor . '/autoload.php', '<?php class ComposerAutoloaderInitwrong {}');
    if (DependencyInstaller::isReady($root)) throw new RuntimeException('An autoloader generated for another lock file was incorrectly accepted.');

    foreach (DependencyInstaller::requirements() as $label => $passed) {
        if (!is_bool($passed)) throw new RuntimeException('Dependency requirement checks must return booleans: ' . $label);
    }
    if (array_key_exists('PHP CLI OpenSSL', DependencyInstaller::requirements())) throw new RuntimeException('The web installer must not require a separate PHP CLI runtime.');
    if (!str_contains((string) file_get_contents($root . '/install/index.php'), 'DependencyInstaller::install($application[\'root\'])')) {
        throw new RuntimeException('The browser installer must run the dependency setup automatically.');
    }
    if (!is_file($root . '/composer.json') || !is_file($root . '/composer.lock')) throw new RuntimeException('The release must include both dependency manifests.');
    if (is_dir($root . '/vendor')) throw new RuntimeException('Dependencies must remain outside the application source tree.');
} finally {
    $cleanup($storage);
}

echo "PASS exact locked package manifest validation\n";
echo "PASS private vendor readiness and autoloader integrity checks\n";
echo "PASS missing, extra, and mismatched package rejection\n";
echo "PASS automatic browser installer integration\n";
