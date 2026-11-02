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
- UGX commerce, immutable receipts, PayPal and DPO payment integrations, audited refunds and secure multi-file digital products
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

See docs/INSTALLATION.md for installation and deployment details; Readme.txt is retained only as a pointer to current safe operating guidance.

## Configuration

The application reads environment values from the hosting environment or a private .env file. See .env.example. Keep .env, config/local.php, database files, encryption keys, logs and backups outside public access.

Never use production customer data in development or CI. Automated tests create isolated temporary SQLite databases.

## Development and validation

Run PHP lint, unit, SQLite integration, assistant, recognition, operations, content-security, performance and security tests, plus JavaScript syntax and behaviour tests. GitHub Actions validates PHP 8.1 and 8.3. Provider credentials and real-money payment flows must be tested separately in provider sandbox environments before production activation.

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
