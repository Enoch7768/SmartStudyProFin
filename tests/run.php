<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed in this PHP runtime.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Identity\AccountService;
use App\Core\Config;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Migrator;
use App\Cms\ContentRepository;
use App\Cms\PublicContent;

$temporary = sys_get_temp_dir() . '/ssp-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create test storage.');
try {
    $pdo = Database::connect($temporary . '/test.sqlite');
    $migrationPath = dirname(__DIR__) . '/database/migrations';
    if (Migrator::run($pdo, $migrationPath) !== ['001_identity', '002_cms_content', '003_student_identity', '004_learning_engine', '005_lesson_resources_quiz_settings']) throw new RuntimeException('Foundation and CMS migrations did not run.');
    if (Migrator::run($pdo, $migrationPath) !== []) throw new RuntimeException('Migrations are not idempotent.');
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, :created_at, :updated_at)');
    $insert->execute(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password_hash' => password_hash('a-strong-test-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $userId = (int) $pdo->lastInsertId();
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'administrator'")->fetchColumn();
    $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (:user_id, :role_id, :assigned_at)');
    $assign->execute(['user_id' => $userId, 'role_id' => $roleId, 'assigned_at' => $now]);
    if (!Auth::hasRole($pdo, $userId, 'administrator')) throw new RuntimeException('Role assignment failed.');
    if (!Auth::hasPermission($pdo, $userId, 'manage_users')) throw new RuntimeException('Permission assignment failed.');
    $content = new ContentRepository($pdo);
    $courseId = $content->save('courses', null, ['title'=>'Foundations of Science','description_html'=>'Intro','content_html'=>'Course body','price_ugx'=>'25000','category'=>'Science','status'=>'published','sort_order'=>'1']);
    $course = $content->find('courses', $courseId);
    if (!$course || $course['slug'] !== 'foundations-of-science' || (int) $course['price_ugx'] !== 25000) throw new RuntimeException('Course creation or slug generation failed.');
    $chapterId = $content->save('chapters', null, ['course_id'=>$courseId,'title'=>'Introduction','sort_order'=>'1']);
    $lessonId = $content->save('lessons', null, ['chapter_id'=>$chapterId,'title'=>'First lesson','content_html'=>'Lesson text','video_url'=>'','sort_order'=>'1','status'=>'published']);
    $questionId = $content->save('questions', null, ['lesson_id'=>$lessonId,'content'=>'Lesson quiz','question'=>'What is science?','options_json'=>['Study of nature','A sport'],'correct_answer'=>'Study of nature','sort_order'=>'1']);
    if (!$content->find('questions', $questionId)) throw new RuntimeException('Quiz question creation failed.');
    $pdo->exec("UPDATE cms_courses SET price_ugx = 0 WHERE id = " . $courseId);
    $learning = new App\Learning\LearningService($pdo);
    if ($learning->hasAccess($userId, $courseId)) throw new RuntimeException('A student gained access before enrolment.');
    $learning->enrolFreeCourse($userId, $courseId);
    if (!$learning->hasAccess($userId, $courseId)) throw new RuntimeException('Free course enrolment did not grant access.');
    $outline = $learning->outline($userId, $courseId);
    if (count($outline) !== 1 || count($outline[0]['lessons']) !== 1) throw new RuntimeException('Learning course outline did not load.');
    $assetInsert = $pdo->prepare("INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES (:path, 'lesson.pdf', 'application/pdf', 10, 'test-checksum', 'Lesson PDF', 'private', :user_id, :created_at)');
    $assetInsert->execute(['path' => 'media/test/lesson.pdf', 'user_id' => $userId, 'created_at' => $now]);
    $resourceId = (int) $pdo->lastInsertId();
    $content->save('lessons', $lessonId, ['resource_media_id' => $resourceId, 'resource_title' => 'Science notes', 'quiz_time_limit_minutes' => 5, 'quiz_pass_mark' => 75]);
    if (!$learning->media($userId, $resourceId)) throw new RuntimeException('An enrolled student could not resolve their private lesson resource.');
    $insert->execute(['name' => 'Outside Student', 'email' => 'outside@example.test', 'password_hash' => password_hash('another-strong-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $outsideUserId = (int) $pdo->lastInsertId();
    if ($learning->hasAccess($outsideUserId, $courseId) || $learning->media($outsideUserId, $resourceId) !== null) throw new RuntimeException('A different student accessed another student course resource.');
    $learning->startLesson($userId, $courseId, $lessonId);
    $learning->updatePosition($userId, $courseId, $lessonId, 95);
    $learning->toggleBookmark($userId, $courseId, $lessonId);
    if (count($learning->dashboard($userId)['bookmarks']) !== 1) throw new RuntimeException('Lesson bookmark did not persist.');
    $quizData = $learning->quizQuestions($userId, $courseId, $lessonId);
    App\Core\Session::start();
    $quizTimerKey = 'quiz_started_' . $lessonId;
    $_SESSION[$quizTimerKey] = time() - 400;
    $expiredQuizRejected = false;
    try { $learning->submitQuiz($userId, $courseId, $lessonId, []); } catch (RuntimeException) { $expiredQuizRejected = true; }
    if (!$expiredQuizRejected) throw new RuntimeException('An expired timed quiz was accepted.');
    $quizData = $learning->quizQuestions($userId, $courseId, $lessonId);
    $submittedAnswers = [];
    $submittedAnswers[$questionId] = 'Study of nature';
    $quizResult = $learning->submitQuiz($userId, $courseId, $lessonId, $submittedAnswers);
    if (count($learning->attempts($userId, $lessonId)) !== 1) throw new RuntimeException('Quiz attempt history was not saved.');
    $learning->completeLesson($userId, $courseId, $lessonId);
    if ($learning->outline($userId, $courseId)[0]['lessons'][0]['progress_status'] !== 'completed') throw new RuntimeException('Lesson completion did not persist.');
    $pdo->exec("UPDATE cms_courses SET price_ugx = 25000 WHERE id = " . $courseId);
    $paidCourseRejected = false;
    try { $learning->enrolFreeCourse($userId, $courseId); } catch (RuntimeException) { $paidCourseRejected = true; }
    if (!$paidCourseRejected) throw new RuntimeException('A paid course was incorrectly enrolled as free.');

    $productId = $content->save('products', null, ['title'=>'Study Guide','description_html'=>'Guide','price_ugx'=>'10000','category'=>'Books','product_type'=>'physical','status'=>'published']);
    if (!$content->find('products', $productId)) throw new RuntimeException('Product creation failed.');
    $public = new PublicContent($pdo);
    if (count($public->courses()) !== 1 || ($public->course((string) $courseId)['Title'] ?? '') !== 'Foundations of Science' || ($public->course('foundations-of-science')['Title'] ?? '') !== 'Foundations of Science') throw new RuntimeException('Published course public read mapping failed.');
    if (($public->product((string) $productId)['ProductType'] ?? '') !== 'Physical' || count($public->products()) !== 1) throw new RuntimeException('Published product public read mapping failed.');

    $content->savePage('home', ['title'=>'Learn with purpose','subtitle'=>'SmartStudyPro','body_html'=>'Home content','status'=>'published']);
    if (($content->page('home')['title'] ?? '') !== 'Learn with purpose') throw new RuntimeException('CMS page editing failed.');
    $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala']);
    if (($content->settings()['contact.phone'] ?? '') !== '+256700000000') throw new RuntimeException('CMS settings editing failed.');
    $content->delete('courses', $courseId);
    if ((int) $pdo->query('SELECT COUNT(*) FROM cms_chapters')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM cms_lessons')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM cms_quiz_questions')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM course_enrolments')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM lesson_progress')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM lesson_bookmarks')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM quiz_attempts')->fetchColumn() !== 0) throw new RuntimeException('Course deletion did not cascade to learning records.');

    for ($attempt = 0; $attempt < 8; $attempt++) {
        if (Auth::attempt($pdo, 'admin@example.test', 'incorrect-password')) throw new RuntimeException('Incorrect password was accepted.');
    }
    if (Auth::attempt($pdo, 'admin@example.test', 'a-strong-test-password')) throw new RuntimeException('Login throttle did not activate.');
    $pdo->exec('DELETE FROM login_attempts');
    if (!Auth::attempt($pdo, 'admin@example.test', 'a-strong-test-password')) throw new RuntimeException('Valid login was rejected.');
    Auth::logout();

    $accounts = new AccountService($pdo);
    $studentId = $accounts->register('Test Student', 'student@example.test', 'a-strong-student-password');
    if (!Auth::hasRole($pdo, $studentId, 'student') || Auth::hasRole($pdo, $studentId, 'administrator')) throw new RuntimeException('Student registration assigned an incorrect role.');
    $studentProfile = $accounts->profile($studentId);
    if (($studentProfile['email'] ?? '') !== 'student@example.test') throw new RuntimeException('Student profile retrieval failed.');
    $accounts->updateProfile($studentId, 'Updated Student', '+256700000000', 'Kampala', 'Learning every day', 'dark');
    $studentProfile = $accounts->profile($studentId);
    if (($studentProfile['name'] ?? '') !== 'Updated Student' || ($studentProfile['preferred_theme'] ?? '') !== 'dark') throw new RuntimeException('Student profile update failed.');
    $accounts->changePassword($studentId, 'a-strong-student-password', 'a-new-strong-student-password');
    if (!Auth::attempt($pdo, 'student@example.test', 'a-new-strong-student-password')) throw new RuntimeException('Password change did not update the credential.');
    $accounts->changePassword($studentId, 'a-new-strong-student-password', 'a-third-strong-student-password');
    if (Auth::user($pdo) !== null) throw new RuntimeException('A password change did not invalidate the previous session.');
    if (!Auth::attempt($pdo, 'student@example.test', 'a-third-strong-student-password')) throw new RuntimeException('A new login after session invalidation failed.');
    Auth::logout();
    $resetToken = $accounts->createResetToken('student@example.test');
    if (!is_string($resetToken) || strlen($resetToken) !== 64) throw new RuntimeException('Password reset token creation failed.');
    if (!$accounts->resetPassword($resetToken, 'a-reset-strong-student-password')) throw new RuntimeException('Valid password reset token was rejected.');
    if ($accounts->resetPassword($resetToken, 'another-reset-strong-password')) throw new RuntimeException('Password reset token was reusable.');
    if (!Auth::attempt($pdo, 'student@example.test', 'a-reset-strong-student-password')) throw new RuntimeException('Password reset did not update the credential.');
    Auth::logout();
    if (!$accounts->allowResetRequest('student@example.test', '127.0.0.1') || !$accounts->allowResetRequest('student@example.test', '127.0.0.1') || !$accounts->allowResetRequest('student@example.test', '127.0.0.1') || $accounts->allowResetRequest('student@example.test', '127.0.0.1')) throw new RuntimeException('Password reset request throttling failed.');
    $duplicateRejected = false;
    try {
        $insert->execute(['name' => 'Duplicate', 'email' => 'ADMIN@example.test', 'password_hash' => null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    } catch (PDOException) {
        $duplicateRejected = true;
    }
    if (!$duplicateRejected) throw new RuntimeException('Case-insensitive email uniqueness failed.');
    $pdo->exec("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES ($userId, 'test-hash', '2099-01-01 00:00:00', '$now')");
    $pdo->exec('DELETE FROM users WHERE id = ' . $userId);
    if ((int) $pdo->query("SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash = 'test-hash'")->fetchColumn() !== 0) throw new RuntimeException('Foreign-key cascade failed.');
    $root = dirname(__DIR__);
    $privateStorage = $temporary . '/private';
    putenv('APP_STORAGE_PATH=' . $privateStorage);
    putenv('DB_PATH=' . $privateStorage . '/installed.sqlite');
    Config::load($root);
    if (!Installer::storageIsPrivate($root) || !Installer::databaseIsPrivate()) throw new RuntimeException('Installer accepted an unsafe storage path.');
    Installer::install($root, 'Fresh Administrator', 'fresh-admin@example.test', 'a-very-strong-install-password');
    if (!Installer::isInstalled()) throw new RuntimeException('Installer did not create its lock.');
    Config::load($root);
    if (!Installer::applicationKeyIsValid()) throw new RuntimeException('The generated application key was not loaded.');
    if ((fileperms($privateStorage . '/app.key') & 0777) !== 0600 || (fileperms($privateStorage . '/installed.lock') & 0777) !== 0600) throw new RuntimeException('Private key and install lock permissions are unsafe.');
    putenv('DB_PATH=' . $temporary . '/outside.sqlite');
    Config::load($root);
    if (Installer::databaseIsPrivate()) throw new RuntimeException('Installer accepted a database path outside private storage.');
    putenv('DB_PATH=' . $privateStorage . '/installed.sqlite');
    Config::load($root);
    $installedDb = Database::connect();
    $admin = $installedDb->query("SELECT id, password_hash FROM users WHERE email = 'fresh-admin@example.test'")->fetch();
    if (!$admin || !password_verify('a-very-strong-install-password', $admin['password_hash'])) throw new RuntimeException('Installer did not create the administrator securely.');
    if (!Auth::hasRole($installedDb, (int) $admin['id'], 'administrator')) throw new RuntimeException('Installer did not assign the administrator role.');
    if (strlen(trim((string) file_get_contents($privateStorage . '/app.key'))) !== 64) throw new RuntimeException('Installer did not generate a valid application key.');
    $reinstallRejected = false;
    try {
        Installer::install($root, 'Second Admin', 'second-admin@example.test', 'another-strong-password');
    } catch (RuntimeException) {
        $reinstallRejected = true;
    }
    if (!$reinstallRejected) throw new RuntimeException('Installer allowed a second first-run installation.');
    echo "PASS SQLite migrations and idempotency\nPASS role and permission assignment\nPASS login throttling and valid authentication\nPASS case-insensitive email uniqueness\nPASS foreign-key cascade\nPASS private first-run installation and lock\nPASS native CMS content, quiz, page and settings CRUD\nPASS CMS relational cascade integrity\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
        if (is_dir($file)) {
            foreach (glob($file . '/*') ?: [] as $child) if (is_file($child)) unlink($child);
            rmdir($file);
        }
    }
    rmdir($temporary);
}
