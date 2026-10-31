SmartStudyPro — Owner and Deployment Guide

This legacy text file is retained for compatibility. The current project documentation is in README.md and docs/INSTALLATION.md.

Important production guidance:

- Never delete, rename or manually edit the production database to troubleshoot an error.
- Never use a public database-reset script. The old reset endpoint has been removed.
- Take and verify a private backup before updates, migrations or maintenance.
- Keep APP_STORAGE_PATH, DB_PATH, APP_KEY, logs and backups outside the public web root.
- Use HTTPS, APP_ENV=production and APP_DEBUG=false.
- Configure and test PayPal and DPO webhooks in sandbox mode before enabling live payments.
- Keep provider credentials in the private hosting environment or config/local.php. Never commit secrets.
- Do not expose student, customer, order, payment or learning records in screenshots, logs or support requests.

For installation, use docs/INSTALLATION.md. For security, backups, diagnostics and production checks, use docs/PRODUCTION_HARDENING.md. For legal review before launch, use docs/LEGAL_REVIEW_CHECKLIST.md.

For account or site support, use the contact details configured by the site operator.
