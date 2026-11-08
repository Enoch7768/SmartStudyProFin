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

`APP_URL` is the canonical base URL used by the application for absolute links, redirects, OAuth callbacks, and the installation base path. Set it from the server environment or the project `.env` file, without a trailing slash. For a subdirectory installation, include that path, for example `https://example.com/smartstudypro`. Do not hardcode the domain or installation path in PHP templates.
5. No Composer installation or third-party PHP vendor directory is required. The deployed application uses native PHP code and SQLite.
6. Generate a 64-character hexadecimal `APP_KEY` and store it in the server environment or private storage. Never commit it.
7. Set the three provisioning environment variables and run `php scripts/provision.php` from the application root. Never put the administrator password in command-line arguments.
8. Configure provider credentials and verify email delivery and payment webhooks before production use. For the optional Groq meeting companion, set `GROQ_API_KEY` only in the server environment or private storage `.env` file (never the project `.env.example` or a public file). Configure `GROQ_MODEL`, input/output budgets, per-user daily limits and `GROQ_MEETINGS_ENABLED` as needed.
9. In cPanel Cron Jobs, run `php /absolute/path/to/SmartStudyProFin/scripts/meeting-reminders.php` every 15 minutes. This creates in-app reminders for confirmed session bookings about 24 hours and one hour before the meeting. The reminder ledger makes repeated runs safe. Verify the cron PHP binary and application file permissions on the host.

### Provision the first administrator (PowerShell)

Run this from the application root after configuring the application environment:

```powershell
$env:SSP_ADMIN_NAME = "Administrator"
$env:SSP_ADMIN_EMAIL = "admin@example.com"
$secure = Read-Host "Create a strong administrator password" -AsSecureString
$env:SSP_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new("", $secure).Password
php scripts/provision.php
Remove-Item Env:SSP_ADMIN_NAME, Env:SSP_ADMIN_EMAIL, Env:SSP_ADMIN_PASSWORD
Remove-Variable secure
```

The password is read from the process environment, not command-line arguments. Keep the shell session private and clear the variables immediately after provisioning.

## Local development

Use the PHP built-in server with `index.php` as the router:

```powershell
php -S 127.0.0.1:8989 -t . index.php
```

Do not use a separate router script. The root and clean URLs are dispatched through the front controller.

## Production operations

Keep private storage outside the document root and preserve its ownership and restrictive permissions. Back up the SQLite database, application key, and private media. Run the diagnostics command after deployment and review application logs before accepting payments.
