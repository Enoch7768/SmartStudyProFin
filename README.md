# SmartStudyPro

**Study Made Simple, Success Made Sure.**

SmartStudyPro is a PHP 8.1+ learning and education-commerce platform for students and administrators. It brings course discovery, enrolment, protected learning materials, lesson progress, quizzes and timed assessments, certificates, achievements, notifications, a grounded AI learning assistant, digital products and payment processing into one responsive website.

The application is designed for conventional PHP hosting with SQLite. It uses server-rendered PHP pages and does not require a Node.js runtime or a separate database server in production.

## Core capabilities

- Public course and product catalogue with a native PHP content manager
- Student accounts, role-based administration, profiles and password recovery
- Course enrolment, protected media, lesson progress, bookmarks, quizzes and timed assessments
- Verifiable certificates, achievements and student notifications
- Course-grounded AI learning assistance, with Gemini configured server-side
- UGX commerce, immutable orders, PayPal and DPO payment integrations
- Release packaging, safe database migrations, diagnostics and private backups
- Security controls, accessible navigation and responsive light/dark presentation

## Requirements

- PHP 8.1 or later
- PDO SQLite and SQLite3 extensions
- OpenSSL and Fileinfo
- HTTPS in production
- Apache with mod_rewrite and .htaccess support, or equivalent web-server rules
- A writable private storage directory outside the public web root

Optional integrations require their own credentials: PayPal, DPO and Google Gemini.

## Installation

1. Deploy the release package to your PHP hosting account.
2. Configure the document root to the application directory.
3. Set APP_STORAGE_PATH to a directory outside the public web root and DB_PATH to a database file inside that private directory.
4. Configure APP_URL, APP_ENV=production, APP_DEBUG=false and a strong private APP_KEY.
5. Add provider credentials to the private environment or config/local.php; never commit secrets.
6. Open /install/ over HTTPS and create the first administrator account.
7. Disable setup access after installation.
8. Configure and test payment-provider webhooks and email delivery before accepting live transactions.

See Readme.txt for the existing site-owner guide and docs/DEPLOYMENT.md for operational deployment details.

## Configuration

The application reads environment values from the hosting environment or a private .env file. See .env.example. Keep .env, config/local.php, database files, encryption keys, logs and backups outside public access.

Never use production customer data in development or CI. Automated tests create isolated temporary SQLite databases.

## Development and validation

Run PHP lint, unit, SQLite integration, assistant, recognition, operations, content-security, performance and security tests, plus JavaScript syntax and behaviour tests. GitHub Actions validates PHP 8.1 and 8.3. Provider credentials and real-money payment flows must be tested separately in provider sandbox environments before production activation.

## Legal and intellectual property

This repository is proprietary software. Public visibility is not permission to copy, modify, redistribute, sublicense, host or resell the source code. The developer retains ownership of original project code and custom implementation, subject to third-party licences and client-supplied materials. The client receives only the limited usage rights set out in the signed project agreement and EULA.md.

Website users should review TERMS_OF_USE.md, PRIVACY_POLICY.md, COOKIE_NOTICE.md and ACCESSIBILITY.md. These documents are also available through the website's legal pages.

The legal documents are implementation drafts and must be reviewed against the signed client agreement, actual data-controller identity, hosting/provider arrangements and applicable law before production launch.

## Security

Please do not report vulnerabilities in public issues. Follow SECURITY.md for responsible disclosure.

## Contributing

External contributions are not automatically licensed for inclusion. Read CONTRIBUTING.md and obtain written approval before submitting code or assets.

## Project status

The project is maintained as a client website and proprietary application. Feature and release status is recorded in the repository's Git history and release notes.
