<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\AI\AssistantService;
use App\Cms\ContentRepository;
use App\Core\Database;
use App\Core\Migrator;

$temporary = sys_get_temp_dir() . '/ssp-ai-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create assistant test storage.');

try {
    $pdo = Database::connect($temporary . '/assistant.sqlite');
    $ran = Migrator::run($pdo, dirname(__DIR__) . '/database/migrations');
    if (!in_array('007_ai_learning_assistant', $ran, true)) throw new RuntimeException('The AI assistant migration did not run.');
    if (Migrator::run($pdo, dirname(__DIR__) . '/database/migrations') !== []) throw new RuntimeException('AI assistant migrations are not idempotent.');

    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, :created_at, :updated_at)');
    $insert->execute(['name' => 'Enrolled Student', 'email' => 'enrolled@example.test', 'password_hash' => password_hash('a-strong-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $insert->execute(['name' => 'Other Student', 'email' => 'other@example.test', 'password_hash' => password_hash('another-strong-password', PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $otherId = (int) $pdo->lastInsertId();

    $content = new ContentRepository($pdo);
    $courseId = $content->save('courses', null, ['title'=>'Foundations of Biology','description_html'=>'Biology course','content_html'=>'','price_ugx'=>'0','category'=>'Science','status'=>'published','sort_order'=>'1']);
    $chapterId = $content->save('chapters', null, ['course_id'=>$courseId,'title'=>'Cell structure','sort_order'=>'1']);
    $lessonId = $content->save('lessons', null, ['chapter_id'=>$chapterId,'title'=>'The cell membrane','content_html'=>'The cell membrane controls the movement of substances into and out of the cell. Diffusion moves particles from a region of higher concentration to a region of lower concentration.','video_url'=>'','sort_order'=>'1','status'=>'published']);
    $pdo->prepare("INSERT INTO course_enrolments (user_id, course_id, status, source, enrolled_at, updated_at) VALUES (:user_id, :course_id, 'active', 'free', :enrolled_at, :updated_at)")->execute(['user_id'=>$studentId,'course_id'=>$courseId,'enrolled_at'=>$now,'updated_at'=>$now]);

    $assistant = new AssistantService($pdo, '');
    $sources = $assistant->retrieve($studentId, 'How does diffusion move particles?');
    if (!$sources || (int) $sources[0]['lesson_id'] !== $lessonId) throw new RuntimeException('Grounded retrieval did not return the relevant enrolled lesson.');
    if ($assistant->retrieve($otherId, 'How does diffusion move particles?')) throw new RuntimeException('Retrieval exposed a lesson to a student without course access.');

    $unknown = $assistant->ask($studentId, 'What is the capital of Mars?');
    if ($unknown['sources'] !== [] || !str_contains($unknown['answer'], "couldn't find an answer")) throw new RuntimeException('The assistant did not refuse an unsupported course question.');
    $history = $assistant->history($studentId, (int) $unknown['conversation_id']);
    if (count($history) !== 2 || $history[0]['role'] !== 'user' || $history[1]['role'] !== 'assistant') throw new RuntimeException('Assistant conversation history did not persist.');
    $ownershipRejected = false;
    try {
        $assistant->history($otherId, (int) $unknown['conversation_id']);
    } catch (RuntimeException) {
        $ownershipRejected = true;
    }
    if (!$ownershipRejected) throw new RuntimeException('A student accessed another student conversation.');

    echo "PASS AI assistant migration and idempotency\nPASS enrolled-course grounded retrieval\nPASS cross-student retrieval isolation\nPASS unsupported question handling\nPASS conversation persistence and ownership\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    @rmdir($temporary);
}
