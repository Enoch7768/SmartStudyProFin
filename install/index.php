<?php

declare(strict_types=1);

$application = require dirname(__DIR__) . '/bootstrap/app.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, private');

use App\Core\Installer;
use App\Core\Security;

if (Installer::isInstalled()) {
    if (($_GET['installed'] ?? '') === '1') {
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installation complete</title><body style="font:16px system-ui;max-width:620px;margin:12vh auto;padding:24px;color:#182033"><h1>SmartStudyPro is installed</h1><p>Your administrator account and private database are ready. The installer is now locked.</p><p>Open the <a href="../admin/cms.php">native SmartStudyPro content manager</a> to manage your website.</p></body></html>';
        exit;
    }
    http_response_code(404);
    exit('The installer is disabled because this application is already installed.');
}

$errors = [];
$requirements = Installer::requirements();
$storagePrivate = Installer::storageIsPrivate($application['root']);
$databasePrivate = Installer::databaseIsPrivate();
$allPassed = !in_array(false, $requirements, true) && $storagePrivate && $databasePrivate;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh this page and try again.';
    } else {
        try {
            $password = (string) ($_POST['password'] ?? '');
            if (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) throw new RuntimeException('The passwords do not match.');
            Installer::install($application['root'], (string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), $password);
            header('Location: ./?installed=1', true, 303);
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install SmartStudyPro</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{color-scheme:light;--navy:#0C086B;--deep:#070443;--orange:#FF7A00;--ink:#182033;--muted:#667085;--line:#e7e9f0;--surface:#fff;--bg:#f5f6fb}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:"Plus Jakarta Sans",sans-serif;min-height:100vh;display:grid;place-items:center;padding:28px}.shell{width:min(100%,980px);display:grid;grid-template-columns:.9fr 1.1fr;background:var(--surface);border:1px solid var(--line);border-radius:24px;overflow:hidden;box-shadow:0 24px 70px rgba(7,4,67,.1)}.intro{padding:48px;background:linear-gradient(145deg,var(--deep),var(--navy));color:#fff;display:flex;flex-direction:column;justify-content:space-between}.mark{font:800 25px Outfit,sans-serif;letter-spacing:-.7px}.mark span{color:var(--orange)}h1{font:700 clamp(30px,4vw,43px)/1.12 Outfit,sans-serif;letter-spacing:-1px;margin:56px 0 16px}.intro p{color:#d9dcf5;line-height:1.8;margin:0}.tag{color:#ffd4ad;font-size:12px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;margin-top:46px}.panel{padding:44px}.panel h2{font:700 25px Outfit,sans-serif;margin:0 0 8px}.sub{color:var(--muted);font-size:14px;line-height:1.7;margin:0 0 26px}.field{margin:17px 0}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:8px}.field input{width:100%;height:48px;border:1px solid var(--line);border-radius:10px;padding:0 14px;font:inherit;outline:none}.field input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(12,8,107,.09)}button{border:0;border-radius:10px;background:var(--orange);color:#fff;padding:15px 20px;width:100%;font:700 14px "Plus Jakarta Sans",sans-serif;cursor:pointer;margin-top:8px}button:disabled{opacity:.45;cursor:not-allowed}.checks{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:20px 0 24px}.check{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:8px}.dot{width:8px;height:8px;border-radius:50%;background:#d04444}.dot.ok{background:#199765}.alert{padding:12px 14px;border-radius:10px;background:#fff1f0;color:#a12c24;font-size:13px;line-height:1.6;margin:14px 0}.fine{font-size:11px;color:var(--muted);line-height:1.7;margin-top:17px}@media(max-width:760px){body{padding:14px}.shell{grid-template-columns:1fr}.intro{padding:28px}.intro h1{margin:30px 0 12px}.panel{padding:28px}.checks{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="shell">
<section class="intro"><div><div class="mark">SmartStudy<span>Pro</span></div><div class="tag">Secure first-time setup</div><h1>Your learning platform starts here.</h1><p>Prepare your private database and create the first administrator account. No default passwords or demo accounts are installed.</p></div><div style="font-size:12px;color:#b9bee8">SmartStudyPro · Private installation</div></section>
<section class="panel"><h2>Application setup</h2><p class="sub">Check your server, then create your administrator account.</p>
<?php foreach ($errors as $error): ?><div class="alert"><?= Security::escape($error) ?></div><?php endforeach; ?>
<div class="checks"><?php foreach ($requirements as $label => $passed): ?><div class="check"><span class="dot<?= $passed ? ' ok' : '' ?>"></span><?= Security::escape($label) ?></div><?php endforeach; ?><div class="check"><span class="dot<?= $storagePrivate ? ' ok' : '' ?>"></span>Private storage outside web root</div><div class="check"><span class="dot<?= $databasePrivate ? ' ok' : '' ?>"></span>Database inside private storage</div></div>
<form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<div class="field"><label for="name">Administrator name</label><input id="name" name="name" maxlength="120" required></div>
<div class="field"><label for="email">Administrator email</label><input id="email" name="email" type="email" maxlength="254" required></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
<div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
<button type="submit"<?= $allPassed ? '' : ' disabled' ?>>Install SmartStudyPro</button></form>
<p class="fine">Use HTTPS for installation. Set APP_STORAGE_PATH outside the public web root and DB_PATH inside that private directory. The application will not create public demo accounts.</p>
</section></main></body></html>
