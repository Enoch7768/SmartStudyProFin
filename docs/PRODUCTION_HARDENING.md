# Production hardening and validation

## Security controls

- The application bootstrap applies content-type sniffing, clickjacking, referrer, permissions and cross-origin opener protections to application responses. HSTS is sent only when PHP sees a direct HTTPS request.
- Session cookies are HTTP-only, SameSite=Lax and Secure on HTTPS. Login rotates the session ID and CSRF token. Stale sessions are destroyed when the account is no longer active or its authentication version changes.
- Login uses a dummy password hash for unknown email addresses to reduce account-enumeration timing differences.
- CMS rich text is sanitized on write and again before public rendering. The allowlist removes scripts, embedded objects, inline event handlers, unsafe URL schemes and unsafe attributes while preserving supported semantic formatting. Hosts without the PHP DOM extension degrade to escaped plain text.
- PayPal approval links are restricted to the official live or sandbox host. DPO API and checkout URLs are restricted to approved HTTPS hosts. Provider redirects are not followed, XML parsing disables network access, responses are size-limited and PayPal order creation uses an idempotency request ID.
- Private lesson streams reject malformed and multi-range requests and support GET/HEAD only. Public media and purchased downloads also reject other HTTP methods.
- Apache rules deny access to application internals, private storage, the legacy CMS, docs, hidden files, database files, dumps and archives. Avatar uploads cannot execute PHP-family files.
- The exposed legacy Cockpit diagnostic endpoint and unused Cockpit bootstrap were removed.

## Performance

Migration 009 adds indexes for the most common published catalogue, course outline, quiz, learner progress and media queries. The performance test checks that SQLite selects those indexes using EXPLAIN QUERY PLAN. Existing pagination and result limits remain in place, and the browser bootstrap no longer initializes optional libraries on pages that do not load them.

## Accessibility

- The mobile navigation is a real button with an accessible name, controlled-menu reference and expanded state.
- Escape closes the mobile navigation and returns focus to its toggle.
- Main navigation pages have a skip link target.
- Form labels are associated with their inputs and login/registration fields expose browser autocomplete hints.
- Focus-visible outlines and reduced-motion styles are supplied by the previously missing main stylesheet.
- Motion-heavy optional frontend components are not initialized when the user requests reduced motion.

## Staging and production checklist

1. Use HTTPS throughout the site and configure the hosting server to expose the correct HTTPS state to PHP.
2. Keep APP_STORAGE_PATH outside the public document root and DB_PATH inside that private directory.
3. Preserve the application key, database, media and environment configuration together. Do not rotate the application key independently.
4. Configure live PayPal and DPO credentials, provider webhook identifiers and callback URLs. Test a real low-value payment and a duplicate callback in a staging environment before accepting production payments.
5. Configure Gemini only if the AI tutor is enabled, and confirm that the key is private and server-side.
6. Run php scripts/doctor.php --json and the full test suite on the target PHP version.
7. Create a backup and verify it before release deployment or database migrations.
8. Validate sign-in, password reset, course enrolment, lesson streaming, quizzes, checkout, payment callbacks, digital downloads, certificates, notifications and administrator permissions using real staging accounts.
9. Test keyboard-only navigation, zoom/reflow, reduced motion, screen-reader labels and mobile layouts on the actual hosting environment.
10. Review server/PHP logs and disk space after deployment.

Automated CI validates PHP 8.1 and 8.3, SQLite migrations and query plans, security-related behavior, backup integrity, rich-text sanitization and frontend JavaScript initialization. It cannot replace live provider transactions, hosting-specific HTTPS checks or manual browser/screen-reader testing.
