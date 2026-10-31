<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Cms\ContentRepository;
use App\Core\Database;
use App\Core\Migrator;
use App\Learning\RecognitionService;

$temporary = sys_get_temp_dir() . '/ssp-recognition-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create test storage.');

try {
    $pdo = Database::connect($temporary . '/recognition.sqlite');
    $ran = Migrator::run($pdo, dirname(__DIR__) . '/database/migrations');
    if (!in_array('008_notifications_recognition_analytics', $ran, true)) throw new RuntimeException('The phase 7 migration did not run.');
    if (Migrator::run($pdo, dirname(__DIR__) . '/database/migrations') !== []) throw new RuntimeException('Phase 7 migrations are not idempotent.');

    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, :created_at, :updated_at)');
    $insert->execute(['name' => 'Recognition Student', 'email' => 'recognition@example.test', 'password_hash' => password_hash('a-strong-test-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $insert->execute(['name' => 'Other Student', 'email' => 'other-recognition@example.test', 'password_hash' => password_hash('another-strong-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $otherId = (int) $pdo->lastInsertId();
    $studentRoleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'student'")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (:user_id, :role_id, :assigned_at)')->execute(['user_id' => $studentId, 'role_id' => $studentRoleId, 'assigned_at' => $now]);

    $content = new ContentRepository($pdo);
    $courseId = $content->save('courses', null, ['title' => 'Applied Mathematics', 'description_html' => 'Mathematics', 'content_html' => '', 'price_ugx' => '0', 'category' => 'Science', 'status' => 'published', 'sort_order' => '1']);
    $chapterId = $content->save('chapters', null, ['course_id' => $courseId, 'title' => 'Algebra', 'sort_order' => '1']);
    $lessonId = $content->save('lessons', null, ['chapter_id' => $chapterId, 'title' => 'Linear equations', 'content_html' => 'Solve linear equations.', 'video_url' => '', 'sort_order' => '1', 'status' => 'published']);
    $pdo->prepare("INSERT INTO course_enrolments (user_id, course_id, status, source, enrolled_at, updated_at) VALUES (:user_id, :course_id, 'completed', 'free', :enrolled_at, :updated_at)")->execute(['user_id' => $studentId, 'course_id' => $courseId, 'enrolled_at' => $now, 'updated_at' => $now]);
    $pdo->prepare("INSERT INTO lesson_progress (user_id, course_id, lesson_id, status, started_at, completed_at, updated_at) VALUES (:user_id, :course_id, :lesson_id, 'completed', :started_at, :completed_at, :updated_at)")->execute(['user_id' => $studentId, 'course_id' => $courseId, 'lesson_id' => $lessonId, 'started_at' => $now, 'completed_at' => $now, 'updated_at' => $now]);
    $pdo->prepare("INSERT INTO quiz_attempts (user_id, course_id, lesson_id, answers_json, question_count, correct_count, score_percent, passed, started_at, submitted_at) VALUES (:user_id, :course_id, :lesson_id, '{}', 1, 1, 100, 1, :started_at, :submitted_at)")->execute(['user_id' => $studentId, 'course_id' => $courseId, 'lesson_id' => $lessonId, 'started_at' => $now, 'submitted_at' => $now]);

    $service = new RecognitionService($pdo);
    $service->sync($studentId);
    $service->sync($studentId);
    if (count($service->achievements($studentId)) !== 6) throw new RuntimeException('The achievement catalogue is incomplete.');
    $earned = array_values(array_filter($service->achievements($studentId), static fn (array $item): bool => $item['earned_at'] !== null));
    if (count($earned) !== 3) throw new RuntimeException('Earned achievements did not match the actual learning activity.');
    $certificates = $service->certificates($studentId);
    if (count($certificates) !== 1 || !preg_match('/^[A-F0-9]{24}$/', $certificates[0]['verification_code'])) throw new RuntimeException('Course completion did not issue a unique certificate.');
    $verified = $service->verifyCertificate($certificates[0]['verification_code']);
    if (!$verified || $verified['learner_name'] !== 'Recognition Student' || $verified['course_title'] !== 'Applied Mathematics') throw new RuntimeException('Public certificate verification returned incorrect details.');
    if ($service->verifyCertificate('invalid-code') !== null) throw new RuntimeException('An invalid certificate code was accepted.');
    if ($service->certificates($otherId)) throw new RuntimeException('A student accessed another student certificates.');

    $notifications = $service->notifications($studentId);
    if (count($notifications) !== 5) throw new RuntimeException('Achievement and certificate notifications were not created exactly once.');
    $service->sync($studentId);
    if (count($service->notifications($studentId)) !== 5) throw new RuntimeException('Repeated synchronization duplicated notifications.');
    if ($service->unreadCount($studentId) !== 5) throw new RuntimeException('Unread notification count is incorrect.');
    $service->markRead($otherId, (int) $notifications[0]['id']);
    if ($service->unreadCount($studentId) !== 5) throw new RuntimeException('A different user changed another user notification.');
    $service->markRead($studentId, (int) $notifications[0]['id']);
    if ($service->unreadCount($studentId) !== 4) throw new RuntimeException('Mark-read did not update the owned notification.');
    $service->markAllRead($studentId);
    if ($service->unreadCount($studentId) !== 0) throw new RuntimeException('Mark-all-read did not clear unread notifications.');

    $analytics = $service->analytics();
    if ((int) $analytics['summary']['students'] !== 1 || (int) $analytics['summary']['enrolments'] !== 1 || (int) $analytics['summary']['completions'] !== 1 || (int) $analytics['summary']['certificates'] !== 1) throw new RuntimeException('Aggregate learning analytics are inconsistent.');
    echo "PASS phase 7 migration and idempotency\nPASS activity-based achievements\nPASS unique certificate issue and verification\nPASS private certificate and notification ownership\nPASS idempotent notifications and read state\nPASS aggregate learning analytics\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    @rmdir($temporary);
}
