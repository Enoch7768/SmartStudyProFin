<?php

declare(strict_types=1);

namespace App\Core;

use Phar;
use RuntimeException;

final class DependencyInstaller
{
    private const COMPOSER_URL = 'https://getcomposer.org/download/latest-stable/composer.phar';
    private const MAX_COMPOSER_BYTES = 20000000;

    public static function requirements(): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return [
            'PHP process execution for automatic dependency setup' => function_exists('proc_open') && !in_array('proc_open', $disabled, true),
            'PHP Phar support' => class_exists(Phar::class),
            'PHP CLI executable' => self::phpBinary() !== null,
            'PHP CLI OpenSSL' => self::cliOpenSslAvailable(),
            'HTTPS downloads' => extension_loaded('openssl') && (extension_loaded('curl') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)),
        ];
    }

    public static function isReady(string $root): bool
    {
        $vendor = rtrim((string) Config::get('storage.path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor';
        return is_file($vendor . DIRECTORY_SEPARATOR . 'autoload.php')
            && is_file($root . DIRECTORY_SEPARATOR . 'composer.json')
            && is_file($root . DIRECTORY_SEPARATOR . 'composer.lock');
    }

    public static function install(string $root): void
    {
        if (self::isReady($root)) return;
        foreach (self::requirements() as $label => $passed) {
            if (!$passed) throw new RuntimeException($label === 'PHP CLI OpenSSL' ? 'Automatic dependency setup cannot use HTTPS because the PHP command-line runtime has OpenSSL disabled. Enable extension=openssl in the php.ini used by PHP CLI, restart the PHP server, and retry. The web PHP and command-line PHP runtimes can load different php.ini files.' : 'Automatic dependency setup is unavailable: ' . $label . '. Ask your hosting provider to enable it or use a host that supports PHP process execution.');
        }
        $manifestPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
        $lockPath = $root . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($manifestPath) || !is_file($lockPath)) throw new RuntimeException('The application dependency manifest or lock file is missing.');
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $lock = json_decode((string) file_get_contents($lockPath), true);
        if (!is_array($manifest) || !is_array($lock) || !isset($manifest['require']) || !isset($lock['packages'])) {
            throw new RuntimeException('The locked dependency manifest is invalid. Upload a complete release package and retry.');
        }
        $storage = (string) Config::get('storage.path');
        if ($storage === '' || !Installer::storageIsPrivate($root)) throw new RuntimeException('Automatic dependency setup requires private storage outside the public web root.');
        if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) throw new RuntimeException('Private storage could not be created for dependencies.');
        if (!is_writable($storage)) throw new RuntimeException('Private storage is not writable by PHP.');
        chmod($storage, 0700);
        $composer = $storage . DIRECTORY_SEPARATOR . 'composer.phar';
        if (!is_file($composer) || !self::validComposerPhar($composer)) self::downloadComposer($composer);
        $vendor = $storage . DIRECTORY_SEPARATOR . 'vendor';
        $home = $storage . DIRECTORY_SEPARATOR . 'composer-home';
        $cache = $storage . DIRECTORY_SEPARATOR . 'composer-cache';
        foreach ([$home, $cache] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Composer private working directory could not be created.');
            chmod($directory, 0700);
        }
        $environment = [
            'COMPOSER_VENDOR_DIR' => $vendor,
            'COMPOSER_HOME' => $home,
            'COMPOSER_CACHE_DIR' => $cache,
            'COMPOSER_ALLOW_SUPERUSER' => '1',
        ];
        $certificateBundle = self::certificateBundle();
        if ($certificateBundle !== null) $environment['COMPOSER_CAFILE'] = $certificateBundle;
        $previous = [];
        foreach ($environment as $key => $value) {
            $previous[$key] = getenv($key);
            putenv($key . '=' . $value);
        }
        try {
            $process = proc_open(
                [self::phpBinary(), $composer, 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress', '--optimize-autoloader', '--no-plugins', '--no-scripts', '--working-dir=' . $root],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root
            );
            if (!is_resource($process)) throw new RuntimeException('Composer could not be started by PHP.');
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1], 50000);
            $stderr = stream_get_contents($pipes[2], 50000);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            if ($status !== 0) {
                $detail = trim((string) $stderr . "\n" . (string) $stdout);
                throw new RuntimeException('Composer could not install the locked dependencies. ' . substr($detail, 0, 1800));
            }
        } finally {
            foreach ($previous as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
        }
        if (!is_file($vendor . DIRECTORY_SEPARATOR . 'autoload.php')) throw new RuntimeException('Composer finished without creating the dependency autoloader.');
        chmod($vendor . DIRECTORY_SEPARATOR . 'autoload.php', 0600);
    }

    private static function cliOpenSslAvailable(): bool
    {
        $binary = self::phpBinary();
        if ($binary === null || !function_exists('proc_open')) return false;
        $process = @proc_open([$binary, '-r', 'echo extension_loaded("openssl") ? "1" : "0";'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return false;
        fclose($pipes[0]);
        $output = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0 && $output === '1';
    }

    private static function certificateBundle(): ?string
    {
        $candidates = [
            (string) ini_get('openssl.cafile'),
            (string) ini_get('curl.cainfo'),
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem',
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'cacert.pem',
            dirname(PHP_BINDIR) . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'curl-ca-bundle.crt',
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) return $candidate;
        }
        return null;
    }

    private static function phpBinary(): ?string
    {
        $suffix = DIRECTORY_SEPARATOR === chr(92) ? '.exe' : '';
        $candidates = [
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php' . $suffix,
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php-cli' . $suffix,
            PHP_BINARY,
        ];
        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) return $candidate;
        }
        return null;
    }

    private static function downloadComposer(string $destination): void
    {
        $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
        $context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1, 'max_redirects' => 3], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        if (extension_loaded('curl')) {
            $handle = curl_init(self::COMPOSER_URL);
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS]);
            $contents = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            if ($contents === false || $status !== 200) throw new RuntimeException('Composer download failed: ' . ($error !== '' ? $error : 'unexpected HTTPS response.'));
        } else {
            $contents = @file_get_contents(self::COMPOSER_URL, false, $context);
        }
        if (!is_string($contents) || $contents === '' || strlen($contents) > self::MAX_COMPOSER_BYTES) throw new RuntimeException('Composer could not be downloaded securely. Check outbound HTTPS access and retry.');
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !self::validComposerPhar($temporary) || !rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('The downloaded Composer executable failed its PHAR signature validation.');
        }
        chmod($destination, 0600);
    }

    private static function validComposerPhar(string $path): bool
    {
        try {
            $phar = new Phar($path);
            $signature = $phar->getSignature();
            return is_array($signature) && in_array($signature['hash_type'] ?? '', ['SHA-256', 'SHA-384', 'SHA-512', 'OpenSSL'], true);
        } catch (\Throwable) {
            return false;
        }
    }
}
