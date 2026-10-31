# SmartStudyPro installation

## Server requirements

- PHP 8.1 or newer
- PDO SQLite
- OpenSSL
- JSON
- Fileinfo
- PHP mail transport or an external mail service for password recovery
- Apache with rewrite support for the current compatibility entry point

## Prepare the application

1. Upload the repository files to the intended application directory.
2. Copy .env.example to .env.
3. Set APP_URL to the final HTTPS address.
4. Set APP_STORAGE_PATH to a writable directory outside the public web root.
5. Set DB_PATH to a SQLite file inside that private storage directory.
6. Confirm that the hosting account can create directories and files in the private storage location.
7. Open /install/ over HTTPS and create the first administrator account.

The installer creates the SQLite database, applies versioned migrations, generates a private application key, creates the administrator account, and writes an installation lock. It does not create a default account or sample customer data.

## Account email

Set `APP_MAIL_FROM` to a valid sender address and configure the hosting provider's PHP mail transport or SMTP relay. Password recovery always returns the same public response whether or not an account exists. Reset links are single-use and expire after 30 minutes. If mail delivery is unavailable, password recovery emails will not be delivered; configure and test mail delivery before launch.

## Private storage

The database, application key, logs, backups, sessions, and private uploads belong outside the public web root. Do not place environment files, database files, or application secrets in a publicly downloadable directory. The installer refuses a storage directory inside the detected document root.

## Diagnostics

Run php scripts/doctor.php when command-line PHP and PDO SQLite are available. The browser installer performs its own initial requirement checks and does not require Composer or shell access.

## Database recovery

Back up the complete private storage directory before upgrades or manual database work. Take consistent SQLite backups while writes are controlled. Never delete the database to resolve a schema error; apply a new migration instead.
