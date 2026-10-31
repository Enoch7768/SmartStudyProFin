<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Learning\LearningService;

header('Cache-Control: private, no-store');
$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=study.php');
$userId = (int) $user['id'];
$service = new LearningService($pdo);
$courseId = filter_var($_GET['course_id'] ?? $_POST['course_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$lessonId = filter_var($_GET['lesson_id'] ?? $_POST['lesson_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$course = $service->course($courseId);
$error = null;
$notice = null;
$result = null;

if (!$course) {
    http_response_code(404);
    $error = 'This course could not be found or is not currently published.';
}

if ($course && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'enrol') {
                $service->enrolFreeCourse($userId, $courseId);
                Security::redirect('study.php?course_id=' . $courseId);
            } elseif ($action === 'complete') {
                $service->completeLesson($userId, $courseId, $lessonId);
                $notice = 'Lesson marked as complete. Your progress has been saved.';
            } elseif ($action === 'bookmark') {
                $bookmarked = $service->toggleBookmark($userId, $courseId, $lessonId);
                $notice = $bookmarked ? 'Lesson added to your bookmarks.' : 'Lesson removed from your bookmarks.';
            } elseif ($action === 'position') {
                $service->updatePosition($userId, $courseId, $lessonId, (int) ($_POST['position'] ?? 0));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            } elseif ($action === 'quiz') {
                $result = $service->submitQuiz($userId, $courseId, $lessonId, is_array($_POST['answers'] ?? null) ? $_POST['answers'] : []);
                $notice = 'Your quiz has been graded and the attempt saved.';
            } else {
                throw new RuntimeException('Unknown learning action.');
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$hasAccess = $course && $service->hasAccess($userId, $courseId);
$outline = $hasAccess ? $service->outline($userId, $courseId) : [];
$lessons = [];
foreach ($outline as $chapter) foreach ($chapter['lessons'] as $lesson) $lessons[] = $lesson;
if ($hasAccess && $lessonId === 0 && $lessons) {
    $next = current(array_filter($lessons, static fn (array $lesson): bool => $lesson['progress_status'] !== 'completed'));
    $lessonId = (int) ($next['id'] ?? $lessons[0]['id']);
}
$lesson = $hasAccess && $lessonId > 0 ? $service->lesson($userId, $courseId, $lessonId) : null;
if ($lesson && !$error) $service->startLesson($userId, $courseId, $lessonId);
$quiz = null;
$attempts = [];
if ($lesson) {
    try {
        $quiz = $service->quizQuestions($userId, $courseId, $lessonId);
        $attempts = $service->attempts($userId, $lessonId);
        if ($result) {
            App\Core\Session::start();
            unset($_SESSION['quiz_started_' . $lessonId]);
        }
    } catch (Throwable $exception) {
        $error = $error ?: $exception->getMessage();
    }
}
if ($quiz && count($quiz['questions']) > 1) shuffle($quiz['questions']);
$completedCount = count(array_filter($lessons, static fn (array $item): bool => $item['progress_status'] === 'completed'));
$totalLessons = count($lessons);
$progressPercent = $totalLessons > 0 ? (int) round($completedCount / $totalLessons * 100) : 0;
$seoTitle = ($course['title'] ?? 'Study Portal') . ' | SmartStudyPro';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= Security::escape($seoTitle) ?></title>
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--navy:#0C086B;--deep:#070443;--orange:#FF7A00;--bg:#F5F6FB;--card:#fff;--ink:#1E293B;--muted:#64748B;--line:#E2E8F0}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px "Plus Jakarta Sans",sans-serif}h1,h2,h3,h4{font-family:Outfit,sans-serif}.topbar{height:70px;background:var(--card);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:sticky;top:0;z-index:5}.layout{display:grid;grid-template-columns:340px minmax(0,1fr);min-height:calc(100vh - 70px)}.sidebar{background:var(--card);border-right:1px solid var(--line);padding:22px 0;overflow:auto;max-height:calc(100vh - 70px);position:sticky;top:70px}.chapter{padding:13px 22px;background:#f0f1fa;color:var(--navy);font:700 14px Outfit,sans-serif}.lesson-link{display:flex;align-items:center;gap:10px;padding:13px 22px;text-decoration:none;color:var(--ink);border-left:4px solid transparent;border-bottom:1px solid #f0f1f5}.lesson-link.active{background:#fff5eb;color:#b95500;border-left-color:var(--orange);font-weight:700}.content{padding:32px;min-width:0}.panel{background:var(--card);border:1px solid var(--line);border-radius:17px;padding:24px;box-shadow:0 8px 25px rgba(7,4,67,.04)}.video{width:100%;max-height:70vh;background:#080808;border-radius:14px}.btn-main{background:var(--orange);border:0;border-radius:10px;padding:11px 18px;color:#fff;font-weight:700}.btn-outline{border:1px solid var(--line);border-radius:10px;padding:10px 15px;color:var(--navy);background:#fff;font-weight:700}.muted{color:var(--muted)}.progress{height:8px;background:#e9eaf2}.progress-bar{background:var(--orange)}.question{border:1px solid var(--line);border-radius:13px;padding:18px;margin:14px 0}.resource{display:inline-flex;gap:9px;align-items:center;padding:12px 15px;background:#fff5eb;border-radius:10px;color:#9a4700;text-decoration:none;font-weight:700}.timer{font-weight:800;color:#b54708}.review-item{border-bottom:1px solid var(--line);padding:12px 0}.review-item:last-child{border:0}@media(max-width:900px){.layout{grid-template-columns:1fr}.sidebar{position:static;max-height:350px;border-bottom:1px solid var(--line)}.content{padding:18px}.topbar{padding:0 14px}}
</style>
</head>
<body>
<header class="topbar"><div class="d-flex align-items-center gap-3"><a href="index.php"><img src="Smart_Study_Logo_Fin-removebg-preview.png" alt="SmartStudyPro" height="42"></a><span class="fw-bold d-none d-md-inline"><?= Security::escape($course['title'] ?? 'Study Portal') ?></span></div><div class="d-flex align-items-center gap-3"><a href="learning.php" class="text-decoration-none fw-semibold">My learning</a><a href="profile.php" class="text-decoration-none fw-semibold">Account</a></div></header>
<?php if ($error): ?><div class="container py-4"><div class="alert alert-danger rounded-3"><?= Security::escape($error) ?></div></div><?php endif; ?>
<?php if ($notice): ?><div class="container pt-3"><div class="alert alert-success rounded-3"><?= Security::escape($notice) ?></div></div><?php endif; ?>
<?php if (!$course): ?><main class="container py-5"><div class="panel text-center"><h1>Course unavailable</h1><p class="muted">The course may have been unpublished or removed.</p><a href="courses.php" class="btn-main text-decoration-none">Browse courses</a></div></main>
<?php elseif (!$hasAccess): ?><main class="container py-5"><div class="panel mx-auto text-center" style="max-width:650px"><div class="small text-uppercase fw-bold mb-2" style="color:var(--orange)">Your learning journey</div><h1><?= Security::escape($course['title']) ?></h1><p class="muted"><?= Security::escape(strip_tags($course['description_html'])) ?></p><?php if ((int)$course['price_ugx'] === 0): ?><p>This course is free. Enrol to unlock its lessons and resources.</p><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="action" value="enrol"><button class="btn-main">Enrol for free</button></form><?php else: ?><p>This course requires a confirmed purchase. Enrolment is activated after secure payment verification.</p><a href="course-details.php?id=<?= $courseId ?>" class="btn-main text-decoration-none">View course and purchase options</a><?php endif; ?></div></main>
<?php else: ?><div class="layout"><aside class="sidebar"><div class="px-4 pb-3"><div class="small muted mb-1">COURSE PROGRESS</div><div class="d-flex justify-content-between fw-bold mb-2"><span><?= $completedCount ?> of <?= $totalLessons ?> lessons</span><span><?= $progressPercent ?>%</span></div><div class="progress"><div class="progress-bar" style="width:<?= $progressPercent ?>%"></div></div></div>
<?php foreach ($outline as $chapter): ?><div class="chapter"><?= Security::escape($chapter['title']) ?></div><?php foreach ($chapter['lessons'] as $item): ?><a class="lesson-link <?= (int)$item['id']===$lessonId?'active':'' ?>" href="study.php?course_id=<?= $courseId ?>&lesson_id=<?= (int)$item['id'] ?>"><i class="bi <?= $item['progress_status']==='completed'?'bi-check-circle-fill text-success':'bi-play-circle' ?>"></i><span><?= Security::escape($item['title']) ?></span><?php if ($item['bookmarked']): ?><i class="bi bi-bookmark-fill text-warning"></i><?php endif; ?></a><?php endforeach; ?><?php endforeach; ?></aside>
<main class="content">
<?php if ($lesson): ?><div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><div class="small fw-bold text-uppercase mb-1" style="color:var(--orange)">Learning module</div><h1 class="h2 mb-1"><?= Security::escape($lesson['title']) ?></h1><p class="muted mb-0"><?= Security::escape($course['title']) ?></p></div><div class="d-flex gap-2"><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="lesson_id" value="<?= $lessonId ?>"><input type="hidden" name="action" value="bookmark"><button class="btn-outline" type="submit"><i class="bi bi-bookmark-plus"></i> Save</button></form><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="lesson_id" value="<?= $lessonId ?>"><input type="hidden" name="action" value="complete"><button class="btn-main" type="submit"><i class="bi bi-check2-circle me-1"></i>Mark complete</button></form></div></div>
<?php if (!empty($lesson['video_media_id'])): ?><div class="panel mb-4 p-2"><video class="video" controls preload="metadata" controlsList="nodownload" src="lesson-media.php?id=<?= (int)$lesson['video_media_id'] ?>"></video></div><?php elseif (!empty($lesson['video_url'])): ?><div class="panel mb-4"><a href="<?= Security::escape($lesson['video_url']) ?>" target="_blank" rel="noopener noreferrer" class="resource"><i class="bi bi-play-circle"></i> Open lesson video</a></div><?php endif; ?>
<?php if (!empty($lesson['resource_media_id'])): ?><div class="mb-4"><a class="resource" href="lesson-media.php?id=<?= (int)$lesson['resource_media_id'] ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-file-earmark-pdf"></i><?= Security::escape($lesson['resource_title'] ?: 'Open lesson resource') ?></a></div><?php endif; ?>
<section class="panel mb-4"><h2 class="h4 mb-3">Lesson notes</h2><div style="line-height:1.85"><?= $lesson['content_html'] !== '' ? $lesson['content_html'] : '<p class="muted">No lesson notes have been added yet.</p>' ?></div></section>
<?php if ($quiz && $quiz['questions']): ?><section class="panel mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><div class="small fw-bold text-uppercase" style="color:var(--orange)">Knowledge check</div><h2 class="h4 mb-0">Lesson quiz</h2><small class="muted">Pass mark: <?= (int)$quiz['pass_mark'] ?>%</small></div><?php if ($quiz['time_limit_minutes']>0 && !$result): ?><div class="timer" id="quizTimer" data-start="<?= (int)$quiz['started_at'] ?>" data-limit="<?= (int)$quiz['time_limit_minutes'] ?>">Timed quiz</div><?php endif; ?></div>
<?php if ($result): ?><div class="alert <?= $result['passed']?'alert-success':'alert-warning' ?> mt-3"><strong><?= $result['passed']?'Passed':'Keep practising' ?> · <?= $result['score'] ?>%</strong><div><?= $result['correct'] ?> of <?= $result['total'] ?> correct. Pass mark <?= $result['pass_mark'] ?>%.</div></div><?php foreach ($result['review'] as $item): ?><div class="review-item"><strong><?= Security::escape($item['question']) ?></strong><div class="small mt-1"><?= $item['is_correct']?'Correct':'Correct answer: '.Security::escape($item['correct_answer']) ?></div></div><?php endforeach; ?>
<?php else: ?><form method="post" id="quizForm"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="lesson_id" value="<?= $lessonId ?>"><input type="hidden" name="action" value="quiz"><?php foreach ($quiz['questions'] as $index=>$question): ?><div class="question"><p class="fw-bold mb-3"><?= $index+1 ?>. <?= Security::escape($question['question']) ?></p><?php foreach ($question['options'] as $option): ?><label class="d-flex align-items-center gap-2 mb-2"><input type="radio" name="answers[<?= (int)$question['id'] ?>]" value="<?= Security::escape($option) ?>"> <?= Security::escape($option) ?></label><?php endforeach; ?></div><?php endforeach; ?><button class="btn-main" type="submit">Submit and grade quiz</button></form><?php endif; ?>
<?php if ($attempts): ?><hr><h3 class="h6 fw-bold">Previous attempts</h3><?php foreach ($attempts as $attempt): ?><div class="d-flex justify-content-between border-bottom py-2 small"><span><?= Security::escape(date('d M Y H:i', strtotime($attempt['submitted_at']))) ?> · <?= $attempt['passed']?'Passed':'Not passed' ?></span><strong><?= (int)$attempt['score_percent'] ?>%</strong></div><?php endforeach; ?><?php endif; ?></section><?php endif; ?>
<?php else: ?><section class="panel text-center"><h2>No lesson selected</h2><p class="muted">Choose a lesson from the course outline.</p></section><?php endif; ?>
</main></div><?php endif; ?>
<script>
const timer=document.getElementById('quizTimer');
if(timer){const start=Number(timer.dataset.start),limit=Number(timer.dataset.limit)*60,form=document.getElementById('quizForm');const tick=()=>{const left=Math.max(0,limit-Math.floor(Date.now()/1000-start));timer.textContent='Time remaining: '+String(Math.floor(left/60)).padStart(2,'0')+':'+String(left%60).padStart(2,'0');if(left===0&&form){form.querySelectorAll('input,button').forEach(el=>el.disabled=true);timer.textContent='Time expired · reload to retry';}};tick();window.setInterval(tick,1000);}
const player=document.querySelector('video.video');
if(player){let last=-1;player.addEventListener('timeupdate',()=>{const second=Math.floor(player.currentTime);if(second>0&&second%10===0&&second!==last){last=second;const data=new FormData();data.append('_csrf','<?= Security::escape(Security::csrfToken()) ?>');data.append('action','position');data.append('course_id','<?= $courseId ?>');data.append('lesson_id','<?= $lessonId ?>');data.append('position',String(second));fetch(location.href,{method:'POST',body:data,credentials:'same-origin'}).catch(()=>{});}});}
</script>
</body>
</html>
