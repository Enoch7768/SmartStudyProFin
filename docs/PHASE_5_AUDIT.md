# Phase 5 — Whole-project audit and release readiness

**Status:** Main-branch hardening in progress — release candidate not approved  
**Audit branch:** `main`  
**Inventory snapshot:** `12cfb20d227e94119f4ab96f5b4c9a2623f7692f`  
**Current source inventory at snapshot:** 257 tracked files: 143 PHP files, 69 shipped asset files, 21 additive SQLite migrations, and 20 test files. The retired `cms/` tree (880 tracked files immediately before cleanup) has been removed from the active source tree.

A green CI run is a baseline, not proof that every user journey, provider integration or production hosting configuration has been validated. This document distinguishes automated checks from staging and owner sign-off still required.

## Latest independently verified CI snapshot

- Latest validated source commit: `12cfb20d227e94119f4ab96f5b4c9a2623f7692f`.
- GitHub Actions [run #1257](https://github.com/Enoch7768/SmartStudyProFin/actions/runs/42626159674) completed successfully on 2026-11-09. Both `foundation (8.2)` and `foundation (8.3)` passed.
- The tested commit includes the shared design-token layer, shared typography defaults, learner/admin dark-theme token alignment, extraction of navigation CSS from `nav.php` into `main.css`, shared stylesheet loading across all 26 discovered navigation consumers, instructor base stylesheet integration and release-history documentation updates.
- The foundation workflow covers PHP lint, the release-package policy test, identity and migrations, commerce/refunds, simulated payments, meetings, instructor permissions, AI privacy, recognition, backup/operations, JavaScript tests, rich-text sanitization, query plans, security configuration and site/asset smoke tests.
- The release archive rule filters shipped `assets/vendor/` browser libraries before checking forbidden server-side dependency directories. Regression coverage checks both allowed browser assets and forbidden server dependency paths.
- These are automated default-branch checks only. They are not a tag-triggered release build, provider staging test, production cPanel drill, manual browser/accessibility review, or proof that historical refs have been cleaned.
## Product quality and unification assessment

This is a directional source-and-CI review, not a user-study, uptime report or third-party benchmark. Scores measure the current repository evidence, not the ambition of the product.

| Dimension | Current score | Evidence and gap |
|---|---:|---|
| Feature breadth | 7.5/10 | Learning, commerce, delivery modes, instructor workflows, AI assistance, recognition and operations are present with dedicated services and tests. |
| Automated engineering baseline | 7/10 | Broad PHP 8.2/8.3 checks and integration suites pass; live provider, cPanel and full browser journeys remain unverified. |
| Security and privacy readiness | 5/10 | Good foundations in CSRF, role checks, private storage, webhook validation and upload allowlisting; historical exposed refs, active-secret rotation, report-only CSP and absent malware scanning remain material gaps. |
| Design-system consistency | 5/10 | Shared tokens now exist, but navigation, page shells, typography, component styling and theme support still vary across individual templates. |
| Accessibility and responsive assurance | 5/10 | Responsive/reduced-motion rules and automated smoke tests exist; manual keyboard, screen-reader, zoom, tablet/foldable and assistive-technology testing remains. |
| Production readiness | 4/10 | The default-branch CI baseline is green, but historical Git cleanup, provider staging, restore and target-host drills, legal sign-off and manual UX validation are open. |

A Google/Spotify-level product bar is not just a polished UI. It requires a consistent interaction model, shared design language, predictable state and navigation, resilient failure/recovery paths, accessibility, measured performance, observability, release discipline and proven operation at scale. SmartStudyPro is not at that bar yet. The current design-token work is a foundation, not a claim of completed unification.

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
- **Additional release-tag exposure:** both `v0.1.0` and `v1.0.0` are present as lightweight Git tags and point to the same historical commit `8148ecb8f6450a4161d5b00f98bc862c88b4bdee` from 2026-11-03. That tagged tree contains 1,257 files, including 962 retired CMS files, root `auth.php`, the financial spreadsheet and other historical private artifacts. The owner has removed the published GitHub Release; the live Releases API currently returns no published releases. Both tags remain as Git refs. `VERSION` on `main` remains `0.1.0`. Neither tag is a valid clean v1.0.0 release target.
- The current GitHub branch listing enumerates 24 refs in total, including `main` (23 non-`main` refs). The prior audit snapshot recorded 25 refs, so the one-ref difference must be reconciled during cleanup. A fresh recursive tree scan of all 23 current non-`main` heads confirms every one contains root `auth.php`, `invoice.html`, eight SQLite/database snapshot files and the financial spreadsheet. Nineteen branch heads contain 4,247 retired `cms/` files each; the other four contain 962 each. None of the non-`main` heads is safe to use or redistribute. Both published-history tags (`v0.1.0` and `v1.0.0`) independently remain affected. Cleaning only `main` is therefore insufficient.
- The repository has an active ruleset named `Cannot Delete` for all non-default branches. It enforces deletion and non-fast-forward restrictions and has no bypass actor; the connected integration reports `current_user_can_bypass: never`. This is an additional operational blocker to removing the 23 exposed non-main refs. The ruleset must be deliberately changed by an authorized repository owner for the cleanup window, then restored with a safe policy. The integration cannot update or delete this ruleset.
- Deleting these files from `main` does not erase old tags, branch heads or earlier commits. The owner has authorized coordinated cleanup and has removed the published GitHub Release, but no tag deletion, branch deletion, Git history rewrite or credential rotation has been performed: the connected GitHub interface provides read operations and branch updates, but no branch/tag/release deletion or root-history rewrite operation. A Git CLI/mirror-capable environment is required to execute and verify this destructive cleanup.
- Treat the historical signing secret as compromised. Before further public distribution, rotate any still-used credential that may have appeared in historical source. Never reuse the legacy signing secret. The coordinated history rewrite is already authorized; execute it from a Git CLI/mirror-capable environment and preserve a private recovery backup.

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
- Both `v0.1.0` and `v1.0.0` point to the same historical tree, which was confirmed to contain 962 legacy CMS files. The historical source remains in Git history and both tags until a coordinated cleanup is completed.

### P2 — Cross-product design consistency — shared foundations and theme synchronization implemented; shell consolidation remains open

- A shared brand, surface, typography, spacing, radius, border, elevation and common-state token layer is defined in `assets/css/main.css`. Shared focus, reduced-motion, responsive spacing, form-control, table, card and empty/loading/error-state primitives are available to every page loading the base stylesheet.
- Navigation styling is centralized in `main.css`; all 26 discovered navigation consumers load the shared stylesheet. The instructor and administrator meeting workspaces now use the shared surface, border, typography and accent tokens rather than independent hard-coded palettes.
- Duplicated public, learner, CMS and commerce theme-toggle code has been replaced with `assets/js/theme.js`. The same controller handles accessible toggle state and icons, local fallback, system preference, and account synchronization. `theme-preference.php` now supports authenticated GET retrieval as well as CSRF-protected POST updates, so a saved preference follows a signed-in user between devices and workspaces. A late server response cannot override a theme choice made during the current page session.
- The learner portal and commerce admin retain their focused page layouts while consuming shared dark surface, text, border and form tokens. Admin operations pages now expose the same compact theme control. CMS and focused lesson-player shells remain specialized by design rather than being forced into the public navigation.
- The app is not yet at Google/Spotify-level design-system consistency. Several page templates still define page-local body, card, button, typography and spacing rules; not every bespoke shell uses the same reusable layout primitives; and empty/loading/error classes are not yet adopted consistently across all journeys.
- Remaining work: migrate the remaining page-local CSS incrementally, standardize reusable navigation and page-shell patterns where role-specific layouts do not add value, adopt the shared state components across learner and administrator workflows, and verify light/dark parity with manual keyboard, screen-reader, zoom, tablet, foldable and mobile checks. Automated source and CI checks do not replace browser-based visual regression review.

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
| Repository inventory and release boundaries | 257-file inventory at `d6474a0`; retired CMS and private invoice removed from `main`; package exclusions tested; 24 live branch refs enumerated; all 23 non-main trees recursively scanned | Reconcile the one-ref difference from the prior 25-ref snapshot; rewrite all 23 exposed non-main heads and both affected tags; remove the v0.1.0 release; verify no sensitive paths remain reachable |
| Core configuration, sessions, authentication and URL handling | Source review, security tests, private-secret tests, site smoke | Full browser auth and subdirectory-host validation |
| Identity, Google OAuth and account lifecycle | OAuth state/nonce/PKCE implementation; account and migration tests | Real Google OAuth staging sign-in, reset email delivery |
| Native CMS, rich text and media | Sanitization, media-library tests, MIME/path controls | Admin task walkthrough, upload format matrix and manual malicious-file review |
| Commerce, shipping, digital entitlements and refunds | Commerce/refund/demo-payment integration suites | Live/sandbox gateway replay, mixed-cart and shipping-zone staging checks |
| Payment providers and webhooks | Provider verification and callback tests | Merchant credentials, webhook registration, duplicate callback replay and settlement reconciliation |
| Learning, quizzes, progress and certificates | Migration, recognition, meeting and instructor tests | Full learner/instructor browser journeys and course content QA |
| AI tutor and meeting companion | Retrieval/privacy, quotas, kill switch and bounded provider request tests | Provider terms/privacy review and live-key staging test |
| Admin experience and permissions | Security and feature tests for sensitive workflows | Page-by-page administrator task and permission walkthrough |
| Browser assets, accessibility and responsive behaviour | JS suites, CSS inventory, site/asset smoke; shared design-token foundation now exists | Consolidate page shells and local CSS, then manual keyboard/screen-reader/device tests; optimize large imagery |
| Retired CMS and historical artifacts | 880-file legacy tree and private invoice removed from current `main`; v0.1.0 release and both tags confirmed affected; both tags point to the same 1,257-file historical tree; all 23 non-main refs recursively scanned and confirmed exposed | Reconcile branch-count drift; rewrite all 23 non-main refs and both tags; confirm the v0.1.0 release remains absent; rotate any exposed active credential; verify no sensitive objects remain reachable |
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
- [ ] Execute the already-authorized coordinated rewrite/cleanup of all 23 confirmed-exposed non-main branch refs, the affected v0.1.0 and v1.0.0 tags and exposed Git history; rotate any still-used exposed credential and verify no sensitive objects remain reachable.

## Operational constraints to preserve

- Do not use production records as test fixtures.
- Keep all database migrations additive and tested on blank and upgrade databases.
- Every state-changing route must validate request method, CSRF, authentication, role/permission and record ownership.
- Payment state must only be finalized from verified provider evidence and must be idempotent.
- Refunds must be calculated from eligible order lines, not blindly from the full payment.
- Uploads stay outside the document root, use generated storage names, allow only configured formats and are never executed or interpreted. ZIPs are attachment-only; archive contents are never extracted or executed.
- Keep the existing PHP + SQLite runtime and shared-hosting deployment model. Do not add dependencies or redesign the interface as part of audit-only fixes.
