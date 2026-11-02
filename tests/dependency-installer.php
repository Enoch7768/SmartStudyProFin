<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\DependencyInstaller;

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/composer.json'), true);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
if (!is_array($manifest) || !is_array($lock) || !isset($manifest['require'], $lock['packages'])) throw new RuntimeException('Composer manifest and lock file must be valid.');
$storage = sys_get_temp_dir() . '/smartstudypro-dependency-test-' . bin2hex(random_bytes(5));
putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_PATH=' . $storage . '/test.sqlite');
Config::load($root);
if (DependencyInstaller::isReady($root)) throw new RuntimeException('A fresh private storage directory must not report dependencies as installed.');
foreach (DependencyInstaller::requirements() as $label => $passed) {
    if (!is_bool($passed)) throw new RuntimeException('Dependency requirement checks must return booleans.');
}
if (!is_file($root . '/composer.json') || !is_file($root . '/composer.lock')) throw new RuntimeException('The deployment must include dependency manifests.');
if (is_dir($root . '/vendor')) throw new RuntimeException('Composer dependencies must not be installed inside the application source tree.');
echo "PASS locked dependency manifest validation\nPASS private vendor directory selection\nPASS automatic installer host capability checks\n";
