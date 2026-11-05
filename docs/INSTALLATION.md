# Installation and dependency setup

SmartStudyPro is distributed without a root `vendor/` directory or a bundled Composer executable. The release includes `composer.json` and `composer.lock` as the authoritative dependency manifest.

## Hosting requirements

- PHP 8.2 or newer, 64-bit.
- PDO SQLite and SQLite3.
- cURL, ZIP, GD, mbstring, SimpleXML, MongoDB, OpenSSL and Fileinfo.
- JSON and secure random support.
- Outbound HTTPS access to GitHub from the PHP web runtime.
- A writable private storage directory outside the public document root.
- A SQLite database path inside that private storage directory.
- Apache rewrite support or equivalent web-server routing.
- HTTPS enabled before opening the installer.

The installer checks the PHP web runtime. It does not need a separate PHP CLI executable, Composer, Phar, SSH, or `proc_open`.

## First installation

1. Upload the clean release package and point the document root to the application directory.
2. Configure `APP_STORAGE_PATH` as a writable directory outside the public web root. The default is a sibling directory named `smartstudypro-storage`.
3. Configure `DB_PATH` as a SQLite file inside `APP_STORAGE_PATH`.
4. Configure `APP_URL`, `APP_ENV=production` and `APP_DEBUG=false`.
5. Open `/install` over HTTPS. On the first visit, the installer automatically downloads the prebuilt PHP dependency archive from a pinned commit of `Enoch7768/sspfvfv2`, extracts it into a private staging directory, and verifies the complete package name/version set against this release's `composer.lock`. It also verifies the generated Composer autoloader against the lock file's content hash.
6. After the dependency archive passes validation, the installer activates it atomically under `APP_STORAGE_PATH/vendor`, then displays the administrator setup form. Submit the form to prepare the SQLite database, run migrations and create the first administrator.
7. If GitHub cannot be reached or validation fails, the installer displays the error and keeps the setup form available when the host requirements are met. Fix the issue and submit the form to retry the dependency setup.
8. Configure provider credentials and verify email delivery and payment webhooks before production use. The installer locks itself after successful setup.

The dependency archive is downloaded only when the private vendor installation is missing or fails its integrity check. Existing verified dependencies are reused on subsequent installer requests. Temporary archives and staging directories are kept in private storage and removed after setup.

## Windows and XAMPP

Enable the required extensions in the `php.ini` used by Apache or the PHP built-in server, not just the CLI configuration. Confirm that the cURL CA certificate configuration is valid so outbound HTTPS can verify GitHub's TLS certificate. Restart the web server after changing PHP extensions.

## Production deployment

The source package must not contain a root `vendor/`, `composer.phar`, Composer cache, private database, application key, or private uploads. Preserve `composer.json` and `composer.lock`; they are required to verify the pinned prebuilt dependency archive. Never expose the private storage directory through a web alias or symlink.

Before accepting payments, configure provider credentials and webhook URLs, test them in sandbox mode, and verify a private backup.
