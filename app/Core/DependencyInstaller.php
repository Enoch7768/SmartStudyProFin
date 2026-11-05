<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use ZipArchive;

final class DependencyInstaller
{
    private const SOURCE_REPOSITORY = 'Enoch7768/sspfvfv2';
    private const SOURCE_COMMIT = '65dda1f32e20bcdf305edecd4eb775fa9f03fb7d';
    private const MAX_ARCHIVE_BYTES = 120000000;
    private const MAX_ARCHIVE_ENTRIES = 25000;

    public static function requirements(): array
    {
        return [
            'PHP 8.2 or newer' => version_compare(PHP_VERSION, '8.2.0', '>='),
            '64-bit PHP runtime' => PHP_INT_SIZE === 8,
            'PHP cURL extension' => extension_loaded('curl'),
            'PHP ZIP extension' => extension_loaded('zip') && class_exists(ZipArchive::class),
            'PHP GD extension' => extension_loaded('gd'),
            'PHP mbstring extension' => extension_loaded('mbstring'),
            'PHP SimpleXML extension' => extension_loaded('simplexml'),
            'PHP MongoDB extension' => extension_loaded('mongodb'),
            'PHP OpenSSL extension' => extension_loaded('openssl'),
            'Outbound HTTPS access' => extension_loaded('curl') && extension_loaded('openssl'),
        ];
    }

    public static function isReady(string $root): bool
    {
        $vendor = self::vendorPath();
        $lockPath = $root . DIRECTORY_SEPARATOR . 'composer.lock';
        $installedPath = $vendor . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
        $required = [
            $vendor . DIRECTORY_SEPARATOR . 'autoload.php',
            $vendor . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_real.php',
            $vendor . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'platform_check.php',
            $vendor . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.php',
            $installedPath,
        ];
        foreach ($required as $path) if (!is_file($path) || is_link($path)) return false;
        if (!is_file($lockPath) || is_link($lockPath)) return false;
        try {
            $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
            $installed = json_decode((string) file_get_contents($installedPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($lock['packages'] ?? null) || !is_array($installed['packages'] ?? null) || !is_string($lock['content-hash'] ?? null) || preg_match('/^[a-f0-9]{32}$/i', $lock['content-hash']) !== 1) return false;
            $expected = self::packageVersions($lock['packages']);
            $actual = self::packageVersions($installed['packages']);
            ksort($expected);
            ksort($actual);
            if ($expected === [] || $expected !== $actual) return false;
            $autoload = (string) file_get_contents($vendor . DIRECTORY_SEPARATOR . 'autoload.php');
            return str_contains($autoload, 'ComposerAutoloaderInit' . ($lock['content-hash'] ?? ''));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function install(string $root): void
    {
        if (self::isReady($root)) return;
        foreach (self::requirements() as $label => $passed) {
            if (!$passed) throw new RuntimeException('Automatic dependency setup requires: ' . $label . '. Enable it in the PHP web runtime and retry /install.');
        }
        $lockPath = $root . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($lockPath) || is_link($lockPath)) throw new RuntimeException('The application dependency lock file is missing or unsafe.');
        try {
            $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('The application dependency lock file is invalid.');
        }
        if (!is_array($lock['packages'] ?? null) || !is_string($lock['content-hash'] ?? null)) {
            throw new RuntimeException('The application dependency lock file is incomplete.');
        }
        $storage = rtrim((string) Config::get('storage.path'), DIRECTORY_SEPARATOR);
        if ($storage === '' || !Installer::storageIsPrivate($root)) throw new RuntimeException('Dependency setup requires private storage outside the public web root.');
        if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) throw new RuntimeException('Private storage could not be created for dependencies.');
        if (!is_writable($storage)) throw new RuntimeException('Private storage is not writable by PHP.');
        chmod($storage, 0700);

        $archivePath = $storage . DIRECTORY_SEPARATOR . 'vendor-source-' . bin2hex(random_bytes(8)) . '.zip';
        $stage = $storage . DIRECTORY_SEPARATOR . 'vendor-stage-' . bin2hex(random_bytes(8));
        $vendor = self::vendorPath();
        $backup = $storage . DIRECTORY_SEPARATOR . 'vendor-backup-' . bin2hex(random_bytes(8));
        try {
            self::downloadArchive($archivePath);
            self::extractArchive($archivePath, $stage);
            self::validateVendor($stage, $lock);
            if (is_link($vendor)) throw new RuntimeException('The existing private vendor path cannot be a symbolic link.');
            if (file_exists($vendor) && !is_dir($vendor)) throw new RuntimeException('The existing dependency path is not a directory.');
            if (is_dir($vendor) && !rename($vendor, $backup)) throw new RuntimeException('The existing dependency directory could not be safely replaced.');
            if (!rename($stage, $vendor)) {
                if (is_dir($backup)) @rename($backup, $vendor);
                throw new RuntimeException('The verified dependencies could not be activated in private storage.');
            }
            try {
                self::setPermissions($vendor);
            } catch (\Throwable $exception) {
                self::removeDirectory($vendor);
                if (is_dir($backup)) @rename($backup, $vendor);
                throw $exception;
            }
            if (!self::isReady($root)) {
                self::removeDirectory($vendor);
                if (is_dir($backup)) @rename($backup, $vendor);
                throw new RuntimeException('The installed dependency files failed their final integrity check. Retry /install.');
            }
            if (is_dir($backup)) self::removeDirectory($backup);
        } finally {
            if (is_file($archivePath)) @unlink($archivePath);
            if (is_dir($stage)) self::removeDirectory($stage);
        }
    }

    private static function vendorPath(): string
    {
        return rtrim((string) Config::get('storage.path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor';
    }

    private static function packageVersions(array $packages): array
    {
        $versions = [];
        foreach ($packages as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null)) return [];
            $name = strtolower($package['name']);
            if (isset($versions[$name])) return [];
            $versions[$name] = $package['version'];
        }
        return $versions;
    }

    private static function downloadArchive(string $destination): void
    {
        $url = 'https://codeload.github.com/' . self::SOURCE_REPOSITORY . '/legacy.zip/' . self::SOURCE_COMMIT;
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('The GitHub dependency archive download could not be started.');
        $file = fopen($destination, 'xb');
        if ($file === false) {
            curl_close($handle);
            throw new RuntimeException('A temporary dependency archive could not be created in private storage.');
        }
        $bytes = 0;
        $options = [
            CURLOPT_FILE => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'SmartStudyPro-Installer/1.0',
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function ($curl, float $downloadTotal, float $downloaded) use (&$bytes): int {
                $bytes = (int) $downloaded;
                return $bytes > self::MAX_ARCHIVE_BYTES ? 1 : 0;
            },
        ];
        curl_setopt_array($handle, $options);
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        fclose($file);
        if ($success !== true || $status !== 200 || $bytes <= 0 || $bytes > self::MAX_ARCHIVE_BYTES) {
            @unlink($destination);
            throw new RuntimeException('Could not download the prebuilt PHP dependencies from GitHub. ' . ($error !== '' ? $error : 'Check outbound HTTPS access and retry /install.'));
        }
        chmod($destination, 0600);
    }

    private static function extractArchive(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) throw new RuntimeException('The downloaded GitHub dependency archive is not a valid ZIP file.');
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) throw new RuntimeException('The dependency archive has an unexpected number of files.');
            $rootPrefix = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) throw new RuntimeException('The dependency archive contains an unsafe file path.');
                $parts = explode('/', str_replace('\\', '/', $name));
                if ($parts[0] === '' || $parts[0] === '.' || $parts[0] === '..') throw new RuntimeException('The dependency archive has an unexpected directory structure.');
                if ($rootPrefix === null) $rootPrefix = $parts[0] . '/';
                if ($parts[0] . '/' !== $rootPrefix || (count($parts) === 1 && !str_ends_with($name, '/'))) throw new RuntimeException('The dependency archive contains multiple root directories.');
                foreach ($parts as $part) if ($part === '..' || $part === '.') throw new RuntimeException('The dependency archive contains a path traversal entry.');
                $stat = $zip->statIndex($i);
                if (!is_array($stat) || (int) ($stat['size'] ?? 0) > self::MAX_ARCHIVE_BYTES) throw new RuntimeException('The dependency archive contains an oversized entry.');
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('The dependency archive contains a symbolic link.');
            }
            if (!is_dir($destination) && !mkdir($destination, 0700, true)) throw new RuntimeException('A private temporary directory could not be created for dependencies.');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                $relative = substr($name, strlen($rootPrefix));
                if ($relative === '') continue;
                $target = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (str_ends_with($name, '/')) {
                    if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) throw new RuntimeException('A dependency directory could not be extracted.');
                    continue;
                }
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) throw new RuntimeException('A dependency directory could not be extracted.');
                $input = $zip->getStream($name);
                $output = fopen($target, 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    throw new RuntimeException('A dependency file could not be extracted safely.');
                }
                $copied = stream_copy_to_stream($input, $output, self::MAX_ARCHIVE_BYTES + 1);
                fclose($input);
                fclose($output);
                if (!is_int($copied) || $copied > self::MAX_ARCHIVE_BYTES) throw new RuntimeException('An extracted dependency file exceeded the allowed size.');
                chmod($target, 0600);
            }
        } finally {
            $zip->close();
        }
    }

    private static function validateVendor(string $vendor, array $lock): void
    {
        foreach (['autoload.php', 'composer/autoload_real.php', 'composer/platform_check.php', 'composer/installed.json', 'composer/installed.php'] as $file) {
            if (!is_file($vendor . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file)) || is_link($vendor . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
                throw new RuntimeException('The GitHub dependency archive is incomplete: ' . $file . ' is missing.');
            }
        }
        $installed = json_decode((string) file_get_contents($vendor . '/composer/installed.json'), true);
        if (!is_array($installed['packages'] ?? null)) throw new RuntimeException('The downloaded dependency package manifest is invalid.');
        $expected = self::packageVersions($lock['packages']);
        $actual = self::packageVersions($installed['packages']);
        ksort($expected);
        ksort($actual);
        if ($expected === [] || $expected !== $actual) throw new RuntimeException('The prebuilt dependency versions do not exactly match this SmartStudyPro release. Contact the maintainer to update the dependency archive.');
        $autoload = (string) file_get_contents($vendor . '/autoload.php');
        if (!str_contains($autoload, 'ComposerAutoloaderInit' . $lock['content-hash'])) throw new RuntimeException('The downloaded Composer autoloader was generated for a different dependency lock file.');
    }

    private static function setPermissions(string $directory): void
    {
        @chmod($directory, 0700);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()) throw new RuntimeException('The dependency directory contains an unexpected symbolic link.');
            @chmod($entry->getPathname(), $entry->isDir() ? 0700 : 0600);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($directory);
    }
}
