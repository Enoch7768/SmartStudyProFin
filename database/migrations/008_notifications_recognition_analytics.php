<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, event_key TEXT NOT NULL, type TEXT NOT NULL CHECK (type IN ('achievement', 'certificate', 'course', 'assessment', 'system')), title TEXT NOT NULL, message TEXT NOT NULL, target_url TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, read_at TEXT, UNIQUE(user_id, event_key), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
    $pdo->exec('CREATE INDEX idx_notifications_user_created ON notifications(user_id, created_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX idx_notifications_user_unread ON notifications(user_id, read_at, created_at DESC)');
    $pdo->exec("CREATE TABLE achievements (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL, description TEXT NOT NULL, icon TEXT NOT NULL, threshold INTEGER NOT NULL DEFAULT 1 CHECK (threshold > 0), metric TEXT NOT NULL CHECK (metric IN ('lessons_completed', 'quizzes_passed', 'courses_completed')))");
    $pdo->exec("CREATE TABLE user_achievements (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, achievement_id INTEGER NOT NULL, earned_at TEXT NOT NULL, UNIQUE(user_id, achievement_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE)");
    $pdo->exec('CREATE INDEX idx_user_achievements_user_date ON user_achievements(user_id, earned_at DESC)');
    $pdo->exec("CREATE TABLE certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, course_id INTEGER NOT NULL, verification_code TEXT NOT NULL UNIQUE, issued_at TEXT NOT NULL, UNIQUE(user_id, course_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY (course_id) REFERENCES cms_courses(id) ON DELETE RESTRICT)");
    $pdo->exec('CREATE INDEX idx_certificates_user_date ON certificates(user_id, issued_at DESC)');
    $pdo->exec("INSERT INTO achievements (slug, name, description, icon, threshold, metric) VALUES ('first-lesson', 'First Steps', 'Complete your first lesson.', 'bi bi-book', 1, 'lessons_completed'), ('ten-lessons', 'Learning Rhythm', 'Complete ten lessons.', 'bi bi-journal-check', 10, 'lessons_completed'), ('first-quiz', 'Knowledge Check', 'Pass your first quiz.', 'bi bi-patch-check', 1, 'quizzes_passed'), ('five-quizzes', 'Quiz Champion', 'Pass five quizzes.', 'bi bi-trophy', 5, 'quizzes_passed'), ('first-course', 'Course Graduate', 'Complete your first course.', 'bi bi-mortarboard', 1, 'courses_completed'), ('three-courses', 'Lifelong Learner', 'Complete three courses.', 'bi bi-award', 3, 'courses_completed')");
};
