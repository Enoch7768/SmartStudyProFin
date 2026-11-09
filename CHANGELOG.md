# Changelog

All notable SmartStudyPro changes are recorded here. The current section is unreleased; a version number and release date will be added only after the release gates are approved.

## Unreleased

### Added
- Course offerings for self-paced, live-online and in-person delivery, with offering-specific prices and enrolments.
- Learner session booking, timezone-aware scheduling, protected meeting links, rosters, attendance, reminders and cohort completion certificates.
- Instructor self-service workspace with assignment-scoped access to offerings, sessions and learner rosters.
- Groq-powered meeting preparation companion with per-user and per-meeting usage budgets.
- Physical product shipping, delivery methods, stock reservations and fulfilment tracking.
- Separate digital read/view and original-file download entitlements.
- Audited line-level refund allocation and entitlement revocation.

### Security and reliability
- Private upload storage with MIME/extension validation, generated filenames and authenticated delivery. ZIP archives are private download attachments only; the application does not extract, preview, index or execute archive contents.
- Hardened PayPal and DPO callback verification, private secret loading, contact-form abuse controls and application session handling.
- Updated release validation to run the full regression suite on PHP 8.2 and 8.3 before packaging.
- Release package checks now verify required migrations, the instructor workspace, excluded private artifacts, package contents and SHA-256 checksum generation.
- Release archive validation distinguishes shipped browser libraries in `assets/vendor/` from server-side dependency trees, with regression coverage for both allowed and forbidden paths.
- Removed the retired legacy CMS and private invoice/transcript artifacts from the active source tree.

### Deployment and operations
- CLI-only first administrator provisioning; there is no browser /install endpoint.
- Private SQLite backups, integrity verification, diagnostics and documented manual restore procedure.
- PHP 8.2+ and SQLite deployment target for conventional shared hosting/cPanel.

### Known limitations and release notes
- This release does not provide antivirus scanning. File allowlisting and private storage reduce server-side execution risk but do not prove uploaded documents are malware-free.
- ZIP files are never extracted or previewed by the application.
- PayPal and DPO live credentials, merchant configuration, webhook registration and end-to-end sandbox transactions require staging verification.
- Refund money movement is performed in the provider merchant console and must be confirmed before a successful refund is recorded in SmartStudyPro.
- Google OAuth, email delivery, Gemini and Groq integrations require owner-supplied credentials and provider/privacy review.
- Backup restore remains a documented manual operation; an isolated staging restore drill is still a release gate.
- The v0.1.0 and v1.0.0 tags both point to the same historical commit containing private artifacts and legacy source; only v0.1.0 currently has a published GitHub Release, and VERSION on main remains 0.1.0. The latest live branch listing enumerates 23 non-main refs (24 total including main); recursive scans confirm all 23 heads retain the private invoice, legacy authentication endpoint, eight database snapshots and financial spreadsheet, with 962–4,247 retired CMS files per branch. Do not use or redistribute affected historical refs. A coordinated Git-history cleanup and any required credential rotation are mandatory before creating the next public release.
