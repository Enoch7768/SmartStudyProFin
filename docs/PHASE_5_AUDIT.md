# Phase 5 — Whole-project audit and hardening

**Status:** In progress  
**Audit target:** `main`  
**Baseline commit:** `7628cea8ac3fb4d1d8fe6e2f321876880dd9b53d`  
**Inventory:** 1,602 tracked entries (1,210 file blobs), including 291 PHP files and 962 entries in the retired `cms/` tree.

This is a living audit record. A green CI run is a baseline, not proof that every user journey, provider integration or production hosting configuration has been validated.

## Audit objectives

1. Review every maintained PHP source file and every shipped browser asset, including line-level security, error handling, authorization, validation and accessibility checks.
2. Verify the full learner, instructor, administrator and commerce journeys against the actual database schema and authorization rules.
3. Audit release packaging, private storage, deployment, backup/restore, migrations and operational diagnostics.
4. Improve reliability, usability, accessibility, performance and maintainability without replacing the existing design or adding unnecessary runtime dependencies.
5. Separate active application code from retired CMS material, historical databases, uploaded files, generated artifacts and private business records.

## Confirmed findings

### P0 — Public repository data hygiene

**Phase 1 remediation:** Removed 88 tracked runtime/private artifacts from the current `main` tree: legacy CMS SQLite databases, 38 CMS uploads, generated thumbnails/cache, three legacy booking databases, two avatar images and the root financial-looking spreadsheet. Kept intentional public brand/site assets, migration source files and `.gitkeep` placeholders. Expanded `.gitignore` to prevent CMS runtime data and generated thumbnails/cache from being reintroduced. This does not erase prior Git history; history scrubbing and any required credential rotation remain separate follow-up work.

- The public repository currently tracks legacy SQLite databases under `cms/storage/data/`, `database/` and `db/`, plus historical uploaded media and a ZIP archive under `cms/storage/uploads/`.
- A spreadsheet named `Income Statement and distribution.xlsx` is tracked at the repository root. It is excluded from the release archive, but remains downloadable from the public Git repository and its history.
- Avatar images are tracked under `uploads/avatars/`. The release process excludes these files, but the public Git repository still exposes them.
- Release packaging excludes several of these paths, which protects the deployed ZIP but does not protect the public source repository or old Git history.
- Before production launch, classify each tracked data artifact. Remove private/business records, database snapshots, user uploads and personal images from the public repository; rotate any credentials found; consider Git-history scrubbing because ordinary deletion does not erase prior commits. Preserve only intentional synthetic test fixtures.

### P0 — Historical authentication secret

- Removed the obsolete root `auth.php` endpoint from the current branch and added an explicit web-server 404 rule. It contained a hardcoded legacy signing secret and a parallel authentication/database implementation that was not part of the active account service.
- Removing the file from `main` does not remove it from prior public Git commits. Treat the old signing key as disclosed; rotate it if any active media/token integration ever relied on it, and scrub repository history if the project owner wants the old secret and personal artifacts removed from public history.

### P1 — Private credential configuration

- The private storage `APP_STORAGE_PATH/.env` loader was used for Google and Groq secrets, but Gemini, PayPal and DPO credentials were still read only from the project environment file/process environment.
- This is inconsistent with the documented private-storage secret workflow and makes those provider secrets unavailable when configured only in private storage.
- Updated configuration to load `APP_KEY`, Gemini API key, PayPal secret and DPO company token through the private-secret reader as well. Added a regression test for all six secret values.

### P1 — Homepage structured-data encoding

- Homepage JSON-LD previously interpolated contact and site values directly into a script block.
- Replaced manual interpolation with `json_encode` and HTML-sensitive JSON flags to prevent malformed structured data and script-context injection.

### P1 — Contact handling and administrator workflow

- Replaced the legacy contact handler that wrote to a separate project-directory database with the private application SQLite database.
- Added CSRF protection, server-side validation, a honeypot and a transactional per-IP rate limit. Added an administrator contact inbox with new/read/archived states.
- Retired the broken template contact/newsletter endpoints that referenced a missing third-party PHP Email Form library.

### P1 — Upload and session hardening

- Added server-detected MIME plus filename-extension agreement checks for the configured safe formats, including ZIP. ZIPs remain private downloads; the application does not extract or execute archive contents.
- Failed media metadata writes now remove the just-stored file rather than leaving orphaned private files.
- Session cookies now use the configured application base path, and HTTPS cookie/HSTS decisions also respect the canonical HTTPS `APP_URL` for reverse-proxy deployments.
- PayPal webhook bodies are size-bounded. DPO callbacks now require the provider-returned CompanyRef and an exact UGX amount, and payment confirmation requires a non-empty provider transaction reference.

### P1 — Legacy CMS boundary

- The retired `cms/` tree contains 962 tracked entries and includes legacy dynamic-code mechanisms. It is not part of the deployment package and the web server/front controller block its routes.
- It remains visible in the public Git repository. Keep it out of runtime and releases; decide whether to preserve it in a private archival repository or remove it after validating all content/media migration and history-retention requirements.

### P2 — Course delivery and checkout integration

- Published course offerings are displayed in catalogue/detail pages, and the meeting planner supports live and in-person sessions.
- The current order creation path still prices courses using the legacy course-level `price_ugx`; it does not yet create a distinct order line for each self-paced/live/in-person offering.
- This means separate offering prices and entitlements need an end-to-end checkout and enrolment integration before the delivery-mode pricing model is complete.

### P2 — Refund allocation and entitlement isolation

- Added migration `016_refund_item_allocations` to snapshot refund-to-order-line allocations.
- Refund requests now use `BEGIN IMMEDIATE`, validate course/digital-product eligibility, reserve the selected line's remaining refundable balance, and reject physical product lines.
- Refund resolution updates the order/payment totals, revokes only a fully refunded digital product's download access, and revokes course enrolment only when the course line is fully refunded and no other paid purchase grants access.
- The administrator refund screen lists eligible line items and their remaining balances instead of accepting an unallocated order-level amount.
- PHP commerce regression coverage now checks partial line refunds, cumulative over-refund rejection, independent product/course entitlement revocation and audit history. Shipping and read/download entitlements remain Phase 2 work.

### P2 — Operational and UX opportunities under review

- Validate backup restore as well as backup creation/integrity verification.
- Validate concurrent checkout, duplicate webhook delivery, partial refunds, refund eligibility by item type and entitlement revocation.
- Verify administrator permission checks on every state-changing action, not just page entry.
- Verify private media path containment, allowed MIME handling, ZIP attachment delivery, and that no upload path can execute code.
- Review mobile/tablet layouts, keyboard navigation, focus management, accessible names, empty states, validation feedback and reduced-motion behavior.
- Review slow queries, N+1 content reads, pagination, image payload sizes and cache headers.
- Improve diagnostics so operators receive actionable repair steps without exposing secrets or personal data.

## Audit coverage ledger

| Area | Status |
|---|---|
| Repository inventory and release boundaries | Current-branch data cleanup complete; history review and release recheck pending |
| Core configuration, sessions, authentication and URL handling | Reviewed; private-secret loading hardened; deeper regression pass ongoing |
| Identity, Google OAuth and account lifecycle | Initial source review; full journey tests pending |
| CMS, rich-text sanitization and media | Initial source review; full permission and upload matrix pending |
| Commerce, shipping, digital entitlements and refunds | Initial source review; end-to-end checkout gaps identified |
| Payment providers and webhooks | Initial source review; sandbox replay and idempotency validation pending |
| Learning, quizzes, progress and certificates | Initial source review; full journey audit pending |
| AI tutor and meeting companion | Initial source review; quotas, error handling and privacy tests ongoing |
| Admin experience and permissions | Initial source review; page-by-page task audit pending |
| Browser assets, accessibility and responsive behavior | Inventory complete; visual/browser validation pending |
| Legacy CMS and historical artifacts | Current-branch private data removed; history scrubbing decision pending |
| Release, backup, restore and cPanel operations | Initial source review; restore drill pending |

## Validation rules

- No production data is used as a test fixture.
- Every database migration must be additive, repeatable and tested on a blank database and an upgrade database.
- Every state-changing route must validate method, CSRF, authentication, role/permission and ownership.
- Payment state must only be finalized from verified provider evidence and must be idempotent.
- Refunds must be calculated from eligible order lines, not blindly from the full payment.
- Uploads must remain outside the document root, use generated storage names, allow only the configured file types and never be executed or interpreted by the server. ZIPs are allowed as downloads; archive contents are not executed or extracted by the application.
- CI must pass PHP 8.2 and 8.3, migration tests, security tests, browser behavior tests and release-package checks before a phase is considered complete.

## Current hardening changes

- Removed the retired `auth.php` implementation and explicitly blocked its URL.
- Moved contact submissions to private SQLite storage and added the administrator inbox, CSRF, rate limiting and input validation.
- Added ZIP-aware MIME/extension validation and upload orphan cleanup.
- Scoped session cookies to the installed base path and tightened DPO/PayPal callback validation.
- Added regression tests for contact abuse controls, ZIP extension handling, subdirectory cookies, DPO callback checks and blank provider transaction references.

- Private-storage secret loading regression test added.
- Homepage JSON-LD safely encoded.
- CI and release validation include the private-secret test.

## Completion criteria

Phase 5 is complete only when all maintained source files have been reviewed, all high/critical findings have been resolved or explicitly accepted, all key learner/admin/payment journeys have automated coverage, restore and deployment drills have passed, the release package contains only intended runtime files, and the PHP 8.2/8.3 CI matrix is green.
