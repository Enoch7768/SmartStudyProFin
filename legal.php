<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

$document = (string) ($legalDocument ?? 'privacy');
$pages = [
    'privacy' => [
        'title' => 'Privacy Policy',
        'version' => '1.0.0',
        'intro' => 'How SmartStudyPro collects, uses, stores and protects personal information.',
        'sections' => [
            ['Your information', 'Depending on the features you use, we may process your name, email address, password hash, profile details, phone number, location, learning progress, bookmarks, quiz and assessment attempts, achievements, certificates, orders, payment references, support messages and security records. Do not send passwords, card security codes or unnecessary sensitive information in messages or AI prompts.'],
            ['How information is used', 'We use information to create and secure accounts; provide courses, progress tracking, assessments and certificates; process orders and payments; deliver downloads; respond to requests; provide AI-assisted learning where enabled; prevent fraud and abuse; maintain backups and service reliability; meet legal obligations; and resolve disputes.'],
            ['Service providers', 'Information may be processed by hosting and infrastructure providers, PayPal or DPO for payments, Google Gemini when the AI assistant is enabled, and email, domain, security, backup and technical support providers. When Gemini is enabled, relevant prompts, conversation context and retrieved course excerpts may be sent to Google’s API to generate a response. Provider privacy terms also apply. We do not sell personal data or share it with unrelated parties for their independent marketing.'],
            ['International processing', 'Some service providers may process information outside Uganda. The operator must assess applicable cross-border transfer requirements, provider safeguards, contractual terms and any consent or other legal basis required by Ugandan law before enabling a provider.'],
            ['Children', 'A parent or legal guardian must authorise and supervise a child’s account and use. We do not intentionally seek personal information from a child without required parent or guardian authorisation. Contact us if you believe a child’s information was provided without appropriate authorisation.'],
            ['Retention', 'Information is retained only as long as reasonably necessary to provide the service, maintain learning and transaction records, protect security, resolve disputes and comply with law. Retention periods vary by record type. Information that is no longer required should be securely deleted or de-identified, subject to lawful retention and backup rotation.'],
            ['Security', 'The platform uses measures such as password hashing, access controls, CSRF protection, session hardening, private storage, payment verification and restricted media delivery. No internet service can be guaranteed completely secure. Keep your password private and report suspected account compromise promptly.'],
            ['Your rights', 'Subject to applicable law, you may request access to your personal information, correction of inaccurate information, deletion where retention is no longer authorised or necessary, information about processing, or withdrawal of consent where consent is the legal basis. You may also raise a complaint with Uganda’s Personal Data Protection Office. We may need to verify your identity before responding.'],
            ['Cookies and local storage', 'The website uses an essential session cookie for authentication and security and browser local storage to remember your light/dark theme preference. See the Cookie Notice for details.'],
            ['Controller identity and contact', 'The website is operated as SmartStudyPro, Educational Resources, Kampala, Uganda. The operating entity’s full registered legal name, service address and applicable Personal Data Protection Office registration details must be confirmed and published by the operator before production launch. Contact smartstudypro36@gmail.com or +256 704 416250 for privacy requests.']
        ]
    ],
    'terms' => [
        'title' => 'Terms of Use',
        'version' => '1.0.0',
        'intro' => 'The rules that apply when you use SmartStudyPro accounts, learning services, purchases and other features.',
        'sections' => [
            ['Accounts and eligibility', 'Provide accurate information, keep your credentials confidential and promptly report suspected unauthorised use. A parent or legal guardian must review and accept these Terms and the Privacy Policy before a child uses an account, and supervise that use. Do not submit a child’s personal information unless you are authorised and required parent or guardian consent has been obtained.'],
            ['Learning and outcomes', 'Courses, quizzes, assessments and AI study assistance are educational resources. They do not guarantee examination results, admission, employment or a qualification from an external institution. Complete assessments honestly. Do not impersonate another learner, falsify progress or certificates, or access another learner’s materials or records. Certificates verify platform records and are not government qualifications or external accreditation unless expressly stated and independently verified.'],
            ['Acceptable use', 'Do not break the law, infringe rights, upload malicious code, attempt unauthorised access, disrupt service, scrape protected content, misuse another user’s data, harass or impersonate people, submit content you do not have rights to use, or manipulate payment, booking, quiz, certificate or achievement records.'],
            ['Intellectual property', 'The Developer and other rights holders retain rights in the website software, platform design, branding and protected learning content. You receive a personal, limited, revocable, non-transferable right to access the service and use content made available to you for your own learning. Do not copy, sell, redistribute, publicly display or create derivative works from protected content without permission. Your submitted materials remain yours; you grant only the permission needed to store, display and process them to provide the service.'],
            ['Payments', 'Prices, currency, descriptions, delivery method and applicable charges are shown before checkout. PayPal, DPO or the provider shown at checkout processes payments under its own terms. The website does not intend to store full payment-card numbers or card security codes. An order is not paid merely because a browser returns to the website; provider payment must be verified. Refunds, cancellations, returns and digital-access rules are subject to the policy shown at purchase and applicable consumer law.'],
            ['AI learning assistant', 'AI responses may be incomplete or incorrect and should be checked against course materials and authoritative sources. Do not use AI responses for emergencies or as professional advice. Relevant prompts, conversation context and retrieved course excerpts may be sent to the configured AI provider. Do not submit passwords, payment-card details or another person’s confidential information.'],
            ['Availability and suspension', 'We aim to keep the service available and secure but do not guarantee uninterrupted access. Maintenance, connectivity, hosting, provider outages and security incidents may affect availability. We may restrict access for material breach, security risk, unlawful use, non-payment or legal requirements, taking reasonable steps to explain the reason where safe and lawful.'],
            ['Privacy and changes', 'The Privacy Policy explains how personal information is handled. We may update these Terms when services, law or security requirements change and will communicate material changes through the website or another reasonable channel. The version and acceptance date may be recorded for your account.'],
            ['Contact', 'Questions or complaints: smartstudypro36@gmail.com · +256 704 416250 · Kampala, Uganda. These Terms must be reviewed by Ugandan counsel and aligned with the operator’s registered identity and actual refund and support procedures before launch.']
        ]
    ],
    'eula' => [
        'title' => 'Client Software Licence Agreement',
        'version' => '1.0.0',
        'intro' => 'This licence describes the Developer’s ownership of the original SmartStudyPro software and the limited right granted to the Client to operate the deployed website. It is a client contract draft, not an agreement that website visitors are asked to sign.',
        'sections' => [
            ['Ownership — all rights reserved', 'The Developer retains all right, title, interest and intellectual-property rights in the original source code, software architecture, database structure, custom modules, workflows, integrations, design implementation, documentation, updates and developer-created derivative works. Payment for development, setup, hosting, maintenance or support does not transfer ownership of the software or source code to the Client.'],
            ['Limited licence to the Client', 'Subject to the signed project agreement and payment of applicable fees, the Developer grants the Client a limited, non-exclusive, non-transferable and non-sublicensable licence to operate the deployed website for the Client’s own education and business activities at the domain or domains stated in the signed agreement. No right to resell, sublicense, distribute, publish, or deploy additional copies is granted unless agreed in writing.'],
            ['Restrictions', 'The Client must not copy, publish, sell, rent, sublicense, distribute or commercially exploit the source code; remove proprietary notices; extract developer-created modules for another product; circumvent technical or security restrictions; or give third parties source-code access unless authorised in writing and protected by confidentiality obligations. Mandatory rights that cannot lawfully be restricted remain unaffected.'],
            ['Client materials', 'Client-provided content, names, logos, trademarks, student records and other materials remain owned by the Client or their respective rights holders. The Client authorises the Developer to use them only as reasonably necessary to build, maintain, support and deliver the contracted website. Third-party software, fonts, icons, media and libraries remain subject to their own licences; this agreement does not claim ownership of them.'],
            ['Client responsibilities', 'The Client is responsible for the legality and rights clearance of content it publishes, its users and administrator access, its lawful collection and use of personal data, and compliance with education, consumer, tax and privacy rules applicable to its operations. Hosting, domain registration, provider fees, email delivery, ongoing moderation, legal advice and continuous support are excluded unless the signed project agreement includes them.'],
            ['Hosting, support and updates', 'The project agreement must define hosting, maintenance, support response times, backups, recovery objectives, update entitlement, fees, licensed domains and third-party services. No perpetual maintenance or hosting service is implied by this licence.'],
            ['Personal data', 'The parties must document their controller/processor roles, permitted processing, security measures, subprocessors, breach assistance, retention and deletion arrangements where the Developer processes personal data for the Client. The Client remains responsible for determining the lawful purposes for which student, parent, staff, customer and visitor data is collected.'],
            ['Termination and liability', 'Fees, suspension rights, acceptance criteria, defect warranty, liability allocation and dispute resolution must be completed in the signed project agreement. On termination, the Client must stop using the licensed software. The parties must agree a lawful export and handover of Client content and personal data before access is disabled. No party excludes liability that cannot lawfully be excluded.'],
            ['Execution and legal review', 'The Developer/Licensor legal name, Client/Licensee legal name, service addresses, licensed domain, fees, governing law, support scope and authorised signatures must be completed in a separate signed project agreement. This online copy is a reference draft and is not effective until completed and signed by both parties. Obtain qualified legal review before signature.']
        ]
    ],
    'cookies' => [
        'title' => 'Cookie and Local Storage Notice',
        'version' => '1.0.0',
        'intro' => 'SmartStudyPro uses essential browser storage to operate account sessions and remember your display preference.',
        'sections' => [
            ['Authentication session cookie', 'An essential session cookie supports signed-in sessions and security controls. It is not used for advertising. Blocking it may prevent sign-in and account features from working.'],
            ['Theme preference', 'Browser local storage remembers whether you selected light or dark appearance. This preference is not an authentication credential. Clearing it resets the appearance preference.'],
            ['Third-party services', 'When you leave SmartStudyPro to sign in with or pay through a third-party provider, that provider may use its own cookies and similar technologies under its own notices. We do not control those technologies.'],
            ['Analytics and advertising', 'The application does not intentionally use advertising cookies or cross-site behavioural advertising trackers. If analytics, embedded media, chat widgets or additional tracking are introduced, this Notice must be updated and required consent obtained before activation.'],
            ['Managing storage', 'You can clear cookies and local storage through your browser settings. Clearing the session cookie signs you out. Contact smartstudypro36@gmail.com with questions.']
        ]
    ],
    'accessibility' => [
        'title' => 'Accessibility Statement',
        'version' => '1.0.0',
        'intro' => 'SmartStudyPro aims to make learning and account services usable by as many people as possible.',
        'sections' => [
            ['Our approach', 'We aim to use semantic page structure, descriptive headings and labels, keyboard-accessible controls, visible focus states, clear form validation, text alternatives for meaningful images, accessible names for icon-only controls, reduced-motion preferences, readable contrast and responsive layouts. Learning materials should be supplied in accessible formats where reasonably practicable.'],
            ['Supported environments', 'The website is designed for current versions of Chrome, Edge, Firefox and Safari on desktop and mobile, with keyboard navigation and commonly used screen readers. Actual support can vary with browser, assistive technology, third-party content, uploaded course files and materials supplied by content authors.'],
            ['Known limitations', 'Some legacy pages, third-party payment screens, embedded media, uploaded PDFs, images, quizzes and administrator-authored content may not fully meet accessibility expectations. Automated checks cannot establish full WCAG conformance. We do not claim certification or complete conformance at this time.'],
            ['Report a barrier', 'Email smartstudypro36@gmail.com with the page or feature, assistive technology/browser if known, and what you were trying to do. You may also call +256 704 416250. Do not include passwords or sensitive student records. We will review reported barriers and prioritise practical fixes.']
        ]
    ],
    'refunds' => [
        'title' => 'Purchases, Cancellations and Refunds',
        'version' => '1.0.0',
        'intro' => 'This notice explains how to raise a question about an order. The terms shown at checkout and applicable consumer law take priority where they provide a mandatory right.',
        'sections' => [
            ['Before payment', 'Review the item description, price, currency, delivery method and any access conditions before confirming payment. Keep your order reference and payment confirmation.'],
            ['Payment verification', 'A browser redirect alone does not establish that payment succeeded. Orders are completed after the payment provider response is verified by the platform. If money appears to have been deducted but the order remains pending, contact support with your order reference and provider transaction reference. Never email card numbers or security codes.'],
            ['Digital courses and downloads', 'Access to digital content may begin after verified payment. If access is missing, the file is defective or the purchased item differs materially from its description, contact support promptly so the issue can be investigated. Any refund or cancellation will be handled according to the terms displayed at purchase and applicable law.'],
            ['Physical products and bookings', 'For a physical item or booking, include the order reference, item, date and reason for the request. Return condition, delivery costs, cancellation windows and any exceptions must be stated in the item-specific terms shown before purchase.'],
            ['Contact', 'For order or refund requests, email smartstudypro36@gmail.com or call +256 704 416250. Include only the order reference and information needed to investigate. Do not send payment-card details.']
        ]
    ]
];

if (!isset($pages[$document])) {
    http_response_code(404);
    $document = 'privacy';
}
$page = $pages[$document];
$seoTitle = $page['title'] . ' | SmartStudyPro';
$seoDescription = $page['intro'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
<title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ssp-navy:#0C086B;--ssp-navy-dark:#070443;--ssp-orange:#FF7A00;--ssp-orange-hover:#E06B00;--ssp-bg-main:#FFFFFF;--ssp-bg-soft:#F8FAFC;--ssp-card-bg:#FFFFFF;--ssp-card-border:#E2E8F0;--ssp-text-main:#1E293B;--ssp-text-muted:#64748B}
[data-theme="dark"]{--ssp-navy:#C7D2FE;--ssp-navy-dark:#0B0F17;--ssp-bg-main:#0B0F17;--ssp-bg-soft:#111827;--ssp-card-bg:#151C2C;--ssp-card-border:#2E3A52;--ssp-text-main:#F8FAFC;--ssp-text-muted:#CBD5E1}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--ssp-bg-soft);color:var(--ssp-text-main)}
h1,h2,h3{font-family:Outfit,sans-serif}
.legal-wrap{max-width:980px;margin:0 auto;padding:56px 18px 76px}
.legal-card{background:var(--ssp-card-bg);border:1px solid var(--ssp-card-border);border-radius:18px;padding:clamp(22px,4vw,48px);box-shadow:0 12px 34px rgba(7,4,67,.07)}
.legal-card h1{color:var(--ssp-navy);font-weight:800;letter-spacing:-.6px}
.legal-card h2{font-size:1.2rem;color:var(--ssp-navy);font-weight:700;margin-top:30px}
.legal-card p{line-height:1.85;color:var(--ssp-text-muted)}
.legal-meta{font-size:.85rem;color:var(--ssp-text-muted)}
.legal-links{display:flex;flex-wrap:wrap;gap:12px 20px;margin-top:30px;padding-top:22px;border-top:1px solid var(--ssp-card-border)}
.legal-links a{color:var(--ssp-orange);font-weight:700;text-decoration:none}
.legal-footer{background:var(--ssp-navy-dark);color:#CBD5E1;padding:24px 16px;text-align:center;font-size:.85rem}
.legal-footer a{color:#fff;margin:0 8px;text-decoration:none}
.legal-footer a:hover{color:var(--ssp-orange)}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="legal-wrap">
    <article class="legal-card">
        <div class="text-uppercase fw-bold small mb-2" style="color:var(--ssp-orange)">SmartStudyPro · Legal information</div>
        <h1 class="display-5 mb-3"><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mb-2"><?= htmlspecialchars($page['intro'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="legal-meta">Version <?= htmlspecialchars($page['version'], ENT_QUOTES, 'UTF-8') ?> · Effective 31 October 2026</div>
        <?php foreach ($page['sections'] as [$heading, $body]): ?>
            <section>
                <h2><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
            </section>
        <?php endforeach; ?>
        <nav class="legal-links" aria-label="Other legal documents">
            <a href="privacy.php">Privacy</a>
            <a href="terms.php">Terms of Use</a>
            <a href="cookies.php">Cookies</a>
            <a href="refunds.php">Refunds</a>
            <a href="accessibility.php">Accessibility</a>
            <a href="eula.php">Client Software Licence</a>
        </nav>
    </article>
</main>
<footer class="legal-footer">
    <div class="mb-2">© <?= date('Y') ?> SmartStudyPro. All rights reserved.</div>
    <a href="index.php">Home</a>
    <a href="contact.php">Contact</a>
    <a href="privacy.php">Privacy</a>
    <a href="terms.php">Terms</a>
</footer>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
