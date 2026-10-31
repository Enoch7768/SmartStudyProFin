# SmartStudyPro Legal and Production Sign-off Checklist

The repository's legal documents are implementation drafts. Do not treat publication of these files as proof of legal compliance or as a substitute for signed agreements.

## Developer and Client software licence

- [ ] Insert the Developer's full legal name and notice address in the signed project agreement.
- [ ] Insert the Client's full legal name and notice address.
- [ ] Identify the exact licensed production domain(s), permitted business use, licence term and any renewal rights.
- [ ] Confirm development fees, recurring licence fees, hosting, maintenance, support response times, update entitlement and payment milestones.
- [ ] Agree source-code access, escrow or handover terms explicitly; the default licence does not transfer source-code ownership.
- [ ] Identify client-owned content, trademarks, data and assets separately from developer-owned original code and third-party components.
- [ ] Complete governing law, dispute resolution, warranties, liability, indemnities, termination and data export provisions.
- [ ] Have both parties sign the EULA/development agreement before treating the licence as effective.

## Privacy and data protection

- [ ] Confirm the actual data controller's registered legal name, physical/service address and privacy contact.
- [ ] Confirm whether the controller and any relevant processor must be registered with Uganda's Personal Data Protection Office and publish accurate registration details.
- [ ] Appoint and publish the appropriate privacy/data-protection contact or officer where required.
- [ ] Map each category of student, parent, staff, customer, payment, booking, learning, certificate, support and AI data to its purpose and legal basis.
- [ ] Confirm contracts, subprocessor terms and international transfer safeguards for hosting, PayPal, DPO, Google Gemini, email, backups and support providers.
- [ ] Decide whether the AI assistant is enabled for children and document safeguards, notices, guardian authorisation and provider settings.
- [ ] Implement and test a parent/guardian authorisation process before collecting personal data from children where required. The current account form does not independently verify a guardian relationship.
- [ ] Define record-specific retention periods and a secure deletion/de-identification process, including backups and legal/financial records.
- [ ] Establish a process and response owner for access, correction, deletion, objection, consent withdrawal and complaint requests.
- [ ] Confirm breach response, escalation, notification and record-keeping procedures.
- [ ] Verify the Privacy Policy accurately reflects the production configuration and enabled integrations.

## Consumer and education operations

- [ ] Approve the actual refund, cancellation, returns, booking and digital-content access rules and display them before checkout.
- [ ] Confirm course certificate wording, recognition claims and any external accreditation claims.
- [ ] Confirm guardian supervision and child-safety procedures for the education service.
- [ ] Verify contact details, operating hours and complaint handling.

## Technical production readiness

- [ ] Configure HTTPS, private storage outside the web root, production APP_KEY, APP_ENV=production and APP_DEBUG=false.
- [ ] Configure and test payment webhooks in provider sandbox mode before enabling live payments.
- [ ] Configure email delivery and test password recovery.
- [ ] Configure Gemini only after reviewing provider terms, privacy settings and data-processing implications.
- [ ] Test private backups, restore procedures and retention of backup encryption keys.
- [ ] Run the PHP 8.1 and 8.3 CI matrix and perform browser, accessibility, payment and restore smoke tests on the actual hosting environment.
- [ ] Verify the final release package contains no secrets, production databases, logs, backups or development-only CMS files.

## Legal references

The operator should review the Uganda Data Protection and Privacy Act, 2019 and Data Protection and Privacy Regulations, 2021, together with other applicable consumer, electronic transactions, copyright and education requirements. Qualified Ugandan legal advice is recommended before launch.
