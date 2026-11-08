# Commerce and payments

The commerce layer uses the private application SQLite database. Browser cart data contains product identifiers and quantities only; prices and titles are reloaded from published CMS records when the order is created. Order lines retain immutable purchase-time snapshots.

## Provider configuration

Set these values in the private .env file or config/local.php. Never commit live credentials.

```dotenv
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_UGX_PER_USD=3700
DPO_COMPANY_TOKEN=
DPO_ENDPOINT=https://secure.3gdirectpay.com/API/v6/
DPO_CHECKOUT_URL=https://secure.3gdirectpay.com/payv2.php
DPO_SERVICE_TYPE=5525
```

PayPal's Orders API uses USD for provider settlement in this integration. The application order remains denominated in UGX and records the configured conversion rate. Confirm the merchant's permitted currency setup before enabling live payments. The rate is merchant-maintained, not a live FX feed.

DPO is configured for UGX. The server creates a hosted-checkout token and verifies the DPO callback using verifyToken. The callback endpoint is dpo-webhook.php.

## Digital product delivery and upload policy

Online products support private digital assets. Public gallery images are separate. Private files must be stored outside the public web root and never exposed through storage paths.

The required upload policy is a strict server-side allowlist, **not antivirus scanning**. Initial supported formats are sanitized PDF; UTF-8 plain text and CSV; re-encoded JPEG, PNG and WebP; validated/canonicalized MP3 and MP4; and macro-free DOCX/XLSX/PPTX only when a dedicated package validator is available. If that validator is unavailable, reject those Office formats.

Reject PHP and other scripts, executables, shell/batch files, JavaScript, HTML, SVG, XML, macro-enabled Office formats, RTF, archives, installers, disk images, fonts, shortcut files and unknown formats.

Every upload must pass filename normalization, detected MIME, signature and full structural validation. All signals must agree. Reject double extensions, spoofed MIME, polyglots, malformed containers, ambiguous names, unsupported codecs and parser errors. Client-provided MIME is never trusted. Images are decoded/re-encoded; PDF active features are removed or rejected; Office packages are inspected for macros, OLE, ActiveX, embedded files and external relationships. If a safe parser/transformer is unavailable or fails, reject the file. Bound file size, image dimensions, media duration/resolution, parser time and container entry counts.

No antivirus engine, background malware scan, scan status or scanner dependency is part of this design. This policy reduces attack surface but cannot guarantee that every accepted file is malware-free. Uploads remain outside the web root in non-executable private storage with random storage names and restrictive permissions. Deliver files only through authenticated entitlement-checking endpoints with server-selected MIME, X-Content-Type-Options: nosniff, safe content disposition and sandboxed previews. Never execute user uploads.

The current implementation supports private digital assets, MIME allowlisting, secure paid-order delivery, browser preview for PDF/text/CSV/images and an audited refund ledger. The stricter format-specific validation above is a target requirement, not a claim that existing upload handling already implements it.

## Refund operations

The administrator refund ledger records the request, amount, reason, requesting administrator, resolution administrator, provider refund reference, timestamps and append-only audit events. A request reserves refundable balance so overlapping requests cannot exceed the captured amount. A failed request releases its reservation. Successful partial refunds move the order to partially_refunded; a full refund moves both order and payment to refunded and revokes protected digital delivery.

The refund screen records and audits provider actions; it does not claim to move money or call an undocumented provider refund API. An administrator must first process the refund in the relevant PayPal or DPO merchant console, then record the provider-confirmed refund reference and resolution. Never mark a refund successful before provider confirmation.

The required reimbursement workflow is restricted to online activities: self-paced courses, live online course/session fees and digital product access/download entitlements. Physical products, shipping and in-person course fees are excluded. It must allocate refunds to eligible order lines, revoke only the refunded entitlement and prevent mixed-order refunds from reimbursing physical goods or delivery charges. The current payment/order-level ledger does not yet meet this line-level requirement.

## Reconciliation

The administrator order screen flags missing paid-payment records, paid payments attached to pending/cancelled orders, and order/payment amount mismatches. Review provider settlement reports against these exceptions before closing a reconciliation period.

## Payment safety

- Return URLs never mark an order paid on their own.
- PayPal is captured server-side and must return a completed capture with matching configured conversion.
- DPO must return result code 000, a matching company reference, UGX amount, and a non-fraudulent result.
- Order prices are always read from the CMS, not submitted by the browser.
- Course enrolment activation occurs inside the same database transaction that marks the payment and order paid.
- Digital downloads are resolved by a paid order item and private media record, never by a path supplied by the browser.
- Duplicate provider references and payment events are constrained by unique indexes.
- PayPal webhooks must be registered to https://YOUR_DOMAIN/payment-webhook.php; set the matching PAYPAL_WEBHOOK_ID.
- DPO BackURL is https://YOUR_DOMAIN/dpo-webhook.php; the redirect URL returns to the authenticated order owner.
- PHP cURL is required for provider API calls. PHP SimpleXML is additionally required for DPO XML responses.

This release covers one-time course and product orders, secure multi-file delivery and an audited administrator refund ledger. Provider refund execution is manual through the merchant console and must be confirmed before the corresponding refund record is marked successful. Physical shipping, fulfilment, dual-priced digital entitlements and online-only line-item refunds remain implementation requirements. Coupon campaigns, recurring subscriptions and tax are not enabled. Live provider credentials, merchant account configuration, DPO service type, PayPal conversion setup, webhook registration and end-to-end transactions must be verified by the site owner before launch.

## Simulated demo payments

Demo payments are a separate, non-provider flow. They never call PayPal or DPO and never create a live provider payment record. They are available only when the server sets DEMO_PAYMENTS_ALLOWED=true, APP_ENV is exactly local, development or staging, and an administrator enables Demo payment mode in Administration → Orders and payments. Production is hard-blocked even if the database setting is enabled.

The checkout offers simulated success, failure and cancellation. A successful simulation marks the order paid and activates course enrolments; failed attempts leave the order pending; cancelled attempts cancel the order. Every simulation is stored separately in demo_payment_events and labelled DEMO in student and administrator order history. No real money moves and no provider is contacted.

Keep DEMO_PAYMENTS_ALLOWED=false in production. Use PayPal Sandbox and DPO's test environment to test actual gateway integrations.
