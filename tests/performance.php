<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "BLOCKED PDO SQLite is not installed.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;
use App\Core\Migrator;

$temporary = sys_get_temp_dir() . '/ssp-performance-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) throw new RuntimeException('Could not create performance test storage.');

try {
    $pdo = Database::connect($temporary . '/performance.sqlite');
    $ran = Migrator::run($pdo, dirname(__DIR__) . '/database/migrations');
    if (!in_array('009_production_indexes', $ran, true)) throw new RuntimeException('Production query indexes were not installed.');
    $checks = [
        ['cms_courses', 'idx_cms_courses_status_order', 'SELECT id FROM cms_courses WHERE status = \'published\' ORDER BY sort_order, id'],
        ['cms_products', 'idx_cms_products_status_order', 'SELECT id FROM cms_products WHERE status = \'published\' ORDER BY sort_order, id'],
        ['cms_chapters', 'idx_cms_chapters_course_order', 'SELECT id FROM cms_chapters WHERE course_id = 1 ORDER BY sort_order, id'],
        ['cms_lessons', 'idx_cms_lessons_chapter_status_order', 'SELECT id FROM cms_lessons WHERE chapter_id = 1 AND status = \'published\' ORDER BY sort_order, id'],
        ['cms_quiz_questions', 'idx_cms_quiz_questions_lesson_order', 'SELECT id FROM cms_quiz_questions WHERE lesson_id = 1 ORDER BY sort_order, id'],
        ['lesson_progress', 'idx_lesson_progress_user_updated', 'SELECT id FROM lesson_progress WHERE user_id = 1 ORDER BY updated_at DESC'],
        ['media_assets', 'idx_media_assets_visibility_created', 'SELECT id FROM media_assets WHERE visibility = \'private\' ORDER BY created_at DESC, id DESC']
    ];
    foreach ($checks as [$table, $index, $sql]) {
        $indexes = $pdo->query("PRAGMA index_list('" . $table . "')")->fetchAll();
        if (!in_array($index, array_column($indexes, 'name'), true)) throw new RuntimeException('Missing performance index: ' . $index);
        $plan = $pdo->query('EXPLAIN QUERY PLAN ' . $sql)->fetchAll();
        $details = implode(' ', array_column($plan, 'detail'));
        if (!str_contains($details, $index)) throw new RuntimeException('SQLite did not select the expected index: ' . $index);
    }
    echo "PASS production query indexes are installed\nPASS course, product, chapter, lesson and quiz query plans\nPASS student progress and media query plans\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    @rmdir($temporary);
}
