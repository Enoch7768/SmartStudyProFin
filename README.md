# SmartStudyPro

**Study Made Simple, Success Made Sure.**

SmartStudyPro is a PHP 8.2+ learning and education-commerce platform for students and administrators. It brings course discovery, enrolment, protected learning materials, lesson progress, quizzes and timed assessments, certificates, achievements, notifications, a grounded AI learning assistant, digital products and payment processing into one responsive website.

The application is designed for conventional PHP hosting with SQLite. It uses server-rendered PHP pages and does not require a Node.js runtime or a separate database server in production.

## Core capabilities

- Public course and product catalogue with a native PHP content manager
- Student accounts, role-based administration, profiles and password recovery
- Course enrolment, protected media, lesson progress, bookmarks, quizzes and timed assessments
- Verifiable certificates, achievements and student notifications
- Course-grounded AI learning assistance, with Gemini configured server-side
- UGX commerce, immutable receipts, PayPal and DPO payment integrations, audited refunds and secure multi-file digital products
- Release packaging, safe database migrations, diagnostics and private backups
- Security controls, accessible navigation and responsive light/dark presentation

## Requirements

- PHP 8.2 or later, 64-bit
- PDO SQLite and SQLite3 extensions
- cURL, ZIP, GD, mbstring, SimpleXML, OpenSSL and Fileinfo
- HTTPS in production
- Apache with mod_rewrite and .htaccess support, or equivalent web-server rules
- A writable private storage directory outside the public web root

Optional integrations require their own credentials: PayPal, DPO and Google Gemini.

## Deployment and provisioning

There is no browser installer and no `/install` endpoint. First-time setup is performed by the operator from the command line after configuring private storage, the database path, the application key, and runtime dependencies.

1. Deploy the release package and point the document root at the application directory.
2. Set `APP_STORAGE_PATH` to a writable directory outside the public web root.
3. Set `DB_PATH` to a SQLite file inside that private storage directory.
4. Configure `APP_URL`, `APP_ENV=production`, and `APP_DEBUG=false`.
5. No Composer or Node.js installation is required for the production PHP application. Its application and learning code use native PHP classes and SQLite.
6. Generate a 64-character hexadecimal `APP_KEY` and keep it in the server environment or private storage.
7. Set `SSP_ADMIN_NAME`, `SSP_ADMIN_EMAIL` and `SSP_ADMIN_PASSWORD` in the command environment, then run `php scripts/provision.php` from the application root. Do not pass passwords as command-line arguments; process listings can expose them.
8. Configure provider credentials and verify email delivery and payment webhooks before production use.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for deployment details. Never commit secrets, database files, logs, backups, or private media.

## Configuration

The application reads environment values from the hosting environment or a private .env file. See .env.example. Keep .env, config/local.php, database files, encryption keys, logs and backups outside public access.

Never use production customer data in development or CI. Automated tests create isolated temporary SQLite databases.

## Development and validation

The application uses `index.php` as its single front controller. No separate `router.php` file or server-specific router is required. For local development, start the built-in PHP server from the repository root:

```sh
php -S 127.0.0.1:8000 index.php
```

The same front controller handles clean URLs such as `/profile`, dynamic course and product slugs, and learning routes. `/install` is intentionally not a route and returns 404. Existing public PHP endpoints and static assets are passed through, while unknown paths return a real 404. Apache deployments use the included `.htaccess` to send clean URLs to `index.php`; conventional PHP hosting can point its document root at the project directory.

For local Google OAuth, set `APP_URL=http://localhost:8000` and add this exact authorized redirect URI to the Google OAuth web client:

```
http://localhost:8000/google-auth.php?action=callback
```

Google accepts HTTP for localhost development only. Keep the client ID in the project environment and the client secret in the server environment or `$APP_STORAGE_PATH/.env`. Restart the PHP server after changing environment configuration.

Run PHP lint, unit, SQLite integration, assistant, recognition, operations, content-security, performance and security tests, plus JavaScript syntax and behaviour tests. GitHub Actions validates the PHP 8.2 and 8.3 runtimes, provisioning boundaries, and the application test suite. Provider credentials and real-money payment flows must be tested separately in provider sandbox environments before production activation.

## Legal and intellectual property

This repository is proprietary software. Public visibility is not permission to copy, modify, redistribute, sublicense, host or resell the source code. The developer retains ownership of original project code and custom implementation, subject to third-party licences and client-supplied materials. The client receives only the limited usage rights set out in the signed project agreement and EULA.md.

Website users should review TERMS_OF_USE.md, PRIVACY_POLICY.md, COOKIE_NOTICE.md, REFUNDS_POLICY.md and ACCESSIBILITY.md. These documents are also available through the website's legal pages.

Existing accounts are prompted to accept new Terms and Privacy versions at sign-in. The legal documents are implementation drafts and must be reviewed against the signed client agreement, actual data-controller identity, hosting/provider arrangements and applicable law before production launch. Complete the [legal review checklist](docs/LEGAL_REVIEW_CHECKLIST.md) before launch.

## Security

Please do not report vulnerabilities in public issues. Follow SECURITY.md for responsible disclosure.

## Contributing

External contributions are not automatically licensed for inclusion. Read CONTRIBUTING.md and obtain written approval before submitting code or assets.

## Project status

The project is maintained as a client website and proprietary application. Feature and release status is recorded in the repository's Git history and release notes.

## Google sign-in

Google sign-in uses the server-side OAuth 2.0 authorization-code flow with PKCE. Configure `APP_URL` and `GOOGLE_CLIENT_ID` in the server environment. Store `GOOGLE_CLIENT_SECRET` only in the server environment or in `$APP_STORAGE_PATH/.env`, outside the public web root. Set that private file's permissions to `0600`. Do not put the client secret in the repository or the public application directory:

```dotenv
APP_URL=https://your-domain.example
GOOGLE_CLIENT_ID=your-google-oauth-client-id
```

Private storage environment file:

```dotenv
GOOGLE_CLIENT_SECRET=your-google-oauth-client-secret
```

In Google Cloud Console, create a Web application OAuth client and add this exact authorized redirect URI:

```
https://your-domain.example/google-auth.php?action=callback
```

Enable the OpenID Connect scopes `openid`, `email`, and `profile` through the consent configuration. Keep the client secret private and never commit production credentials. The Google buttons appear only when both credentials and the canonical `APP_URL` are configured.

Existing accounts are not automatically linked by matching email addresses. A user must first sign in with their existing SmartStudyPro credentials and connect Google from their account profile. New Google registrations require acceptance of the Terms of Use and Privacy Policy and are assigned the student role. Administrators keep their existing role when they sign in with a previously linked Google identity.
