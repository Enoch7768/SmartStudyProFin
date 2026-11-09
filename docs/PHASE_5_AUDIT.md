# Phase 5 — Whole-project audit and release readiness

**Status:** In progress — release candidate not approved  
**Audit branch:** `main`  
**Inventory snapshot:** `d6474a0a22b7cf9d087789ed184ecc8bc6717f5a`  
**Current source inventory at snapshot:** 257 tracked files: 143 PHP files, 69 shipped asset files, 21 additive SQLite migrations, and 20 test files. The retired `cms/` tree (880 tracked files immediately before cleanup) has been removed from the active source tree.

A green CI run is a baseline, not proof that every user journey, provider integration or production hosting configuration has been validated. This document distinguishes automated checks from staging and owner sign-off still required.

## Latest independently verified CI snapshot

- Main commit: `c7cb9f871cbe6bb0f394167f51c12edfe82cd32f`.
- GitHub Actions run [#42608155021](https://github.com/Enoch7768/SmartStudyProFin/actions/runs/42608155021) completed successfully on 2026-11-09.
- Both `foundation (8.2)` and `foundation (8.3)` jobs completed successfully. Their steps include PHP lint, private configuration, contact abuse controls, routing/security, migrations and identity, commerce/refunds, demo payments, meeting planner, instructor permissions, AI privacy, recognition, backups/operations, JavaScript checks, CMS sanitization, SQLite query plans, security configuration, site/asset smoke tests and release-package boundary checks.
- This run validates the default-branch foundation workflow only. It is not a tag-triggered release build, provider staging test, production cPanel drill, or proof that historical refs have been cleaned.

## Audit objectives

1. Review maintained PHP source and shipped browser assets for security, error handling, authorization, validation, accessibility and performance.
2. Verify learner, instructor, administrator and commerce journeys against the actual schema and authorization rules.
3. Audit release packaging, private storage, deployment, backups, migrations and operational diagnostics.
4. Improve reliability, usability, accessibility, performance and maintainability without replacing the established design or adding unnecessary runtime dependencies.
5. Keep retired code, historical databases, uploads, generated artifacts and private business records out of the active application and release package.

## Confirmed findings and remediation

### P0 — Historical public-repository exposure remains

- The current `main` tree has had legacy runtime/private artifacts removed, and the retired `cms/` source tree has now been removed from active source.
- The legacy CMS removal deleted 880 tracked files from `main`. The native PHP CMS under `app/Cms` and its current admin screens remain in place.
- The current branch no longer contains the retired root `auth.php` implementation, which held a hardcoded legacy signing secret.
- A standalone `invoice.html` containing a named customer's personal contact and payment details was found in the root of the repository. It has been removed from `main`; `/invoice.html` is now ignored and a release regression test prevents the artifact from returning. An obsolete failed transcript artifact was also removed.
- **Confirmed historical exposure:** the published `v0.1.0` tag still contains the invoice, legacy `auth.php`, the retired CMS, SQLite database snapshots, booking databases, personal avatar images, uploaded media/archive files and a financial spreadsheet. Its tree was checked directly.
- The current GitHub branch listing enumerates 24 refs in total, including `main` (23 non-`main` refs). The prior audit snapshot recorded 25 refs, so the one-ref difference must be reconciled during cleanup. Historical branches are not safe to use or redistribute until each live head has been rescanned and exposed history has been rewritten. The published `v0.1.0` tag independently remains affected. Cleaning only `main` is therefore insufficient.
- Deleting these files from `main` does not erase old tags, branch heads or earlier commits. No release/tag deletion, branch deletion, Git history rewrite or credential rotation has been performed.
- Treat the historical signing secret as compromised. Before further public distribution, the owner must authorize a coordinated history rewrite and decide how to handle the published `v0.1.0` release/tag. Rotate any still-used credential that may have appeared in historical source. Never reuse the legacy signing secret.

### P1 — Release workflow validation parity — remediated

- The tag-triggered release workflow previously omitted several current regression suites and did not run the PHP 8.2/8.3 matrix.
- The release workflow now runs the full maintained PHP test suite on PHP 8.2 and 8.3 before packaging. It includes configuration secrets, contact abuse controls, commerce/refunds, simulated payments, meeting planner, instructor permissions, AI privacy, recognition, backups, rich-text security, query plans, security and site/asset smoke tests.
- Browser JavaScript syntax and behaviour suites for the main UI, CMS theme and media library are included.
- Release packaging now checks the semantic version/tag match, required instructor workspace and latest migration, and rejects CMS, database, backup, environment-secret, vendor and test paths. A file manifest and SHA-256 checksum are attached to the release.
- Regression tests now guard the release matrix and required current migration history so the tag workflow cannot silently drift behind normal CI.

### P1 — Private credential configuration — remediated

- Private storage `APP_STORAGE_PATH/.env` now supports the application key, Google secret, Gemini key, Groq key, PayPal secret and DPO company token.
- Project environment/process values retain precedence where intended; private credentials are not required in public templates.
- Regression coverage exists for private secret loading. Production owners must still verify permissions and provider configuration on the target host.

### P1 — Homepage structured-data encoding — remediated

- Homepage JSON-LD is encoded with `json_encode` and HTML-sensitive JSON flags instead of interpolating dynamic values into a script block.

### P1 — Contact handling and administrator workflow — remediated

- Contact submissions use the private application SQLite database rather than a separate project-directory database.
- CSRF, server-side validation, honeypot handling and transactional per-IP rate limiting are in place.
- The administrator contact inbox supports new/read/archived states.

### P1 — Upload and session hardening — automated coverage present

- Upload validation uses server-detected MIME and extension agreement, generated storage names and private storage outside the document root.
- ZIP files are accepted only as private authenticated downloads; the application does not extract, preview, index or execute archive contents.
- Failed media metadata writes remove the just-stored file.
- Session cookies use the installed base path and Secure decisions account for the canonical HTTPS application URL.
- PayPal webhook bodies are size-bounded. DPO callbacks validate the provider reference and exact UGX amount; payment confirmation requires a non-empty provider transaction reference.
- This is file isolation and allowlisting, **not antivirus scanning**. The product documentation explicitly states that malware scanning is not provided; users should only open files from trusted sources.

### P1 — Retired CMS source — removed from active tree

- The retired CMS was not loaded by the native application, was blocked by the front controller and Apache rules, and was excluded from deployment packages.
- Its 880 tracked files have now been removed from `main` to reduce attack surface, repository weight and maintenance ambiguity.
- The published `v0.1.0` tag was confirmed to contain 962 legacy CMS files. The historical source remains in Git history and old tags until a separately approved history-retention decision is made.

### P2 — Course offering checkout integration — implemented, regression coverage present

- Course offerings are now distinct purchasable order lines with purchase-time mode/title/price snapshots.
- Self-paced purchases activate course learning access; live-online and in-person purchases create offering-specific enrolments used by session booking and instructor delivery.
- Refunds revoke only the affected offering entitlement and are restricted to eligible online activities. In-person course fees, physical products and shipping are excluded from the online refund ledger.
- Continue validating mixed carts, capacity reservations and refunds against staging data and provider settlement evidence.

### P2 — Refund allocation and entitlement isolation — implemented

- Refunds allocate against a specific eligible order line, reserve pending/succeeded refundable balances and reject physical product lines.
- A fully refunded digital entitlement loses only its corresponding read/download access. Course access is revoked only when the refunded line is fully refunded and no other valid purchase grants access.
- Refund resolution is an audited administrator workflow. Actual provider money movement is performed in the PayPal or DPO merchant console and must be verified before the application records a successful refund.

### P2 — Backup restore — manual procedure documented; restore drill pending

- Backup creation uses SQLite `VACUUM INTO`, private media and key/lock copies, an HMAC-authenticated manifest and SHA-256 file checksums.
- Automated tests now cover backup creation, integrity, tampering, unsafe backup identifiers and an isolated restore of the database, media, application key and installation lock.
- The application documents a manual restore procedure; there is no one-click live restore action. A controlled restore on the actual target cPanel/staging environment is still required before production release.

### P2 — Performance and accessibility — partial automated coverage

- Query-plan tests check selected SQLite indexes; browser tests cover important JavaScript behaviour; responsive and reduced-motion styles are present.
- A source inventory identified six photographic assets above 2 MB (approximately 2.2–4.3 MB each). Their real-world loading impact, responsive variants and compression should be measured on the target site before launch.
- Automated smoke tests do not replace keyboard-only, screen-reader, zoom/reflow and device-specific visual checks.

## Audit coverage ledger

| Area | Current evidence | Remaining work |
|---|---|---|
| Repository inventory and release boundaries | 257-file inventory at `d6474a0`; retired CMS and private invoice removed from `main`; package exclusions tested; latest live listing enumerates 24 branch refs including `main` | Reconcile the one-ref difference from the prior 25-ref snapshot; rescan all 23 current non-main heads; published v0.1.0 remains affected; coordinated history cleanup required |
| Core configuration, sessions, authentication and URL handling | Source review, security tests, private-secret tests, site smoke | Full browser auth and subdirectory-host validation |
| Identity, Google OAuth and account lifecycle | OAuth state/nonce/PKCE implementation; account and migration tests | Real Google OAuth staging sign-in, reset email delivery |
| Native CMS, rich text and media | Sanitization, media-library tests, MIME/path controls | Admin task walkthrough, upload format matrix and manual malicious-file review |
| Commerce, shipping, digital entitlements and refunds | Commerce/refund/demo-payment integration suites | Live/sandbox gateway replay, mixed-cart and shipping-zone staging checks |
| Payment providers and webhooks | Provider verification and callback tests | Merchant credentials, webhook registration, duplicate callback replay and settlement reconciliation |
| Learning, quizzes, progress and certificates | Migration, recognition, meeting and instructor tests | Full learner/instructor browser journeys and course content QA |
| AI tutor and meeting companion | Retrieval/privacy, quotas, kill switch and bounded provider request tests | Provider terms/privacy review and live-key staging test |
| Admin experience and permissions | Security and feature tests for sensitive workflows | Page-by-page administrator task and permission walkthrough |
| Browser assets, accessibility and responsive behaviour | JS suites, CSS inventory, site/asset smoke | Manual keyboard/screen-reader/device tests; optimize large imagery |
| Retired CMS and historical artifacts | 880-file legacy tree and private invoice removed from current `main`; v0.1.0 historical tree confirmed affected; latest branch listing shows 23 non-main refs | Rescan every current non-main head, reconcile branch-count drift, rewrite exposed refs/history, handle v0.1.0 release/tag and rotate any exposed active credential |
| Release, backup, restore and cPanel operations | PHP 8.2/8.3 CI; release matrix parity; package manifest/checksum | Tag-triggered package run, isolated restore drill and target-host deployment drill |

## Release gates

A production release must not be called ready until all of the following are true:

- [ ] Maintained PHP and browser source review is complete; all high/critical findings are fixed or explicitly accepted.
- [x] PHP 8.2 and 8.3 main-branch regression matrix is green.
- [x] Tag workflow runs the same feature/security regression suites on PHP 8.2 and 8.3.
- [x] Release packaging excludes retired CMS, tests, databases, logs, backups, private environment files and third-party runtime trees.
- [x] Release package contains all 21 migrations, instructor workspace and required browser runtime assets.
- [ ] A tag-triggered release workflow has successfully produced and verified the ZIP, manifest and checksum.
- [ ] A staging restore drill has restored database, media, application key and installation lock from a verified backup.
- [ ] Target cPanel/PHP environment passes diagnostics and the full smoke test.
- [ ] Google OAuth, email delivery, PayPal and DPO have been verified with real staging credentials and duplicate callback tests.
- [ ] Owner has reviewed legal documents, privacy disclosures, refund policy, provider terms and data-processing implications.
- [ ] Manual accessibility and responsive QA is complete.
- [ ] Owner authorizes and completes a coordinated rewrite/cleanup of all 24 non-main branch refs, the v0.1.0 tag/release and exposed Git history; any still-used exposed credential is rotated.

## Operational constraints to preserve

- Do not use production records as test fixtures.
- Keep all database migrations additive and tested on blank and upgrade databases.
- Every state-changing route must validate request method, CSRF, authentication, role/permission and record ownership.
- Payment state must only be finalized from verified provider evidence and must be idempotent.
- Refunds must be calculated from eligible order lines, not blindly from the full payment.
- Uploads stay outside the document root, use generated storage names, allow only configured formats and are never executed or interpreted. ZIPs are attachment-only; archive contents are never extracted or executed.
- Keep the existing PHP + SQLite runtime and shared-hosting deployment model. Do not add dependencies or redesign the interface as part of audit-only fixes.
