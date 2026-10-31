<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_courses_status_order ON cms_courses(status, sort_order, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_products_status_order ON cms_products(status, sort_order, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_chapters_course_order ON cms_chapters(course_id, sort_order, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_lessons_chapter_status_order ON cms_lessons(chapter_id, status, sort_order, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_quiz_questions_lesson_order ON cms_quiz_questions(lesson_id, sort_order, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lesson_progress_user_updated ON lesson_progress(user_id, updated_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_assets_visibility_created ON media_assets(visibility, created_at DESC, id DESC)');
};
