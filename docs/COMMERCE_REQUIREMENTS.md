# Commerce requirements — shipping, digital access, malware scanning and refunds

This document defines required commerce work that is not yet implemented. Existing commerce/payment behaviour must remain intact until each capability has migrations, tests and UI.

## Physical products and shipping

- Capture and validate shipping address at checkout.
- Support configurable shipping zones/methods and delivery prices: flat fee, zone-based fee and optional product/weight-based calculation.
- Show shipping separately from item subtotal and include it in the server-calculated total. Never accept a client-submitted shipping amount.
- Snapshot shipping address and selected method onto the order; later profile edits must not rewrite historical orders.
- Mixed carts charge shipping only for physical lines. Digital products never incur physical shipping.
- Add fulfilment states (unfulfilled, processing, dispatched, delivered, failed delivery, returned), carrier/tracking reference and audit history.
- Add inventory quantities/reservations and transactional stock checks to prevent overselling. Release expired reservations after failed/cancelled checkout.

## Digital product access and download pricing

For online books, videos, audio, documents and images, administrators configure two independent UGX prices:

1. **Read/view/stream price:** grants access inside SmartStudyPro through a browser reader/viewer, similar in concept to Internet Archive. Does not grant the original file download.
2. **Download price:** grants a downloadable copy of the original approved file. Does not automatically grant browser access unless both entitlements are bought or an explicit product policy grants both.

Customers may buy either entitlement or both. Catalogue, cart, order lines, receipts and account library must identify entitlements separately. Each entitlement needs its own server-enforced permission and immutable purchase-time price snapshot. Samples/previews are a separate admin-controlled setting; do not expose full content before purchase by default.

Support in-browser preview only for formats with a safe viewer. Other formats remain download-only until a safe preview exists. Never execute active document content in the application origin; do not promise screenshot prevention.

## Upload safety and malware scanning

- Store all uploads outside the public web root, in private non-executable directories not mapped to web handlers. Never expose raw storage paths.
- Use random storage names, restrictive permissions, path containment checks and immutable SHA-256 hashes.
- Enforce an explicit allowlist of extensions, detected MIME types and file signatures. “Any file” means broad supported safe formats over time, not arbitrary executables.
- Reject scripts/executables and active content such as PHP, shell/batch, JavaScript, HTML and SVG unless a separate sanitizing pipeline is proven safe. Archives require their own inspection/decompression limits.
- Enforce file count, individual/aggregate size, upload time and decompression limits.
- Upload into quarantine. Files cannot be attached, published, previewed or downloaded until scan verdict is clean.
- Integrate ClamAV or another approved malware scanner through an isolated scanner process/service where hosting supports it. Do not execute uploaded content.
- On shared hosting without a scanner, fail closed: retain quarantine and allow retry or approved external scanning; never silently skip scanning.
- Record scan status, engine/version, scan time, result, checksum and error. A timeout, scanner outage, stale signatures, malformed response or unknown verdict is not clean.
- Serve private files through authenticated controllers with `nosniff`, safe content disposition and path checks; consider a separate content origin if active formats are ever supported.

## Administrator reimbursements — online activities only

Refund/reimbursement workflow is limited to online activities: self-paced course purchases, live online course/session fees and digital-product access/download entitlements. Physical products, shipping fees and in-person course fees are excluded.

- Calculate refundable balances from eligible order lines and pending/succeeded refunds; never trust browser amounts.
- Mixed carts require line-level allocation. Refunding a digital entitlement must not refund physical goods or shipping.
- Refund of one digital entitlement revokes only that entitlement. Course access is revoked only if no other valid purchase/enrolment source grants it.
- Require reason, authorized administrator, provider-confirmed reference and append-only audit history.
- Never mark success before PayPal/DPO confirms money movement. If provider refund APIs are not integrated, process in merchant console and record the verified reference.
- Block physical goods, shipping and in-person course fees from this workflow; direct staff to the separate offline/manual process.
- Use transactions/idempotency so concurrent requests cannot exceed captured payment or eligible online line balances.

## Existing behaviour and implementation status

Current code supports online/physical product types, private digital assets, MIME allowlisting, paid-order secure delivery, browser preview for PDF/text/CSV/images, and an audited refund ledger. However, the current refund ledger is payment/order-level rather than online-only line-item reimbursement. Automatic malware scanning/quarantine, physical shipping/fulfilment, separate read/download prices and their distinct entitlements are not yet implemented. Do not describe these features as live until implemented and tested.

## Acceptance criteria

- Physical-only checkout captures shipping and calculates the correct server-side delivery price.
- Digital-only checkout has no shipping charge; mixed checkout charges only physical lines.
- Digital access and download can be purchased independently and independently revoked.
- Quarantined, unsupported, infected or unscannable files cannot be published or delivered.
- Private uploads remain outside the web root and cannot execute.
- Admin refunds cannot include physical goods, shipping or in-person course fees.
- Partial refunds revoke only the affected entitlement; payment/order reconciliation remains correct.
- Migrations work on fresh and existing databases; PHP 8.2/8.3 tests cover all cases.
