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

## Digital product delivery and file safety

Online products support private digital assets. Public gallery images are separate. Private files must be stored outside the public web root and never exposed through storage paths.

No antivirus scanning is performed. The application uses a server-side allowlist for useful content formats: PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, ODT, RTF, TXT, CSV, JPEG, PNG, WebP, GIF, MP3, MP4 and ZIP. ZIP files are private-only download attachments: they cannot be previewed, extracted, indexed or executed. Office document containers detected as ZIP data are accepted only with a matching DOCX, XLSX, PPTX or ODT extension. Server-side scripts, executables, HTML, JavaScript, SVG, other archive formats, installers and unknown formats are not accepted.

The server determines the stored extension and MIME type; client-supplied MIME and filenames are not trusted for storage decisions. Files receive random server-generated storage names and restrictive permissions. The configured storage path must resolve outside the public web root. Uploaded files are never included, evaluated, interpreted, executed or passed to a command or template engine.

Private downloads are served only after checking the authenticated buyer's paid order entitlement. Administrator previews require administrator permission. Responses use server-selected MIME types, X-Content-Type-Options: nosniff, safe Content-Disposition and private caching. Documents are delivered as attachments; only selected preview formats are rendered inline. This prevents server-side execution, but it is not a guarantee that a document is free of malicious content when opened by a user.

## Refund operations

The administrator refund ledger records the request, amount, reason, requesting administrator, resolution administrator, provider refund reference, timestamps and append-only audit events. A request reserves refundable balance so overlapping requests cannot exceed the captured amount. A failed request releases its reservation. Successful partial refunds move the order to partially_refunded; a full refund moves both order and payment to refunded and revokes protected digital delivery.

The refund screen records and audits provider actions; it does not claim to move money or call an undocumented provider refund API. An administrator must first process the refund in the relevant PayPal or DPO merchant console, then record the provider-confirmed refund reference and resolution. Never mark a refund successful before provider confirmation.

The reimbursement workflow is restricted to online activities: course purchases and digital products. Refund requests now reference a specific order line, reserve pending and succeeded amounts against that line's purchase-time total, and reject physical product lines. A fully refunded digital product loses its private file access independently of other order items; course access is revoked only when that course line is fully refunded and no other paid purchase grants access. Physical delivery and in-person course fees remain excluded.

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

This release covers one-time product orders, legacy course orders and selected course-offering purchases, secure multi-file delivery, independent read/download entitlements, server-priced physical shipping and an audited administrator refund ledger. Provider refund execution is manual through the merchant console and must be confirmed before the corresponding refund record is marked successful. Physical shipping and fulfilment now use configurable country/region zones, server-priced delivery methods, order-time address/method/fee snapshots and admin-managed processing/shipped/delivered states with carrier and tracking references. Digital products now have independent read/view and download prices. Read purchases can preview supported formats in-browser but cannot download originals; download purchases can retrieve the original files but do not receive browser-reader access. Each order line snapshots its entitlement and purchase-time price, and refunds revoke only that entitlement. Inventory reservations remain an implementation requirement. Refund allocations are now line-specific and exclude physical goods. Self-paced course offerings grant learning access; live-online and in-person offerings grant separate offering enrolments used by the meeting planner. Session booking requires the matching active offering enrolment. In-person course fees are excluded from the online refund ledger. Coupon campaigns, recurring subscriptions and tax are not enabled. Live provider credentials, merchant account configuration, DPO service type, PayPal conversion setup, webhook registration and end-to-end transactions must be verified by the site owner before launch.

## Simulated demo payments

Demo payments are a separate, non-provider flow. They never call PayPal or DPO and never create a live provider payment record. They are available only when the server sets DEMO_PAYMENTS_ALLOWED=true, APP_ENV is exactly local, development or staging, and an administrator enables Demo payment mode in Administration → Orders and payments. Production is hard-blocked even if the database setting is enabled.

The checkout offers simulated success, failure and cancellation. A successful simulation marks the order paid and activates course enrolments; failed attempts leave the order pending; cancelled attempts cancel the order. Every simulation is stored separately in demo_payment_events and labelled DEMO in student and administrator order history. No real money moves and no provider is contacted.

Keep DEMO_PAYMENTS_ALLOWED=false in production. Use PayPal Sandbox and DPO's test environment to test actual gateway integrations.
