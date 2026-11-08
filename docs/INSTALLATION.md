# Deployment and first-time provisioning

SmartStudyPro has no browser installer. The public `/install` endpoint and installation wizard have been removed. First-time setup is an operator-controlled deployment task and must never be triggered by an HTTP request.

## Hosting requirements

- PHP 8.2 or newer, 64-bit.
- PDO SQLite and SQLite3.
- cURL, ZIP, GD, mbstring, SimpleXML, OpenSSL and Fileinfo where required by enabled features.
- JSON and secure random support.
- A writable private storage directory outside the public document root.
- A SQLite database path inside that private storage directory.
- Apache rewrite support or equivalent web-server routing.
- HTTPS enabled before public traffic is accepted.

## First deployment

1. Upload the release package and point the document root to the application directory.
2. Configure `APP_STORAGE_PATH` to a writable directory outside the public web root.
3. Configure `DB_PATH` to a SQLite file inside that private storage directory.
4. Configure `APP_URL`, `APP_ENV=production`, and `APP_DEBUG=false`.
5. Install the exact Composer dependencies from `composer.lock` in the deployment build environment. The web application never downloads dependencies.
6. Generate a 64-character hexadecimal `APP_KEY` and store it in the server environment or private storage. Never commit it.
7. Run `php scripts/provision.php "Administrator Name" admin@example.com "a-long-unique-password"` from the application root to create the database schema and first administrator.
8. Configure provider credentials and verify email delivery and payment webhooks before production use.

## Local development

Use the PHP built-in server with `index.php` as the router:

```powershell
php -S 127.0.0.1:8989 -t . index.php
```

Do not use a separate router script. The root and clean URLs are dispatched through the front controller.

## Production operations

Keep private storage outside the document root and preserve its ownership and restrictive permissions. Back up the SQLite database, application key, and private media. Run the diagnostics command after deployment and review application logs before accepting payments.
