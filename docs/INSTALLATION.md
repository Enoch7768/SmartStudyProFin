# Installation and dependency setup

SmartStudyPro is distributed without a root `vendor/` directory or a bundled Composer executable. The release includes `composer.json` and `composer.lock` only as the authoritative dependency manifest and lock.

## Hosting requirements

- PHP 8.1 or newer with PDO SQLite, SQLite3, OpenSSL, JSON and Fileinfo.
- Phar support, outbound HTTPS access and PHP `proc_open` enabled for first-time dependency installation.
- Apache rewrite support or equivalent web-server routing.
- A writable private storage directory outside the public document root.
- HTTPS enabled before opening the installer.

The installer displays each server requirement before allowing setup. If the host disables `proc_open`, blocks outbound HTTPS, or lacks Phar support, automatic dependency installation cannot run on that host. Enable those PHP capabilities or use a PHP host that supports them; do not upload a public `vendor/` directory as a workaround.

## First installation

1. Upload the clean release package and point the document root to the application directory.
2. Open `/install/` over HTTPS.
3. The installer downloads the current stable Composer PHAR over verified HTTPS into private storage and validates its PHAR signature.
4. Composer installs the exact versions in `composer.lock`, with development packages, plugins and scripts disabled. The resulting `vendor/` directory, Composer home and cache are placed under `APP_STORAGE_PATH`, not in the public application directory.
5. The installer prepares the private SQLite database, applies migrations, creates the administrator account, and writes the installation lock.
6. The application loads the private Composer autoloader on subsequent requests. The installer refuses to run again after successful setup.

Configure `APP_STORAGE_PATH` outside the web root and `DB_PATH` inside it. If not provided by the hosting environment, the default storage path is a sibling directory named `smartstudypro-storage`.

## Production deployment

The source package must not contain a root `vendor/`, `composer.phar`, Composer cache, private database, application key, or private uploads. Preserve `composer.json` and `composer.lock`; they are required by the browser installer. Never expose the private storage directory through a web alias or symlink.

Before accepting payments, configure provider credentials and webhook URLs, test them in sandbox mode, and verify a private backup.
