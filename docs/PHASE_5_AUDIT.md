# Phase 5 — Whole-project audit and release readiness

**Status:** Main-branch hardening in progress — release candidate not approved  
**Audit branch:** `main`  
**Inventory snapshot:** `12cfb20d227e94119f4ab96f5b4c9a2623f7692f`  
**Current source inventory at snapshot:** 257 tracked files: 143 PHP files, 69 shipped asset files, 21 additive SQLite migrations, and 20 test files. The retired `cms/` tree (880 tracked files immediately before cleanup) has been removed from the active source tree.

A green CI run is a baseline, not proof that every user journey, provider integration or production hosting configuration has been validated. This document distinguishes automated checks from staging and owner sign-off still required.

## Latest independently verified CI snapshot

- Latest validated source commit: `005c402e3340c3b5d5175e3ea880bbfd85f2a771`.
- GitHub Actions [run #1309](https://github.com/Enoch7768/SmartStudyProFin/actions/runs/42655677701) completed successfully on 2026-11-09. Both `foundation (8.2)` and `foundation (8.3)` passed.
- The tested commit includes the shared design-token and common-state layer, centralized navigation styling across all 26 discovered navigation consumers, learner/admin dark-surface alignment, the shared account-synced theme controller and GET/POST theme preference endpoint, consistent admin operations theme controls, and tokenized instructor/meeting workspace surfaces.
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
| Design-system consistency | 6/10 | Shared tokens, centralized navigation and reusable workspace primitives are in place. Learner dashboard, notifications and lesson-player styling have been moved out of inline page styles; public/auth/account and administrator templates still contain page-specific styling and require broader component adoption. |
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
- The app is not yet at Google/Spotify-level design-system consistency. Public/auth/account pages and several administrator screens still contain page-local styling; role-specific shells need more consistent shared layout primitives; and empty/loading/error components are not yet adopted consistently across all journeys.
- The 2026-11-09 follow-up moved the learner dashboard and notification page styling into scoped shared rules in assets/css/main.css, removed their inline stylesheet blocks, and migrated the lesson player to reusable workspace header, layout, sidebar, navigation-link, content and panel primitives. The lesson player now loads the shared base stylesheet and consumes the global design tokens for surfaces, borders, typography, spacing and dark mode.
- The Phase 5.1 continuation also migrated login, registration and profile pages off inline styles; consolidated course-detail page styling; and migrated the administrator meeting workspace off its inline stylesheet and inline style attributes. These pages now consume shared brand, surface, form, button, focus, spacing and responsive tokens from main.css.
- Remaining work: migrate account/authentication and remaining public/admin page-local CSS incrementally, adopt shared empty/loading/error components throughout admin and instructor flows, and verify light/dark parity with manual keyboard, screen-reader, zoom, tablet, foldable and mobile checks. Automated source and CI checks do not replace browser-based visual regression review.

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


### 2026-11-09 — Learner and lesson UI unification follow-up

- learning.php no longer contains a page-local stylesheet. Its welcome, metric, course and learning-card surfaces now consume shared design tokens through scoped rules in assets/css/main.css.
- notifications.php no longer contains a page-local stylesheet. Its page width, notification cards, unread state, actions and empty state use the shared theme tokens and state primitive.
- study.php no longer contains a page-local stylesheet. Its lesson player uses reusable workspace primitives for the sticky header, responsive two-column layout, course sidebar, lesson navigation, content area and panels. Its specialized lesson controls remain scoped to the study-player context.
- Static source checks confirm these three pages have no inline style blocks, the lesson player loads main.css, no duplicate class attributes were introduced in study.php, and the updated stylesheet has balanced braces.
- These are source-level checks only. The GitHub Actions run for the latest commit has not yet reported a result in this audit session. No desktop/mobile/tablet/foldable browser walkthrough, keyboard-only session or screen-reader session has been performed.

### 2026-11-09 — CMS and instructor workspace unification follow-up

- The native CMS sign-in and content-manager views no longer embed their large page-local style blocks. Their styles now live in dedicated `assets/css/admin-cms-login.css` and `assets/css/admin-cms.css` assets, preserving their distinct layouts while keeping CSS out of the PHP template.
- Removed all four inline style attributes from the CMS template and replaced them with named utility classes in the CMS stylesheet.
- The instructor workspace no longer embeds a page-local stylesheet or inline style attribute. Its shared-token-based rules now live in `assets/css/instructor.css`; the page body and full-width field use explicit classes.
- Source checks against the latest GitHub contents confirm `admin/cms.php` and `instructor.php` contain zero style blocks and zero inline style attributes, and each page links its dedicated stylesheet after the shared responsive stylesheet.
- These are source-level checks, not PHP lint, browser rendering or functional tests. The latest commit's GitHub combined status currently reports no status checks; CI is not confirmed green. No visual, keyboard, screen-reader or device testing has been performed.

### 2026-11-09 — CI repair and administrator operations unification

- Investigated the failed GitHub Actions jobs from their actual job logs. The first failure was a stale PHP assertion that expected instructor design-token declarations inside `instructor.php` after its CSS had been extracted. The second was a stale Node assertion that expected CMS dark-mode selectors inside `admin/cms.php` after the CMS stylesheet extraction.
- Updated `tests/run.php` and `tests/cms-theme.test.cjs` to validate the extracted stylesheets instead of requiring page-local CSS. The resulting GitHub Actions run passed on PHP 8.2 and PHP 8.3, including migration, commerce, instructor permissions, AI, recognition, operations, JavaScript, security, page smoke and release-package checks.
- Added `assets/css/admin-operations.css` and migrated analytics, backups, diagnostics, updates, contact inbox and shipping/fulfilment to the shared operations theme and semantic design tokens. Removed embedded style blocks and inline style attributes from all six pages.
- Fixed a malformed Bootstrap/main stylesheet link in `admin/messages.php` that had combined two link elements into one invalid HTML attribute.
- Extended the PHP foundation tests to check that these operations pages load the shared operations stylesheet and that the contact inbox references valid Bootstrap and main CSS assets.
- The new operations migration and test changes are committed, but their CI run must complete before this follow-up can be called green. Browser-level visual and accessibility validation remains outstanding.

### 2026-11-09 — Commerce administration design-system follow-up

- Migrated `admin/product-assets.php` away from a page-local hard-coded light/dark palette and onto `assets/css/admin-operations.css`, with the shared theme tokens and responsive foundation. Product asset selection now has a clear selected-state treatment.
- Migrated `admin/orders.php` and `admin/refunds.php` onto the shared operations layout, panel, form and table styling while retaining the account-synced theme controller.
- Extended the shared admin operations stylesheet to cover Bootstrap tables, form surfaces, panels and theme controls consistently in both color schemes.
- Expanded PHP and Node regression coverage for the shared product asset manager and admin operations styles. The new CI run is pending; this change is not yet confirmed green.
- Media preview remains a protected streaming endpoint rather than a visual page, and demo payment settings is a POST-only action endpoint; neither needs a page stylesheet. This pass did not alter their behavior.
- No browser visual or accessibility validation has been performed.
