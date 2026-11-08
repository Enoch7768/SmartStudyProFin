# Commerce requirements — shipping, digital access, strict upload allowlist and refunds

This document defines required work, not claims of completed functionality. Preserve existing commerce behaviour until each feature has migrations, tests and UI.

## Physical products and shipping

- Capture and validate shipping address at checkout.
- Support configurable shipping zones/methods and delivery prices: flat fee, zone-based fee and optional product/weight-based calculation.
- Show shipping separately from item subtotal; calculate it on the server. Never trust a browser-submitted shipping amount.
- Snapshot shipping address and selected method onto the order.
- Mixed carts charge shipping only for physical lines. Digital products never incur shipping.
- Add fulfilment states, carrier/tracking reference, audit history, inventory and transactional stock reservations to prevent overselling.

## Digital product access and download pricing

For online books, videos, audio, documents and images, administrators configure two independent UGX prices:

1. **Read/view/stream price:** grants access inside SmartStudyPro through a browser reader/viewer. It does not grant original-file download.
2. **Download price:** grants a downloadable copy of the original approved file. It does not automatically grant browser access unless both entitlements are purchased or an explicit policy grants both.

Customers may buy either or both. Catalogue, cart, order lines, receipts and account library identify entitlements separately. Each entitlement has its own server-enforced permission and immutable purchase-time price snapshot. Samples/previews are separate admin-controlled settings.

## Upload policy — strict allowlist, no antivirus scanning

SmartStudyPro will **not run antivirus or malware scans**. Upload security is based on a narrow deny-by-default allowlist, full format validation, safe transformation and private non-executable storage. This reduces attack surface but cannot guarantee every accepted file is malware-free.

- Store every upload outside the public web root in private storage not mapped to PHP, CGI, script handlers or other executable handlers. Use restrictive permissions, random server-generated storage names, path containment checks and SHA-256 hashes.
- Accept only explicitly enabled formats. Initial allowed families:
  - PDF only after structural parsing and sanitization that rejects/removes JavaScript, launch actions, embedded files, rich media, XFA and unsafe external actions.
  - UTF-8 plain text and CSV, treated strictly as text and never rendered as HTML.
  - JPEG, PNG and WebP images, decoded and re-encoded by a maintained image library; discard metadata and trailing/polyglot payloads.
  - MP3 audio and MP4 video with approved codecs only, validated by a media parser and preferably transcoded to a canonical output profile.
  - DOCX, XLSX and PPTX only if a dedicated OOXML package validator confirms macro-free formats and rejects embedded OLE objects, ActiveX, macros, external relationships, embedded packages and unexpected archive entries. If unavailable, reject these formats.
- Reject PHP and all server scripts, executables, shell/batch files, JavaScript, HTML, SVG, XML, macro-enabled Office formats, RTF, archives (ZIP/RAR/7z), disk images, installers, fonts, shortcut files and unknown formats.
- Reject double extensions, trailing dots/spaces, path separators, control characters, Unicode lookalike extensions, alternate data stream syntax, null bytes and ambiguous normalized filenames. Never use the supplied filename as a storage path.
- Validate normalized extension, detected MIME type, file signature/magic bytes and full format structure. All must agree. Client-provided Content-Type is untrusted. Reject MIME/signature mismatches, polyglots, malformed/truncated files, unsupported codecs, unexpected container entries and trailing data where detectable.
- Do not rely on extension or fileinfo/magic bytes alone. Use maintained format-specific parsers, enforce parser time/memory limits and keep libraries updated. If a safe parser/transformer is unavailable, disable that format.
- Enforce individual/aggregate size, file count, parser timeout, image pixel, media duration/resolution and container entry-count limits.
- Validate and transform before a file becomes attachable, previewable, publishable or downloadable. Any error, timeout, unsupported feature or ambiguous result means reject. There is no scan-pending or scan-clean state.
- Serve private files only through authenticated entitlement-checking controllers. Set X-Content-Type-Options: nosniff, server-selected content type and safe Content-Disposition. Render previews in a sandboxed viewer, preferably on a separate cookieless content origin. Never execute uploads.
- Maintain security tests for disguised executables, double extensions, MIME spoofing, signature mismatch, polyglots, malformed/active PDFs, macro-enabled Office files, embedded OLE/ActiveX/external relationships, SVG/HTML/JS, archive bombs, path traversal and oversized media. All prohibited cases must be rejected.

## Administrator reimbursements — online activities only

Refund workflow is limited to self-paced course purchases, live online course/session fees and digital-product access/download entitlements. Physical products, shipping fees and in-person course fees are excluded.

- Calculate refundable balances from eligible order lines and pending/succeeded refunds; never trust browser amounts.
- Mixed carts require line-level allocation. Refunding a digital entitlement must not refund physical goods or shipping.
- Revoke only the refunded entitlement. Revoke course access only if no other valid purchase/enrolment source grants it.
- Require reason, authorized administrator, provider-confirmed reference and append-only audit history.
- Never mark success before PayPal/DPO confirms money movement. If provider refund APIs are unavailable, process in merchant console and record the verified reference.
- Block physical goods, shipping and in-person course fees. Use transactions/idempotency so concurrent requests cannot exceed captured payment or eligible line balances.

## Existing behaviour and implementation status

Current code supports online/physical product types, private digital assets, MIME allowlisting, paid-order secure delivery, browser preview for PDF/text/CSV/images, and an audited refund ledger. It does not yet provide physical shipping/fulfilment, separate read/download prices, distinct entitlements or online-only line-item reimbursements. The strict upload policy above is a target requirement, not a claim that current uploads already meet it.

## Acceptance criteria

- Physical-only checkout calculates delivery price; digital-only checkout has no shipping; mixed checkout charges only physical lines.
- Digital access and download can be purchased and revoked independently.
- Disallowed, ambiguous, disguised, malformed, unsupported or parser-failing uploads are rejected before publication or delivery.
- Uploads remain outside the web root, non-executable and served only through authorized controllers.
- No antivirus dependency, scanner service, scan-pending state or scan-clean state is introduced.
- Admin refunds cannot include physical goods, shipping or in-person course fees.
- Partial refunds revoke only the affected entitlement; payment/order reconciliation remains correct.
- Fresh/upgrade migrations and PHP 8.2/8.3 tests cover all cases.
