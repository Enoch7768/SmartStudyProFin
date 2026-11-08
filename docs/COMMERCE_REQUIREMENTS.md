# Commerce requirements — shipping, digital access, safe file handling and refunds

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

## Uploads — allow useful formats, never execute them

SmartStudyPro does **not** run antivirus or malware scans. There is no scanner service, scan queue, quarantine workflow or scan status.

- Allow common content formats needed for digital products: PDF; DOC/DOCX; XLS/XLSX; PPT/PPTX; ODT; RTF; TXT; CSV; JPEG; PNG; WebP; GIF; MP3; MP4; and ZIP archives as opaque downloads only. Keep the allowlist explicit and configurable in code; unknown formats are rejected.
- Never allow server-side code or executable formats, including PHP, PHAR, CGI, EXE, DLL, scripts, shell/batch files, JavaScript, HTML, SVG, installers, disk images, shortcut files or unsupported archives uploaded as deliverable content. ZIP is the sole archive exception and is never extracted, inspected, previewed or executed by the application.
- Determine the stored extension and MIME type on the server, not from the browser's Content-Type. Reject unsupported detected types and obvious extension/type mismatches. The client filename is display metadata only and must never determine a storage path.
- Generate random storage names. Store uploads outside the public web root in a private directory with restrictive directory/file permissions. Refuse to upload if the configured storage directory resolves inside the web root.
- Uploaded files must never be included, required, evaluated, interpreted, passed to a shell, used as templates, or routed through PHP/CGI or any other server execution handler.
- Deliver files only through application endpoints that check administrator access or the buyer's entitlement. Use a server-selected Content-Type, X-Content-Type-Options: nosniff, safe Content-Disposition and private caching headers. Force documents, ZIP archives and unknown inline-capable types to download; only deliberately supported preview types may render inline.
- Keep the upload size limit and enforce it server-side. Use generated storage paths and verify path containment when reading files.
- This is file isolation, not malware detection. A permitted document can still contain content that is unsafe when opened in a separate desktop application. Users should only open files from sources they trust.

## Administrator reimbursements — online activities only

Refund workflow is limited to self-paced course purchases, live online course/session fees and digital-product access/download entitlements. Physical products, shipping fees and in-person course fees are excluded.

- Calculate refundable balances from eligible order lines and pending/succeeded refunds; never trust browser amounts.
- Mixed carts require line-level allocation. Refunding a digital entitlement must not refund physical goods or shipping.
- Revoke only the refunded entitlement. Revoke course access only if no other valid purchase/enrolment source grants it.
- Require reason, authorized administrator, provider-confirmed reference and append-only audit history.
- Never mark success before PayPal/DPO confirms money movement. If provider refund APIs are unavailable, process in merchant console and record the verified reference.
- Block physical goods, shipping and in-person course fees. Use transactions/idempotency so concurrent requests cannot exceed captured payment or eligible line balances.

## Existing behaviour and implementation status

Current code supports online/physical product types, private digital assets, MIME allowlisting, paid-order secure delivery, browser preview for PDF/text/CSV/images, and an audited refund ledger. The upload manager uses a server-side MIME allowlist, generated storage names and private file permissions. New uploads must also be refused when storage resolves inside the web root. Shipping/fulfilment, separate read/download prices, distinct entitlements and online-only line-item reimbursements remain target requirements.

## Acceptance criteria

- Physical-only checkout calculates delivery price; digital-only checkout has no shipping; mixed checkout charges only physical lines.
- Digital access and download can be purchased and revoked independently.
- Unsupported, executable, script and archive uploads are rejected.
- Uploads are stored outside the web root, have no execute permissions and are served only through authorized controllers.
- No antivirus dependency, scanner service, scan queue or quarantine workflow is introduced.
- Admin refunds cannot include physical goods, shipping or in-person course fees.
- Partial refunds revoke only the affected entitlement; payment/order reconciliation remains correct.
- Fresh/upgrade migrations and PHP 8.2/8.3 tests cover all cases.
