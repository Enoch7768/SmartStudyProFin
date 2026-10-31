<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE course_enrolments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, course_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed', 'revoked')), source TEXT NOT NULL DEFAULT 'free' CHECK (source IN ('free', 'manual', 'payment')), enrolled_at TEXT NOT NULL, completed_at TEXT, updated_at TEXT NOT NULL, UNIQUE(user_id, course_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (course_id) REFERENCES cms_courses(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE lesson_progress (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, course_id INTEGER NOT NULL, lesson_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'started' CHECK (status IN ('started', 'completed')), last_position_seconds INTEGER NOT NULL DEFAULT 0 CHECK (last_position_seconds >= 0), started_at TEXT NOT NULL, completed_at TEXT, updated_at TEXT NOT NULL, UNIQUE(user_id, lesson_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (course_id) REFERENCES cms_courses(id) ON DELETE CASCADE, FOREIGN KEY (lesson_id) REFERENCES cms_lessons(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE lesson_bookmarks (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, lesson_id INTEGER NOT NULL, created_at TEXT NOT NULL, UNIQUE(user_id, lesson_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (lesson_id) REFERENCES cms_lessons(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE quiz_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, course_id INTEGER NOT NULL, lesson_id INTEGER NOT NULL, answers_json TEXT NOT NULL, question_count INTEGER NOT NULL CHECK (question_count >= 0), correct_count INTEGER NOT NULL CHECK (correct_count >= 0), score_percent INTEGER NOT NULL CHECK (score_percent BETWEEN 0 AND 100), passed INTEGER NOT NULL CHECK (passed IN (0, 1)), started_at TEXT NOT NULL, submitted_at TEXT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (course_id) REFERENCES cms_courses(id) ON DELETE CASCADE, FOREIGN KEY (lesson_id) REFERENCES cms_lessons(id) ON DELETE CASCADE)");
    $pdo->exec('CREATE INDEX idx_enrolments_user_status ON course_enrolments(user_id, status, enrolled_at)');
    $pdo->exec('CREATE INDEX idx_enrolments_course_status ON course_enrolments(course_id, status)');
    $pdo->exec('CREATE INDEX idx_lesson_progress_user_course ON lesson_progress(user_id, course_id, status)');
    $pdo->exec('CREATE INDEX idx_lesson_bookmarks_user ON lesson_bookmarks(user_id, created_at)');
    $pdo->exec('CREATE INDEX idx_quiz_attempts_user_date ON quiz_attempts(user_id, submitted_at)');
    $pdo->exec('CREATE INDEX idx_quiz_attempts_lesson ON quiz_attempts(user_id, lesson_id, submitted_at)');
};
