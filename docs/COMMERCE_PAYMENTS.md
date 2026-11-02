# Commerce and payments

The commerce layer uses the private application SQLite database. Browser cart data contains product identifiers and quantities only; prices and titles are reloaded from published CMS records when the order is created. Order lines retain immutable purchase-time snapshots.

## Provider configuration

Set these values in the private `.env` file or `config/local.php`. Never commit live credentials.

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

PayPal's Orders API uses USD for the provider settlement amount in this integration. The application order remains denominated in UGX and records the configured UGX/USD conversion rate in payment metadata. Confirm the correct conversion rate and permitted currency setup with PayPal before enabling live payments. The conversion rate must be reviewed and updated by the merchant; it is not a live FX feed.

DPO is configured for UGX. The server creates a hosted-checkout token, verifies the DPO BackURL callback using `verifyToken`, and repeats verification on the customer return if needed. The callback endpoint is `dpo-webhook.php`.

## Digital product delivery

Online products can include up to 25 private downloadable assets with a combined maximum size of 250 MB per product. Supported delivery formats include PDF, TXT, CSV, common Word/Excel/PowerPoint documents, RTF, ODT and raster images. Public gallery images are managed separately and are shown on the product detail page. Private files are stored outside the public web root and are never exposed through their storage paths.

The CMS media library accepts the supported file formats. Open **Administration → Orders and payments → Product files and images** to attach public gallery images and private purchase files to an online product. Existing single-PDF product attachments remain supported.

After a verified payment, the customer's order history exposes secure view links for browser-previewable PDF, text, CSV and image assets, and download links for every attached file. Each request checks the signed-in owner, paid order, purchase line, private storage root and real file path. Full refunds disable digital delivery; partially refunded purchases retain access while a verified payment remains active.

Receipts are available from each order in the customer's order history. They show the immutable purchase-time item and amount snapshots, provider status, transaction reference and recorded refund totals.

## Refund operations

The administrator refund ledger records the request, amount, reason, requesting administrator, resolution administrator, provider refund reference, timestamps and append-only audit events. A request reserves refundable balance so overlapping requests cannot exceed the captured amount. A failed request releases its reservation. Successful partial refunds move the order to partially_refunded; a full refund moves both order and payment to refunded and revokes protected digital delivery.

The refund screen records and audits provider actions; it does not claim to move money or call an undocumented provider refund API. An administrator must first process the refund in the relevant PayPal or DPO merchant console, then record the provider-confirmed refund reference and resolution. Never mark a refund successful before provider confirmation.

## Reconciliation

The administrator order screen flags missing paid-payment records, paid payments attached to pending/cancelled orders, and order/payment amount mismatches. Review provider settlement reports against these exceptions before closing a reconciliation period.

## Payment safety

- Return URLs never mark an order paid on their own.
- PayPal is captured server-side and must return a completed capture with matching configured conversion.
- DPO must return result code `000`, a matching company reference, UGX amount, and a non-fraudulent result.
- Order prices are always read from the CMS, not submitted by the browser.
- Course enrolment activation occurs inside the same database transaction that marks the payment and order paid.
- Digital downloads are resolved by a paid order item and private media record, never by a path supplied by the browser.
- Duplicate provider references and payment events are constrained by unique indexes.
- PayPal webhooks must be registered to `https://YOUR_DOMAIN/payment-webhook.php`; set the matching `PAYPAL_WEBHOOK_ID`.
- DPO BackURL is `https://YOUR_DOMAIN/dpo-webhook.php`; the redirect URL returns to the authenticated order owner.
- PHP cURL is required for provider API calls. PHP SimpleXML is additionally required for DPO XML responses.

This release covers one-time course and product orders, secure multi-file delivery and an audited administrator refund ledger. Provider refund execution is manual through the merchant console and must be confirmed before the corresponding refund record is marked successful. Coupon campaigns, recurring subscriptions, tax and physical-shipping fulfilment are not enabled in this phase. The live provider credentials, merchant account configuration, DPO service type, PayPal conversion setup, webhook registration, and end-to-end sandbox/live transactions must be verified by the site owner before launch.

## Simulated demo payments

Demo payments are a separate, non-provider flow. They never call PayPal or DPO and never create a live provider payment record. They are available only when the server sets `DEMO_PAYMENTS_ALLOWED=true`, `APP_ENV` is exactly `local`, `development` or `staging`, and an administrator enables Demo payment mode in Administration → Orders and payments. Production is hard-blocked even if the database setting is enabled.

The checkout offers simulated success, failure and cancellation. A successful simulation marks the order paid and activates course enrolments; failed attempts leave the order pending; cancelled attempts cancel the order. Every simulation is stored separately in `demo_payment_events` and labelled DEMO in student and administrator order history. No real money moves and no provider is contacted.

Keep `DEMO_PAYMENTS_ALLOWED=false` in production. Use PayPal Sandbox and DPO's test environment to test actual gateway integrations.
