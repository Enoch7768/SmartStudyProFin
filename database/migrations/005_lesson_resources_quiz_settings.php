<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("ALTER TABLE cms_lessons ADD COLUMN resource_media_id INTEGER REFERENCES media_assets(id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE cms_lessons ADD COLUMN resource_title TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE cms_lessons ADD COLUMN quiz_time_limit_minutes INTEGER NOT NULL DEFAULT 0 CHECK (quiz_time_limit_minutes BETWEEN 0 AND 180)");
    $pdo->exec("ALTER TABLE cms_lessons ADD COLUMN quiz_pass_mark INTEGER NOT NULL DEFAULT 70 CHECK (quiz_pass_mark BETWEEN 1 AND 100)");
    $pdo->exec('CREATE INDEX idx_cms_lessons_resource ON cms_lessons(resource_media_id)');
};
