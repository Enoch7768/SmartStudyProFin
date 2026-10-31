<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Learning\RecognitionService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('../login.php?redirect=admin%2Fanalytics.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('You do not have permission to view learning analytics.');
}
$data = (new RecognitionService($pdo))->analytics();
$summary = $data['summary'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Learning analytics | SmartStudyPro</title>
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="../assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--orange:#FF7A00;--bg:#F5F6FB;--line:#E2E8F0;--muted:#64748B}body{background:var(--bg);color:#1E293B;font-family:"Plus Jakarta Sans",sans-serif}.panel,.metric{background:#fff;border:1px solid var(--line);border-radius:15px;padding:21px}.metric strong{font:700 27px Outfit,sans-serif;color:var(--navy)}.muted{color:var(--muted);font-size:13px}.table{vertical-align:middle}</style>
</head>
<body><main class="container py-5"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="small text-uppercase fw-bold mb-1" style="color:var(--orange)">Administrator</div><h1 class="h2 mb-1">Learning analytics</h1><p class="muted mb-0">Aggregate learning activity and course outcomes.</p></div><div class="d-flex gap-2"><a href="cms.php" class="btn btn-outline-primary">CMS</a><a href="orders.php" class="btn btn-outline-secondary">Orders</a></div></div>
<div class="row g-3 mb-4"><?php foreach ([['Active students',(int)$summary['students']],['Enrolments',(int)$summary['enrolments']],['Course completions',(int)$summary['completions']],['Certificates',(int)$summary['certificates']],['Quiz attempts',(int)$summary['quiz_attempts']],['Average quiz score',number_format((float)$summary['average_score'],1).'%'],['Paid revenue','UGX '.number_format((int)$summary['revenue_ugx'])]] as [$label,$value]): ?><div class="col-6 col-lg-3"><section class="metric h-100"><div class="muted"><?= Security::escape($label) ?></div><strong><?= Security::escape($value) ?></strong></section></div><?php endforeach; ?></div>
<div class="row g-4"><div class="col-lg-7"><section class="panel h-100"><h2 class="h5 mb-3">Course performance</h2><div class="table-responsive"><table class="table"><thead><tr><th>Course</th><th>Enrolled</th><th>Completed</th><th>Completion</th></tr></thead><tbody><?php foreach ($data['courses'] as $course): $rate=(int)$course['enrolled']>0?round((int)$course['completed']/(int)$course['enrolled']*100):0; ?><tr><td><?= Security::escape($course['title']) ?></td><td><?= (int)$course['enrolled'] ?></td><td><?= (int)$course['completed'] ?></td><td><?= $rate ?>%</td></tr><?php endforeach; ?><?php if (!$data['courses']): ?><tr><td colspan="4" class="text-secondary">No published course activity yet.</td></tr><?php endif; ?></tbody></table></div></section></div>
<div class="col-lg-5"><section class="panel h-100"><h2 class="h5 mb-3">Recent completions</h2><?php foreach ($data['recent_completions'] as $item): ?><div class="border-bottom py-2"><strong><?= Security::escape($item['name']) ?></strong><div class="muted"><?= Security::escape($item['course_title']) ?> · <?= Security::escape(date('d M Y', strtotime($item['completed_at']))) ?></div></div><?php endforeach; ?><?php if (!$data['recent_completions']): ?><p class="muted mb-0">Course completions will appear here.</p><?php endif; ?></section></div>
<div class="col-12"><section class="panel"><h2 class="h5 mb-3">Monthly enrolments</h2><div class="d-flex flex-wrap gap-3"><?php foreach ($data['monthly'] as $month): ?><div class="border rounded-3 px-3 py-2"><div class="muted"><?= Security::escape($month['month']) ?></div><strong><?= (int)$month['enrolments'] ?></strong></div><?php endforeach; ?><?php if (!$data['monthly']): ?><p class="muted mb-0">Monthly enrolment data will appear here.</p><?php endif; ?></div></section></div></div></main></body></html>
