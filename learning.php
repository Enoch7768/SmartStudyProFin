<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Learning\LearningService;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=learning.php');
$dashboard = (new LearningService($pdo))->dashboard((int) $user['id']);
$completedCourses = count(array_filter($dashboard['enrolled'], static fn (array $course): bool => $course['enrolment_status'] === 'completed'));
$totalCompletedLessons = array_sum(array_map(static fn (array $course): int => (int) $course['completed_lessons'], $dashboard['enrolled']));
$phone = '+256 704 416250';
$emailContact = 'smartstudypro36@gmail.com';
$address = 'Kampala, Uganda';
$seoTitle = 'My Learning | SmartStudyPro';
$seoDescription = 'Continue your courses, review progress, bookmarks and quiz results.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= Security::escape($seoTitle) ?></title>
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet">
<style>:root{--navy:#0C086B;--deep:#070443;--orange:#FF7A00;--bg:#F5F6FB;--card:#fff;--ink:#1E293B;--muted:#64748B;--line:#E2E8F0}body{background:var(--bg);color:var(--ink);font-family:"Plus Jakarta Sans",sans-serif}h1,h2,h3,h4{font-family:Outfit,sans-serif}.welcome{background:linear-gradient(120deg,var(--deep),var(--navy));color:#fff;border-radius:20px;padding:34px}.metric,.learning-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:21px;height:100%}.metric strong{font:700 28px Outfit,sans-serif;color:var(--navy)}.eyebrow{font-size:11px;letter-spacing:1.2px;text-transform:uppercase;font-weight:800;color:var(--orange)}.course-card{border:1px solid var(--line);border-radius:14px;padding:18px;background:#fff}.btn-main{background:var(--orange);border:0;border-radius:9px;padding:10px 15px;color:#fff;font-weight:700;text-decoration:none;display:inline-block}.muted{color:var(--muted);font-size:13px}.progress{height:7px;background:#e9eaf2}.progress-bar{background:var(--orange)}</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main class="container py-5">
<section class="welcome mb-4"><div class="eyebrow">Your learning space</div><h1 class="h2 mb-2">Keep your momentum, <?= Security::escape($user['name']) ?>.</h1><p class="mb-0 text-white-50">Pick up where you left off and keep moving towards your learning goals.</p></section>
<div class="row g-3 mb-5"><div class="col-md-4"><div class="metric"><div class="muted">Enrolled courses</div><strong><?= count($dashboard['enrolled']) ?></strong></div></div><div class="col-md-4"><div class="metric"><div class="muted">Lessons completed</div><strong><?= $totalCompletedLessons ?></strong></div></div><div class="col-md-4"><div class="metric"><div class="muted">Courses completed</div><strong><?= $completedCourses ?></strong></div></div></div>
<div class="d-flex justify-content-between align-items-end mb-3"><div><div class="eyebrow">Your classroom</div><h2 class="h3 mb-0">My courses</h2></div><a href="courses.php" class="fw-bold text-decoration-none">Explore courses <i class="bi bi-arrow-right"></i></a></div>
<div class="row g-3 mb-5">
<?php foreach ($dashboard['enrolled'] as $course): $percent=(int)$course['lesson_count']>0?(int)round((int)$course['completed_lessons']/(int)$course['lesson_count']*100):0; ?>
<div class="col-lg-6"><article class="course-card h-100"><div class="d-flex justify-content-between gap-3"><div><h3 class="h5 mb-1"><?= Security::escape($course['title']) ?></h3><p class="muted mb-2"><?= (int)$course['lesson_count'] ?> lessons · <?= (int)$course['chapter_count'] ?> chapters</p></div><span class="badge rounded-pill text-bg-light align-self-start"><?= $course['enrolment_status']==='completed'?'Completed':'In progress' ?></span></div><div class="d-flex justify-content-between small mb-2"><span>Course progress</span><strong><?= $percent ?>%</strong></div><div class="progress mb-3"><div class="progress-bar" style="width:<?= $percent ?>%"></div></div><a class="btn-main" href="study.php?course_id=<?= (int)$course['id'] ?>">Continue learning</a></article></div>
<?php endforeach; ?>
<?php if (!$dashboard['enrolled']): ?><div class="col-12"><div class="learning-card text-center"><h3 class="h5">Your learning starts here</h3><p class="muted">Browse the course catalogue and enrol in an available free course. Paid course access becomes available after payment verification.</p><a class="btn-main" href="courses.php">Explore courses</a></div></div><?php endif; ?>
</div>
<?php $available = array_values(array_filter($dashboard['courses'], static fn (array $course): bool => !in_array($course['enrolment_status'], ['active', 'completed'], true))); ?>
<?php if ($available): ?><div class="d-flex justify-content-between align-items-end mb-3"><div><div class="eyebrow">Discover</div><h2 class="h3 mb-0">Available courses</h2></div></div><div class="row g-3 mb-5"><?php foreach ($available as $course): ?><div class="col-lg-6"><article class="course-card h-100"><h3 class="h5"><?= Security::escape($course['title']) ?></h3><p class="muted"><?= Security::escape(strip_tags($course['description_html'])) ?></p><div class="d-flex justify-content-between align-items-center"><strong><?= (int)$course['price_ugx']===0?'Free':'UGX '.number_format((int)$course['price_ugx']) ?></strong><?php if ((int)$course['price_ugx']===0): ?><a class="btn-main" href="study.php?course_id=<?= (int)$course['id'] ?>">Enrol and start</a><?php else: ?><a class="btn-main" href="course-details.php?id=<?= (int)$course['id'] ?>">View course</a><?php endif; ?></div></article></div><?php endforeach; ?></div><?php endif; ?>
<div class="row g-4">
<div class="col-lg-6"><section class="learning-card"><div class="eyebrow">Pick up again</div><h2 class="h4 mb-3">Recently studied</h2><?php foreach($dashboard['recent'] as $item): ?><div class="d-flex justify-content-between align-items-center gap-2 border-bottom py-2"><div><strong><?= Security::escape($item['lesson_title']) ?></strong><div class="muted"><?= Security::escape($item['course_title']) ?></div></div><a class="fw-bold text-decoration-none" href="study.php?course_id=<?= (int)$item['course_id'] ?>&lesson_id=<?= (int)$item['lesson_id'] ?>">Resume</a></div><?php endforeach; ?><?php if(!$dashboard['recent']): ?><p class="muted mb-0">Lessons you start will appear here.</p><?php endif; ?></section></div>
<div class="col-lg-6"><section class="learning-card"><div class="eyebrow">Saved lessons</div><h2 class="h4 mb-3">Bookmarks</h2><?php foreach($dashboard['bookmarks'] as $item): ?><div class="d-flex justify-content-between align-items-center gap-2 border-bottom py-2"><div><strong><?= Security::escape($item['lesson_title']) ?></strong><div class="muted"><?= Security::escape($item['course_title']) ?></div></div><a class="fw-bold text-decoration-none" href="study.php?course_id=<?= (int)$item['course_id'] ?>&lesson_id=<?= (int)$item['lesson_id'] ?>">Open</a></div><?php endforeach; ?><?php if(!$dashboard['bookmarks']): ?><p class="muted mb-0">Bookmark a lesson to save it for later.</p><?php endif; ?></section></div>
<div class="col-12"><section class="learning-card"><div class="eyebrow">Assessment history</div><h2 class="h4 mb-3">Recent quiz attempts</h2><?php foreach($dashboard['attempts'] as $attempt): ?><div class="d-flex flex-wrap justify-content-between gap-2 border-bottom py-2"><span><strong><?= Security::escape($attempt['lesson_title']) ?></strong><span class="muted"> · <?= Security::escape($attempt['course_title']) ?> · <?= Security::escape(date('d M Y',strtotime($attempt['submitted_at']))) ?></span></span><strong><?= (int)$attempt['score_percent'] ?>% · <?= $attempt['passed']?'Passed':'Retry' ?></strong></div><?php endforeach; ?><?php if(!$dashboard['attempts']): ?><p class="muted mb-0">Your graded quizzes will appear here.</p><?php endif; ?></section></div>
</div>
</main><script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script><script src="assets/js/main.js"></script>
</body></html>
