<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed in this PHP runtime.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Identity\AccountService;
use App\Identity\GoogleIdentityService;
use App\Identity\LegalAcceptanceService;
use App\Core\Config;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Migrator;
use App\Cms\ContentRepository;
use App\Cms\PublicContent;
use App\Cms\MediaManager;
use App\Commerce\CommerceService;
use App\Commerce\ShippingService;

$temporary = sys_get_temp_dir() . '/ssp-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create test storage.');
try {
    $pdo = Database::connect($temporary . '/test.sqlite');
    $migrationPath = dirname(__DIR__) . '/database/migrations';
    if (Migrator::run($pdo, $migrationPath) !== ['001_identity', '002_cms_content', '003_student_identity', '004_learning_engine', '005_lesson_resources_quiz_settings', '006_commerce_payments', '007_ai_learning_assistant', '008_notifications_recognition_analytics', '009_production_indexes', '010_legal_acceptance', '011_demo_payments', '012_commerce_delivery_refunds', '013_meeting_planner', '014_meeting_reminders', '015_contact_messages', '016_refund_item_allocations', '017_physical_shipping_fulfillment', '018_digital_read_download_entitlements']) throw new RuntimeException('Foundation and CMS migrations did not run.');
    if (Migrator::run($pdo, $migrationPath) !== []) throw new RuntimeException('Migrations are not idempotent.');
    $routeRules = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');
    $previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
    $_SERVER['SCRIPT_NAME'] = '/SmartStudyProFin/study.php';
    if (App\Core\Url::to('/courses/example-course') !== '/SmartStudyProFin/courses/example-course' || App\Core\Url::to('/assets/css/device-responsive.css') !== '/SmartStudyProFin/assets/css/device-responsive.css') throw new RuntimeException('Internal URLs do not preserve the installed subdirectory.');
    $_SERVER['SCRIPT_NAME'] = '\\SmartStudyProFin\\study.php';
    if (App\Core\Url::to('/courses') !== '/SmartStudyProFin/courses') throw new RuntimeException('Windows-style script paths are not normalized.');
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    if (App\Core\Url::to('/courses') !== '/courses' || App\Core\Url::to('/') !== '/') throw new RuntimeException('Root installation URLs are not canonical.');
    $_SERVER['SCRIPT_NAME'] = 'http://localhost:8989/course-details.php';
    if (App\Core\Url::to('/courses/example-course') !== '/courses/example-course' || App\Core\Url::to('/') !== '/') throw new RuntimeException('Host-qualified script names must not corrupt local route URLs.');
    $_SERVER['SCRIPT_NAME'] = '/SmartStudyProFin/course-details.php';
    if (App\Core\Url::to('/') !== '/SmartStudyProFin/' || App\Core\Url::to('/courses/example-course') !== '/SmartStudyProFin/courses/example-course') throw new RuntimeException('Nested detail-page links must preserve the installation base path.');
    if (App\Core\Url::to('\courses\testing') !== '/SmartStudyProFin/courses/testing' || App\Core\Url::to('courses\testing') !== '/SmartStudyProFin/courses/testing') throw new RuntimeException('Single Windows backslashes must normalize into internal URL separators.');
    if (App\Core\Url::to('\\\\courses\testing') !== '/SmartStudyProFin/') throw new RuntimeException('Double-leading backslashes must not become an external host URL.');
    if (App\Core\Url::to('https://example.com/path') !== 'https://example.com/path') throw new RuntimeException('Absolute HTTPS URLs must remain absolute.');
    if (App\Core\Url::to('/courses/testing?next=%2Flearn%2Fscience') !== '/SmartStudyProFin/courses/testing?next=%2Flearn%2Fscience') throw new RuntimeException('Query strings must be preserved while generating internal URLs.');
    $_SERVER['SCRIPT_NAME'] = '/SmartStudyProFin/admin/orders.php';
    if (App\Core\Url::basePath() !== '/SmartStudyProFin' || App\Core\Url::to('/assets/css/main.css') !== '/SmartStudyProFin/assets/css/main.css' || App\Core\Url::documentBase() !== '/SmartStudyProFin/admin/') throw new RuntimeException('Admin entrypoints must separate application URLs from admin-relative document links.');
    $_SERVER['SCRIPT_NAME'] = '/SmartStudyProFin/forms/contact.php';
    if (App\Core\Url::basePath() !== '/SmartStudyProFin' || App\Core\Url::documentBase() !== '/SmartStudyProFin/forms/') throw new RuntimeException('Form entrypoints must preserve their own relative document base.');
    $_SERVER['SCRIPT_NAME'] = '\\SmartStudyProFin\\admin\\orders.php';
    if (App\Core\Url::basePath() !== '/SmartStudyProFin' || App\Core\Url::documentBase() !== '/SmartStudyProFin/admin/') throw new RuntimeException('Windows-style admin paths must resolve against the admin directory and application root.');
    if (App\Core\Url::resolve('orders.php?saved=1') !== '/SmartStudyProFin/admin/orders.php?saved=1' || App\Core\Url::resolve('../login.php?redirect=admin%2Forders.php') !== '/SmartStudyProFin/login.php?redirect=admin%2Forders.php' || App\Core\Url::resolve('/profile') !== '/SmartStudyProFin/profile') throw new RuntimeException('Relative redirects must resolve within their current directory and application base.');
    if (App\Core\Url::resolve(App\Core\Url::to('/learn/science')) !== '/SmartStudyProFin/learn/science') throw new RuntimeException('Prebuilt application URLs must not receive the installation prefix twice.');
    if (App\Core\Url::resolve('../../outside.php') !== '/SmartStudyProFin/outside.php') throw new RuntimeException('Relative redirects must not escape the application installation directory.');
    if (App\Core\Url::resolve('\\\\\\\\evil.example/path') !== '/SmartStudyProFin/') throw new RuntimeException('Backslash-based external redirect targets must be rejected.');
    $_SERVER['SCRIPT_NAME'] = $previousScriptName ?? '/index.php';
    $_SERVER['SCRIPT_NAME'] = 'http://localhost:8989/SmartStudyProFin/index.php';
    if (App\Core\Url::basePath() !== '/SmartStudyProFin') throw new RuntimeException('Host-qualified script paths must preserve their URL path.');
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    if ($previousScriptName === null) unset($_SERVER['SCRIPT_NAME']); else $_SERVER['SCRIPT_NAME'] = $previousScriptName;

    if (!str_contains($routeRules, 'RewriteRule ^install(?:/|$) - [R=404,L,NC]') || !str_contains($routeRules, 'RewriteRule ^admin/?$ index.php [QSA,L]') || !str_contains($routeRules, 'RewriteRule ^ index.php [QSA,L]')) throw new RuntimeException('Apache must send admin and clean URLs through the index.php front controller.');
    if (!str_contains($routeRules, 'RewriteRule ^about\\.php$ about [R=301,L]') || !str_contains($routeRules, 'RewriteRule ^(?:learning|study)/?$ learn [R=301,L]') || !str_contains($routeRules, 'RewriteRule ^index\\.php$ ./ [R=301,L]')) throw new RuntimeException('Canonical redirects must preserve subdirectory installations.');
    if (preg_match('/^RewriteRule\\s+\\S+\\s+\\/[A-Za-z]/m', $routeRules)) throw new RuntimeException('Canonical rewrite targets must not discard the application subdirectory.');
    $frontController = (string) file_get_contents(dirname(__DIR__) . '/index.php');
    if (!str_contains($frontController, "preg_match('#^/install(?:/|$)#i', \$path)")) throw new RuntimeException('Removed installer paths must be explicitly rejected by the front controller.');
    $provisionScript = (string) file_get_contents(dirname(__DIR__) . '/scripts/provision.php');
    if (!str_contains($provisionScript, "PHP_SAPI !== 'cli'") || !str_contains($provisionScript, "getenv('SSP_ADMIN_PASSWORD')") || str_contains($provisionScript, '$argv[3]') || is_dir(dirname(__DIR__) . '/install')) throw new RuntimeException('First-run provisioning must be CLI-only, keep passwords out of command arguments and ship no public installer directory.');
    foreach (["'/profile' => 'profile.php'", "'/cart/add' => 'cart-add.php'", "'/assistant' => 'assistant.php'", "'/learn' => 'learning.php'", "course_slug"] as $route) {
        if (!str_contains($frontController, $route)) throw new RuntimeException('The index.php front controller is missing route support: ' . $route);
    }
    if (is_file(dirname(__DIR__) . '/router.php') || str_contains((string) file_get_contents(dirname(__DIR__) . '/README.md'), 'php -S 127.0.0.1:8000 router.php')) throw new RuntimeException('A separate router.php must not be required by the application.');
    $studyPage = (string) file_get_contents(dirname(__DIR__) . '/study.php');
    if (!str_contains($studyPage, 'Url::to(\'/\')') || !str_contains($studyPage, 'Url::to(\'/learn/\'')) throw new RuntimeException('Nested learning pages are missing their base-path-aware assets and lesson routes.');
    $portalTheme = (string) file_get_contents(dirname(__DIR__) . '/assets/css/portal-theme.css');
    if (!str_contains($portalTheme, '[data-theme="dark"] body') || !str_contains($portalTheme, '.assistant-panel') || !str_contains($portalTheme, '.lesson-link')) throw new RuntimeException('The student portal dark theme is missing shared learning and assistant surfaces.');
    foreach (['learning.php', 'study.php', 'achievements.php', 'certificates.php', 'notifications.php', 'assistant.php'] as $portalPage) {
        if (!str_contains((string) file_get_contents(dirname(__DIR__) . '/' . $portalPage), 'assets/css/portal-theme.css')) throw new RuntimeException('A student portal page is missing the shared theme stylesheet: ' . $portalPage);
    }
    if (!str_contains($studyPage, 'study-theme-toggle') || !str_contains($studyPage, 'ssp-theme') || !str_contains($studyPage, 'theme-preference')) throw new RuntimeException('The focused lesson player is missing its persistent account-synced theme control.');
    if (!str_contains($studyPage, 'Try again') || !str_contains($studyPage, '$lessonCompleted') || !str_contains($studyPage, 'You can now mark this lesson complete.') || !str_contains($studyPage, 'form.requestSubmit()')) throw new RuntimeException('The learning player must expose quiz retries, a clear completed-lesson state and timed-attempt submission.');
    $adminTheme = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin-theme.css');
    if (!str_contains($adminTheme, 'html[data-theme="dark"] body') || !str_contains($adminTheme, '.table')) throw new RuntimeException('The admin commerce dark theme is incomplete.');
    foreach (['admin/orders.php', 'admin/refunds.php'] as $adminPage) {
        $adminMarkup = (string) file_get_contents(dirname(__DIR__) . '/' . $adminPage);
        if (!str_contains($adminMarkup, 'assets/css/admin-theme.css') || !str_contains($adminMarkup, 'data-admin-theme-toggle') || !str_contains($adminMarkup, 'ssp-theme') || !str_contains($adminMarkup, 'theme-preference.php')) throw new RuntimeException('An admin commerce page is missing its shared account-synced theme control: ' . $adminPage);
    }
    $cmsMarkup = (string) file_get_contents(dirname(__DIR__) . '/admin/cms.php');
    $cmsThemeScript = (string) file_get_contents(dirname(__DIR__) . '/assets/js/cms-theme.js');
    if (!str_contains($cmsMarkup, 'data-theme-endpoint="../theme-preference.php"') || !str_contains($cmsMarkup, 'data-csrf=') || !str_contains($cmsThemeScript, 'X-CSRF-Token') || !str_contains($cmsThemeScript, 'JSON.stringify({ theme: isDark ? "dark" : "light" })')) throw new RuntimeException('The CMS theme control is not synchronized with the signed-in account.');
    if (!str_contains($cmsMarkup, 'if(' . '$asset' . "['visibility']!=='public'") || !str_contains($cmsMarkup, 'image/jpeg')) throw new RuntimeException('CMS page hero selectors must only offer public supported image assets.');
    if (!str_contains($cmsMarkup, "if(\$field==='image_media_id')") || !str_contains($cmsMarkup, "visibility']==='public'") || !str_contains($cmsMarkup, "mime_type']==='application/pdf' && \$asset['visibility']==='private'") || !str_contains($cmsMarkup, "mime_type']==='video/mp4' && \$asset['visibility']==='private'")) throw new RuntimeException('CMS content selectors must only offer media that matches its public or private delivery rules.');
    $applicationBootstrap = (string) file_get_contents(dirname(__DIR__) . '/bootstrap/app.php');
    if (str_contains($applicationBootstrap, 'Installer::isInstalled()') || str_contains($applicationBootstrap, '/install')) throw new RuntimeException('Web bootstrap must not contain installer redirects or first-run setup logic.');
    $cartPage = (string) file_get_contents(dirname(__DIR__) . '/cart.php');
    if (!str_contains($cartPage, "Url::to('/checkout')") || str_contains($cartPage, 'action="checkout.php"')) throw new RuntimeException('The cart must open the checkout review before creating an order.');
    $checkoutPage = (string) file_get_contents(dirname(__DIR__) . '/checkout.php');
    $ordersPage = (string) file_get_contents(dirname(__DIR__) . '/orders.php');
    if (!str_contains($checkoutPage, 'unset($_SESSION') || !str_contains($checkoutPage, 'ssp_cart') || !str_contains($ordersPage, 'Continue payment') || !str_contains($ordersPage, 'payment-choice?order=')) throw new RuntimeException('Checkout must clear a created cart and preserve a retry path for pending orders.');
    $cartAction = (string) file_get_contents(dirname(__DIR__) . '/cart-add.php');
    if (!str_contains($cartAction, "Security::verifyCsrf") || !str_contains($cartAction, "product_type") || !str_contains($cartAction, "status = 'published'")) throw new RuntimeException('The cart endpoint is missing server-side request and catalogue validation.');
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
    if (($learning->course('foundations-of-science')['title'] ?? '') !== 'Foundations of Science') throw new RuntimeException('Learning course slug resolution failed.');
    if ($learning->hasAccess($userId, $courseId)) throw new RuntimeException('A student gained access before enrolment.');
    $learning->enrolFreeCourse($userId, $courseId);
    if (!$learning->hasAccess($userId, $courseId)) throw new RuntimeException('Free course enrolment did not grant access.');
    $outline = $learning->outline($userId, $courseId);
    $firstChapter = reset($outline);
    $outlineLessons = $firstChapter['lessons'];
    if (count($outline) !== 1 || count($outlineLessons) !== 1) throw new RuntimeException('Learning course outline did not load.');
    $assetInsert = $pdo->prepare("INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES (:path, 'lesson.pdf', 'application/pdf', 10, 'test-checksum', 'Lesson PDF', 'private', :user_id, :created_at)");
    $assetInsert->execute(['path' => 'media/test/lesson.pdf', 'user_id' => $userId, 'created_at' => $now]);
    $resourceId = (int) $pdo->lastInsertId();
    $mediaManager = new MediaManager($pdo, $temporary);
    $mediaManager->updateMetadata($resourceId, 'Private lesson resource', 'private');
    $publicDocumentRejected = false;
    try { $mediaManager->updateMetadata($resourceId, 'Not public', 'public'); } catch (RuntimeException) { $publicDocumentRejected = true; }
    if (!$publicDocumentRejected) throw new RuntimeException('A private document was incorrectly allowed to become public.');
    $pdo->prepare("INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES ('media/test/library-image.png', 'library-image.png', 'image/png', 128, 'image-checksum', '', 'private', :user_id, :created_at)")->execute(['user_id' => $userId, 'created_at' => $now]);
    $imageAssetId = (int) $pdo->lastInsertId();
    $mediaManager->updateMetadata($imageAssetId, 'Library cover image', 'public');
    $libraryAssets = $mediaManager->all();
    $libraryImage = array_values(array_filter($libraryAssets, static fn (array $asset): bool => (int) $asset['id'] === $imageAssetId))[0] ?? null;
    if (!$libraryImage || $libraryImage['visibility'] !== 'public' || $libraryImage['alt_text'] !== 'Library cover image' || $libraryImage['uploader_name'] !== 'Test Admin') throw new RuntimeException('Media metadata, visibility or uploader details did not persist.');
    $content->save('lessons', $lessonId, ['resource_media_id' => $resourceId, 'resource_title' => 'Science notes', 'quiz_time_limit_minutes' => 5, 'quiz_pass_mark' => 75]);
    if (!$learning->media($userId, $resourceId)) throw new RuntimeException('An enrolled student could not resolve their private lesson resource.');
    $invalidVideoReferenceRejected = false;
    try { $content->save('lessons', $lessonId, ['video_media_id' => $resourceId]); } catch (RuntimeException) { $invalidVideoReferenceRejected = true; }
    if (!$invalidVideoReferenceRejected) throw new RuntimeException('CMS accepted a PDF as a lesson video.');
    $invalidResourceReferenceRejected = false;
    try { $content->save('lessons', $lessonId, ['resource_media_id' => $imageAssetId]); } catch (RuntimeException) { $invalidResourceReferenceRejected = true; }
    if (!$invalidResourceReferenceRejected) throw new RuntimeException('CMS accepted a public image as a private lesson resource.');
    $invalidQuizEditRejected = false;
    try { $content->save('questions', $questionId, ['options_json' => ['A', 'B']]); } catch (RuntimeException) { $invalidQuizEditRejected = true; }
    $savedQuestion = $content->find('questions', $questionId);
    if (!$invalidQuizEditRejected || !in_array('Study of nature', json_decode((string) $savedQuestion['options_json'], true), true)) throw new RuntimeException('A partial quiz edit invalidated its saved correct answer.');

    $pdo->prepare("UPDATE course_enrolments SET status = 'revoked' WHERE user_id = :user_id AND course_id = :course_id")->execute(['user_id' => $userId, 'course_id' => $courseId]);
    if ($learning->hasAccess($userId, $courseId) || $learning->media($userId, $resourceId) !== null) throw new RuntimeException('A revoked enrolment retained lesson or media access.');
    $pdo->prepare("UPDATE course_enrolments SET status = 'active' WHERE user_id = :user_id AND course_id = :course_id")->execute(['user_id' => $userId, 'course_id' => $courseId]);
    if (!$learning->hasAccess($userId, $courseId) || !$learning->media($userId, $resourceId)) throw new RuntimeException('Restoring an enrolment did not restore course access.');

    $insert->execute(['name' => 'Outside Student', 'email' => 'outside@example.test', 'password_hash' => password_hash('another-strong-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $outsideUserId = (int) $pdo->lastInsertId();
    if ($learning->hasAccess($outsideUserId, $courseId) || $learning->media($outsideUserId, $resourceId) !== null) throw new RuntimeException('A different student accessed another student course resource.');
    $learning->startLesson($userId, $courseId, $lessonId);
    $learning->updatePosition($userId, $courseId, $lessonId, 95);
    $learning->toggleBookmark($userId, $courseId, $lessonId);
    $learningDashboard = $learning->dashboard($userId);
    $savedBookmarks = $learningDashboard['bookmarks'];
    if (count($savedBookmarks) !== 1) throw new RuntimeException('Lesson bookmark did not persist.');
    $quizData = $learning->quizQuestions($userId, $courseId, $lessonId);
    App\Core\Session::start();
    $quizTimerKey = 'quiz_started_' . $lessonId;
    $_SESSION[$quizTimerKey] = time() - 400;
    $expiredQuizRejected = false;
    try { $learning->submitQuiz($userId, $courseId, $lessonId, []); } catch (RuntimeException) { $expiredQuizRejected = true; }
    if (!$expiredQuizRejected) throw new RuntimeException('An expired timed quiz was accepted.');
    $quizData = $learning->quizQuestions($userId, $courseId, $lessonId);
    $failedResult = $learning->submitQuiz($userId, $courseId, $lessonId, [$questionId => 'A sport']);
    if ($failedResult['passed'] || count($learning->attempts($userId, $lessonId)) !== 1) throw new RuntimeException('A failed quiz attempt was not saved accurately.');
    $failedQuizCompletionRejected = false;
    try { $learning->completeLesson($userId, $courseId, $lessonId); } catch (RuntimeException) { $failedQuizCompletionRejected = true; }
    if (!$failedQuizCompletionRejected) throw new RuntimeException('A lesson with a quiz was completed without a passing attempt.');
    $quizData = $learning->quizQuestions($userId, $courseId, $lessonId);
    $submittedAnswers = [];
    $submittedAnswers[$questionId] = 'Study of nature';
    $quizResult = $learning->submitQuiz($userId, $courseId, $lessonId, $submittedAnswers);
    if (!$quizResult['passed'] || count($learning->attempts($userId, $lessonId)) !== 2) throw new RuntimeException('A passing retry did not preserve both quiz attempts.');
    $learning->completeLesson($userId, $courseId, $lessonId);
    $completedOutline = $learning->outline($userId, $courseId);
    $completedChapter = reset($completedOutline);
    $completedLessons = $completedChapter['lessons'];
    $completedLesson = reset($completedLessons);
    if (($completedLesson['progress_status'] ?? '') !== 'completed') throw new RuntimeException('Lesson completion did not persist.');
    $pdo->exec("UPDATE cms_courses SET price_ugx = 25000 WHERE id = " . $courseId);
    $paidCourseRejected = false;
    try { $learning->enrolFreeCourse($userId, $courseId); } catch (RuntimeException) { $paidCourseRejected = true; }
    if (!$paidCourseRejected) throw new RuntimeException('A paid course was incorrectly enrolled as free.');

    $productId = $content->save('products', null, ['title'=>'Study Guide','description_html'=>'Guide','price_ugx'=>'10000','category'=>'Books','product_type'=>'physical','status'=>'published']);
    if (!$content->find('products', $productId)) throw new RuntimeException('Product creation failed.');
    $digitalAsset = $pdo->prepare("INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES ('media/store/guide.pdf', 'guide.pdf', 'application/pdf', 100, 'digital-checksum', 'Digital guide', 'private', :user_id, :created_at)");
    $digitalAsset->execute(['user_id' => $userId, 'created_at' => $now]);
    $digitalAssetId = (int) $pdo->lastInsertId();
    $paidCourseId = $content->save('courses', null, ['title'=>'Secure Checkout Course','description_html'=>'Paid course','content_html'=>'Course content','price_ugx'=>'25000','category'=>'Technology','status'=>'published']);
    $digitalProductId = $content->save('products', null, ['title'=>'Digital Study Guide','description_html'=>'Downloadable guide','price_ugx'=>'10000','category'=>'Books','product_type'=>'online','product_file_media_id'=>$digitalAssetId,'status'=>'published']);
    $commerce = new CommerceService($pdo);
    $duplicateCartRejected = false;
    try { $commerce->createOrder($userId, [['type'=>'course','id'=>$paidCourseId,'quantity'=>1], ['type'=>'course','id'=>$paidCourseId,'quantity'=>1]]); } catch (RuntimeException) { $duplicateCartRejected = true; }
    if (!$duplicateCartRejected) throw new RuntimeException('Duplicate cart lines were accepted.');
    $order = $commerce->createOrder($userId, [['type'=>'course','id'=>$paidCourseId,'quantity'=>1], ['type'=>'product','id'=>$digitalProductId,'quantity'=>1]]);
    if ((int) $order['total_ugx'] !== 35000 || count($order['items']) !== 2) throw new RuntimeException('Order creation did not calculate authoritative catalogue prices.');
    $payment = $commerce->createPayment((int) $order['id'], $userId, 'dpo');
    $commerce->updateProviderReference((int) $payment['id'], 'dpo-test-token', 'CREATED', ['company_ref'=>$order['public_id']]);
    $wrongAmountRejected = false;
    try { $commerce->confirmPayment((int) $payment['id'], 'dpo', 'dpo-test-token', 'approval-1', 34999, 'UGX', 'COMPLETED', 'dpo-test-event'); } catch (RuntimeException) { $wrongAmountRejected = true; }
    if (!$wrongAmountRejected || $commerce->order((int) $order['id'], $userId)['status'] !== 'pending' || $learning->hasAccess($userId, $paidCourseId)) throw new RuntimeException('An unmatching payment incorrectly granted purchase access.');
    if (!$commerce->confirmPayment((int) $payment['id'], 'dpo', 'dpo-test-token', 'approval-1', 35000, 'UGX', 'COMPLETED', 'dpo-test-event')) throw new RuntimeException('A matching verified payment did not complete the order.');
    if ($commerce->confirmPayment((int) $payment['id'], 'dpo', 'dpo-test-token', 'approval-1', 35000, 'UGX', 'COMPLETED', 'dpo-test-event')) throw new RuntimeException('A duplicate payment confirmation was not idempotent.');
    $paidOrder = $commerce->order((int) $order['id'], $userId);
    if ($paidOrder['status'] !== 'paid' || !$learning->hasAccess($userId, $paidCourseId)) throw new RuntimeException('A verified course purchase did not activate enrolment.');
    $download = $commerce->purchasedFile($userId, (int) $paidOrder['items'][1]['id']);
    if (!$download || $download['storage_path'] !== 'media/store/guide.pdf') throw new RuntimeException('A paid digital product did not receive a secure file entitlement.');
    if ($commerce->purchasedFile($outsideUserId, (int) $paidOrder['items'][1]['id']) !== null) throw new RuntimeException('A different user accessed a purchased digital product.');
    $shipping = new ShippingService($pdo);
    $zoneId = $shipping->saveZone(null, 'Test zone', 'UG', 'Kampala', true);
    $methodId = $shipping->saveMethod(null, $zoneId, 'Test delivery', '', 0, 1, 2, true);
    $unpaidOrder = $commerce->createOrder($outsideUserId, [['type'=>'product','id'=>$productId,'quantity'=>1]], ['method_id'=>$methodId,'name'=>'Test Recipient','phone'=>'+256700000002','address'=>'Test Road','city'=>'Kampala','region'=>'Kampala','country'=>'UG']);
    if ((int) $unpaidOrder['total_ugx'] !== 10000 || $unpaidOrder['status'] !== 'pending') throw new RuntimeException('Order ownership or pending status was not enforced.');

    $public = new PublicContent($pdo);
    $draftCourseId = $content->save('courses', null, ['title'=>'Draft Learning Programme','description_html'=>'Not public yet','price_ugx'=>0,'category'=>'Testing','status'=>'draft']);
    if ($public->course($draftCourseId) !== null || $learning->course($draftCourseId) !== null || count($public->courses()) !== 2) throw new RuntimeException('Draft course content leaked into public catalogue or student learning.');
    $content->save('courses', $draftCourseId, ['status'=>'published']);
    if (($public->course($draftCourseId)['Title'] ?? '') !== 'Draft Learning Programme' || count($public->courses()) !== 3) throw new RuntimeException('Publishing a course did not make it available in the public catalogue.');
    $content->save('courses', $draftCourseId, ['status'=>'draft']);
    $draftLessonId = $content->save('lessons', null, ['chapter_id'=>$chapterId,'title'=>'Unpublished lesson','content_html'=>'Hidden lesson','status'=>'draft']);
    if ($learning->lesson($userId, $courseId, $draftLessonId) !== null || count($learning->outline($userId, $courseId)[0]['lessons']) !== 1) throw new RuntimeException('Draft lesson content leaked into the enrolled student portal.');
    $content->save('lessons', $draftLessonId, ['status'=>'published']);
    if (($learning->lesson($userId, $courseId, $draftLessonId)['title'] ?? '') !== 'Unpublished lesson' || count($learning->outline($userId, $courseId)[0]['lessons']) !== 2) throw new RuntimeException('Publishing a lesson did not update the enrolled course outline.');
    $content->save('lessons', $draftLessonId, ['status'=>'draft']);
    if ($learning->lesson($userId, $courseId, $draftLessonId) !== null) throw new RuntimeException('Unpublishing a lesson did not revoke student-facing lesson access.');
    if (($public->course((string) $courseId)['Title'] ?? '') !== 'Foundations of Science' || ($public->course('foundations-of-science')['Title'] ?? '') !== 'Foundations of Science') throw new RuntimeException('Published course public read mapping failed.');
    $pdo->prepare('UPDATE cms_courses SET description_html = :description, content_html = :content WHERE id = :id')->execute(['description' => '<p>Course description</p><script>alert(1)</script>', 'content' => '<h3>Course outline</h3><img src=x onerror=alert(1)>', 'id' => $courseId]);
    $publishedCourse = $public->course($courseId);
    if (!$publishedCourse || str_contains($publishedCourse['Description'], '<script') || str_contains($publishedCourse['Content'], 'onerror=') || !str_contains($publishedCourse['Content'], 'Course outline')) throw new RuntimeException('Public course content was not safely sanitised.');
    $courseDetailsPage = (string) file_get_contents(dirname(__DIR__) . '/course-details.php');
    $courseCataloguePage = (string) file_get_contents(dirname(__DIR__) . '/courses.php');
    if (!str_contains($courseDetailsPage, '$course' . "['Description']") || !str_contains($courseDetailsPage, '$course' . "['Content']") || !str_contains($courseDetailsPage, 'About this course') || !str_contains($courseCataloguePage, '$siteUrl ?>/courses/')) throw new RuntimeException('Published course descriptions, full overview or canonical catalogue URLs are not rendered correctly.');
    if (($public->product((string) $productId)['ProductType'] ?? '') !== 'Physical' || count($public->products()) !== 2) throw new RuntimeException('Published product public read mapping failed.');
    $pdo->prepare('UPDATE cms_products SET description_html = :description WHERE id = :id')->execute(['description' => '<p>Safe product details</p><script>window.productInjected = true;</script><img src=x onerror=alert(1)>', 'id' => $productId]);
    $publicProduct = $public->product((string) $productId);
    if (!$publicProduct || str_contains($publicProduct['Description'], '<script') || str_contains($publicProduct['Description'], 'onerror=') || !str_contains($publicProduct['Description'], 'Safe product details')) throw new RuntimeException('Public product reads did not sanitize unsafe stored HTML while preserving safe content.');

    $content->savePage('home', ['title'=>'Learn with purpose','subtitle'=>'SmartStudyPro','body_html'=>'Home content','status'=>'published']);
    if (($content->page('home')['title'] ?? '') !== 'Learn with purpose') throw new RuntimeException('CMS page editing failed.');
    $privateHeroRejected = false;
    try { $content->savePage('home', ['title'=>'Invalid hero','hero_media_id'=>(string) $resourceId,'status'=>'published']); } catch (RuntimeException) { $privateHeroRejected = true; }
    if (!$privateHeroRejected || ($content->page('home')['title'] ?? '') !== 'Learn with purpose') throw new RuntimeException('CMS accepted a private lesson resource as a public page hero or changed content after invalid input.');
    $privateCourseImageRejected = false;
    try { $content->save('courses', null, ['title'=>'Private image course','image_media_id'=>(string) $resourceId,'status'=>'draft']); } catch (RuntimeException) { $privateCourseImageRejected = true; }
    if (!$privateCourseImageRejected) throw new RuntimeException('CMS accepted a private lesson resource as a public course image.');
    $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala']);
    if (($content->settings()['contact.phone'] ?? '') !== '+256700000000') throw new RuntimeException('CMS settings editing failed.');
    $publicFavicon = $pdo->prepare("INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES ('media/public/favicon.png', 'favicon.png', 'image/png', 128, 'favicon-checksum', 'Site favicon', 'public', :user_id, :created_at)");
    $publicFavicon->execute(['user_id' => $userId, 'created_at' => $now]);
    $faviconId = (int) $pdo->lastInsertId();
    $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala','site.favicon_media_id'=>(string) $faviconId]);
    if (($content->settings()['site.favicon_media_id'] ?? '') !== (string) $faviconId) throw new RuntimeException('CMS public favicon setting was not saved.');
    $content->saveSettings(['contact.phone'=>'+256700000001']);
    if (($content->settings()['site.favicon_media_id'] ?? '') !== (string) $faviconId) throw new RuntimeException('A partial CMS settings update cleared the saved favicon.');
    $privateFaviconRejected = false;
    try { $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala','site.favicon_media_id'=>(string) $resourceId]); } catch (RuntimeException) { $privateFaviconRejected = true; }
    if (!$privateFaviconRejected || ($content->settings()['site.favicon_media_id'] ?? '') !== (string) $faviconId) throw new RuntimeException('CMS accepted a private favicon or changed settings after invalid input.');
    $publicPdf = $pdo->prepare("INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES ('media/public/not-a-favicon.pdf', 'document.pdf', 'application/pdf', 128, 'pdf-checksum', 'Document', 'public', :user_id, :created_at)");
    $publicPdf->execute(['user_id' => $userId, 'created_at' => $now]);
    $publicPdfId = (int) $pdo->lastInsertId();
    $publicNonImageRejected = false;
    try { $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala','site.favicon_media_id'=>(string) $publicPdfId]); } catch (RuntimeException) { $publicNonImageRejected = true; }
    if (!$publicNonImageRejected || ($content->settings()['site.favicon_media_id'] ?? '') !== (string) $faviconId) throw new RuntimeException('CMS accepted a public non-image favicon.');
    $invalidFaviconRejected = false;
    try { $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala','site.favicon_media_id'=>'999999']); } catch (RuntimeException) { $invalidFaviconRejected = true; }
    if (!$invalidFaviconRejected) throw new RuntimeException('CMS accepted a nonexistent favicon media record.');
    $content->saveSettings(['contact.phone'=>'+256700000000','contact.email'=>'hello@example.test','contact.address'=>'Kampala','site.favicon_media_id'=>'']);
    if (($content->settings()['site.favicon_media_id'] ?? 'not-empty') !== '') throw new RuntimeException('CMS could not restore the default favicon.');
    $content->delete('courses', $courseId);
    if ((int) $pdo->query('SELECT COUNT(*) FROM cms_chapters')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM cms_lessons')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM cms_quiz_questions')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM course_enrolments WHERE course_id = ' . $courseId)->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM lesson_progress WHERE course_id = ' . $courseId)->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM lesson_bookmarks')->fetchColumn() !== 0 || (int) $pdo->query('SELECT COUNT(*) FROM quiz_attempts')->fetchColumn() !== 0) throw new RuntimeException('Course deletion did not cascade to learning records.');

    for ($attempt = 0; $attempt < 8; $attempt++) {
        if (Auth::attempt($pdo, 'admin@example.test', 'incorrect-password')) throw new RuntimeException('Incorrect password was accepted.');
    }
    if (Auth::attempt($pdo, 'admin@example.test', 'a-strong-test-password')) throw new RuntimeException('Login throttle did not activate.');
    $pdo->exec('DELETE FROM login_attempts');
    if (!Auth::attempt($pdo, 'admin@example.test', 'a-strong-test-password')) throw new RuntimeException('Valid login was rejected.');
    Auth::logout();

    $accounts = new AccountService($pdo);
    $studentId = $accounts->register('Test Student', 'student@example.test', 'a-strong-student-password', ['terms' => '1.0.0', 'privacy' => '1.0.0'], '127.0.0.1', 'test-agent');
$googleService = new GoogleIdentityService($pdo);
$googleResolve = new ReflectionMethod(GoogleIdentityService::class, 'resolveAccount');
$googleClaims = ['sub' => 'google-subject-test', 'email' => 'google@example.test', 'email_verified' => true, 'name' => 'Google Student', 'picture' => 'https://example.test/avatar.png'];
$googleUserId = (int) $googleResolve->invoke($googleService, $googleClaims, 'register', 0);
$googleUser = $accounts->profile($googleUserId);
if (($googleUser['google_subject'] ?? '') !== 'google-subject-test' || (int) ($googleUser['has_password'] ?? 1) !== 0 || empty($googleUser['email_verified_at'])) throw new RuntimeException('Google registration did not create a verified passwordless identity.');
if (!Auth::hasRole($pdo, $googleUserId, 'student') || !(new LegalAcceptanceService($pdo))->hasCurrentAcceptance($googleUserId)) throw new RuntimeException('Google registration did not apply student access and legal acceptance.');
if ((int) $googleResolve->invoke($googleService, $googleClaims, 'login', 0) !== $googleUserId) throw new RuntimeException('An existing Google identity could not sign in.');
if (!Auth::attempt($pdo, 'student@example.test', 'a-strong-student-password')) throw new RuntimeException('The account-linking test could not authenticate the existing account.');
$linkClaims = ['sub' => 'google-link-subject', 'email' => 'different-google@example.test', 'email_verified' => true, 'name' => 'Linked Identity'];
if ((int) $googleResolve->invoke($googleService, $linkClaims, 'link', $studentId) !== $studentId) throw new RuntimeException('An authenticated account could not connect a verified Google identity.');
$linkedSubject = $pdo->prepare('SELECT google_subject FROM users WHERE id = :id');
$linkedSubject->execute(['id' => $studentId]);
if ($linkedSubject->fetchColumn() !== 'google-link-subject') throw new RuntimeException('The Google identity was not linked to the authenticated account.');
$linkedVerification = $pdo->prepare('SELECT email_verified_at FROM users WHERE id = :id');
$linkedVerification->execute(['id' => $studentId]);
$linkedEmailVerifiedAt = $linkedVerification->fetchColumn();
if ($linkedEmailVerifiedAt !== false && $linkedEmailVerifiedAt !== null) throw new RuntimeException('Linking a different Google email incorrectly verified the account email.');
$collisionRejected = false;
try {
    $googleResolve->invoke($googleService, $googleClaims, 'link', $studentId);
} catch (RuntimeException) {
    $collisionRejected = true;
}
if (!$collisionRejected) throw new RuntimeException('A Google identity already owned by another account was linked.');
Auth::logout();
$accounts->changePassword($googleUserId, '', 'a-google-account-password');
if (!Auth::attempt($pdo, 'google@example.test', 'a-google-account-password')) throw new RuntimeException('A Google-only account could not add a password.');
Auth::logout();
$verifyGoogleToken = new ReflectionMethod(GoogleIdentityService::class, 'verifyIdToken');
$invalidGoogleTokenRejected = false;
try {
    $verifyGoogleToken->invoke($googleService, 'not-a-jwt', 'expected-nonce');
} catch (RuntimeException) {
    $invalidGoogleTokenRejected = true;
}
if (!$invalidGoogleTokenRejected) throw new RuntimeException('A malformed Google identity token was accepted.');
$automaticLinkRejected = false;
try {
    $googleResolve->invoke($googleService, ['sub' => 'unlinked-google-subject', 'email' => 'student@example.test', 'email_verified' => true, 'name' => 'Existing Student'], 'login', 0);
} catch (RuntimeException) {
    $automaticLinkRejected = true;
}
if (!$automaticLinkRejected) throw new RuntimeException('Google sign-in automatically linked an existing email account.');
$oauthReplayRejected = false;
try {
    $googleService->complete('authorization-code', 'replayed-state');
} catch (RuntimeException) {
    $oauthReplayRejected = true;
}
if (!$oauthReplayRejected) throw new RuntimeException('A Google OAuth callback without a matching session state was accepted.');


    $acceptances = $pdo->prepare('SELECT document, version, ip_hash, user_agent_hash FROM user_legal_acceptances WHERE user_id = :user_id ORDER BY document');
    $acceptances->execute(['user_id' => $studentId]);
    $acceptedDocuments = $acceptances->fetchAll();
    if (count($acceptedDocuments) !== 2 || $acceptedDocuments[0]['document'] !== 'privacy' || $acceptedDocuments[1]['document'] !== 'terms' || $acceptedDocuments[0]['version'] !== '1.0.0' || $acceptedDocuments[0]['ip_hash'] !== hash('sha256', '127.0.0.1') || $acceptedDocuments[0]['user_agent_hash'] !== hash('sha256', 'test-agent')) throw new RuntimeException('Versioned legal acceptance records were not stored securely.');
    $legalAcceptance = new LegalAcceptanceService($pdo);
    if (!$legalAcceptance->hasCurrentAcceptance($studentId) || $legalAcceptance->hasCurrentAcceptance($userId)) throw new RuntimeException('Current legal acceptance was not checked correctly.');
    $legalAcceptance->acceptCurrent($userId, '127.0.0.2', 'admin-test-agent');
    if (!$legalAcceptance->hasCurrentAcceptance($userId)) throw new RuntimeException('An existing account could not accept current legal versions.');
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
    $pdo->exec('DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE user_id = ' . $userId . ')');
    $pdo->exec('DELETE FROM orders WHERE user_id = ' . $userId);
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
    putenv('GOOGLE_CLIENT_SECRET');
    $privateEnvironmentFile = $privateStorage . '/.env';
    if (file_put_contents($privateEnvironmentFile, "GOOGLE_CLIENT_SECRET=private-google-secret\n") === false) throw new RuntimeException('The private environment file could not be created.');
    chmod($privateEnvironmentFile, 0644);
    Config::load($root);
    if (Config::get('google.client_secret') !== '') throw new RuntimeException('Google OAuth credentials were loaded from a group/world-readable file.');
    chmod($privateEnvironmentFile, 0600);
    Config::load($root);
    if (Config::get('google.client_secret') !== 'private-google-secret') throw new RuntimeException('Google OAuth credentials were not loaded from private storage.');
    if ((fileperms($privateEnvironmentFile) & 0777) !== 0600) throw new RuntimeException('The private environment file permissions are unsafe.');
    putenv('APP_URL=http://localhost:8989/SmartStudyProFin');
    Config::load($root);
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    if (App\Core\Url::basePath() !== '/SmartStudyProFin' || App\Core\Url::to('/courses/testing') !== '/SmartStudyProFin/courses/testing' || App\Core\Url::absolute('/courses/testing') !== 'http://localhost:8989/SmartStudyProFin/courses/testing') throw new RuntimeException('APP_URL must control canonical host and subdirectory routing when the front controller is at the document root.');
    if (session_status() === PHP_SESSION_ACTIVE) App\Core\Session::destroy();
    App\Core\Session::start();
    if ((session_get_cookie_params()['path'] ?? '') !== '/SmartStudyProFin') throw new RuntimeException('Session cookies must be scoped to the configured application subdirectory.');
    App\Core\Session::destroy();
    putenv('APP_URL=http://localhost:8989');
    Config::load($root);
    if (App\Core\Url::basePath() !== '' || App\Core\Url::to('/courses/testing') !== '/courses/testing' || App\Core\Url::absolute('/courses/testing') !== 'http://localhost:8989/courses/testing') throw new RuntimeException('Root APP_URL must produce root-relative routes without host-like paths.');
    putenv('APP_URL=http://localhost:8000');
    putenv('GOOGLE_CLIENT_ID=test-client.apps.googleusercontent.com');
    putenv('GOOGLE_CLIENT_SECRET=test-private-client-secret');
    Config::load($root);
    if (!GoogleIdentityService::isConfigured()) throw new RuntimeException('Valid localhost Google OAuth settings were not recognized.');
    putenv('APP_URL=http://example.test');
    Config::load($root);
    if (GoogleIdentityService::isConfigured() || !in_array('APP_URL must be the canonical HTTPS site URL (HTTP is allowed only for localhost development).', GoogleIdentityService::configurationIssues(), true)) throw new RuntimeException('An insecure non-local Google OAuth URL was accepted.');
    putenv('APP_URL');
    putenv('GOOGLE_CLIENT_ID');
    putenv('GOOGLE_CLIENT_SECRET');
    unlink($privateEnvironmentFile);
    Config::load($root);

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
