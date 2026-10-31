# Releases, updates, diagnostics and backups

## Release packages

The release workflow runs the PHP foundation and operations tests for version tags. It creates a versioned ZIP package and SHA-256 checksum, then attaches both to a GitHub Release. Packages exclude environment secrets, private storage, database files, legacy CMS storage, and user avatar uploads.

The VERSION file is the installed semantic version. Tag names use the vMAJOR.MINOR.PATCH format and must match VERSION without the leading v.

## Updating a hosted installation

1. Create and verify a fresh backup from Admin → Backups.
2. Review the release from Admin → Updates and download the ZIP and checksum from the linked GitHub Release.
3. Verify the SHA-256 checksum before extracting the package.
4. Upload the new application files. Preserve .env, config/local.php, the private APP_STORAGE_PATH directory, uploads/avatars and any host-specific configuration.
5. Open Admin → Updates. Confirm that the pending migrations match the uploaded release.
6. Choose “Back up and apply migrations”. The application creates and verifies another backup before applying database migrations.
7. Open Admin → Diagnostics and confirm that all checks pass.

The application does not download or execute release code automatically. Code deployment remains an explicit hosting-file operation.

## Backups

The backup service uses SQLite VACUUM INTO for a consistent database snapshot, copies private media and the application key, and writes a manifest containing file sizes and SHA-256 checksums. The manifest is authenticated with an HMAC derived from the private application key. Backup files are created under APP_STORAGE_PATH/backups with restrictive permissions and are never served by a public download route.

CLI commands:

- php scripts/backup.php create
- php scripts/backup.php list
- php scripts/backup.php verify BACKUP_ID
- php scripts/doctor.php
- php scripts/doctor.php --json

Backups contain the database, media, application key and installation lock. Keep the matching .env or hosting environment configuration separately in a secure password manager or encrypted off-site location. A restore requires the matching application key and configuration.

## Restore procedure

1. Put the site into maintenance mode at the hosting layer.
2. Preserve a copy of the current private storage directory before changing anything.
3. Verify the selected backup using the CLI utility or Admin → Backups.
4. Restore database.sqlite to the configured DB_PATH, restore the media directory, app.key and installed.lock from that backup, and preserve the correct ownership and private permissions.
5. Restore the matching environment configuration.
6. Run scripts/doctor.php and review the application logs before reopening the site.

Do not restore files from an unverified backup or replace the application key independently of the database and media.

## Diagnostics

Admin → Diagnostics and scripts/doctor.php check PHP requirements, private storage boundaries, key validity, database connectivity and integrity, migration state, backup-directory writability and available disk space. The output is sanitized and does not print secrets or absolute storage paths.
