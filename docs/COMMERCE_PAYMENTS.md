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

This release covers one-time course and product orders. Automated refunds, coupon campaigns, recurring subscriptions, tax, and physical-shipping fulfilment are not enabled in this phase. The live provider credentials, merchant account configuration, DPO service type, PayPal conversion setup, webhook registration, and end-to-end sandbox/live transactions must be verified by the site owner before launch.

## Simulated demo payments

Demo payments are a separate, non-provider flow. They never call PayPal or DPO and never create a live provider payment record. They are available only when the server sets `DEMO_PAYMENTS_ALLOWED=true`, `APP_ENV` is exactly `local`, `development` or `staging`, and an administrator enables Demo payment mode in Administration → Orders and payments. Production is hard-blocked even if the database setting is enabled.

The checkout offers simulated success, failure and cancellation. A successful simulation marks the order paid and activates course enrolments; failed attempts leave the order pending; cancelled attempts cancel the order. Every simulation is stored separately in `demo_payment_events` and labelled DEMO in student and administrator order history. No real money moves and no provider is contacted.

Keep `DEMO_PAYMENTS_ALLOWED=false` in production. Use PayPal Sandbox and DPO's test environment to test actual gateway integrations.
