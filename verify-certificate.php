<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Database;
use App\Core\Security;
use App\Learning\RecognitionService;

$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$certificate = (new RecognitionService(Database::connect()))->verifyCertificate($code);
if (!$certificate) http_response_code(404);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Certificate verification | SmartStudyPro</title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet">
<style>body{background:#F5F6FB;color:#1E293B;font-family:"Plus Jakarta Sans",sans-serif}.verify{max-width:680px;margin:10vh auto;background:#fff;border:1px solid #E2E8F0;border-radius:18px;padding:36px;text-align:center}.seal{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;background:#e9f8ef;color:#198754;font-size:30px;margin:0 auto 18px}</style>
</head>
<body><main class="verify"><?php if ($certificate): ?><div class="seal"><i class="bi bi-patch-check-fill"></i></div><div class="small text-uppercase fw-bold text-success">Valid certificate</div><h1 class="h3 mt-2">Certificate verified</h1><p class="text-secondary">This confirms that the certificate below was issued by SmartStudyPro.</p><hr><p class="mb-1 text-secondary">Certificate holder</p><h2 class="h4"><?= Security::escape($certificate['learner_name']) ?></h2><p class="mb-1 text-secondary">Course completed</p><h3 class="h5"><?= Security::escape($certificate['course_title']) ?></h3><p class="text-secondary mt-3">Issued <?= Security::escape(date('d F Y', strtotime($certificate['issued_at']))) ?></p><code><?= Security::escape($certificate['verification_code']) ?></code><?php else: ?><div class="text-danger"><i class="bi bi-x-circle fs-1"></i></div><h1 class="h3 mt-3">Certificate not found</h1><p class="text-secondary">This verification code is invalid or the certificate is no longer available.</p><?php endif; ?><div class="mt-4"><a href="index.php" class="btn btn-outline-primary">SmartStudyPro home</a></div></main></body></html>
