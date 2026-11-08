<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("ALTER TABLE course_offering_enrolments ADD COLUMN completed_at TEXT");
    $pdo->exec("CREATE TABLE offering_certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, offering_id INTEGER NOT NULL, verification_code TEXT NOT NULL UNIQUE, issued_at TEXT NOT NULL, UNIQUE(user_id, offering_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY (offering_id) REFERENCES course_offerings(id) ON DELETE RESTRICT)");
    $pdo->exec("CREATE INDEX idx_offering_certificates_user_date ON offering_certificates(user_id, issued_at DESC)");
    $pdo->exec("INSERT OR IGNORE INTO roles (name, slug, created_at) VALUES ('Instructor', 'instructor', datetime('now'))");
    $pdo->exec("CREATE TABLE course_offering_instructors (offering_id INTEGER NOT NULL, user_id INTEGER NOT NULL, assigned_by INTEGER NOT NULL, assigned_at TEXT NOT NULL, PRIMARY KEY (offering_id, user_id), FOREIGN KEY (offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (assigned_by) REFERENCES users(id))");
    $pdo->exec("CREATE INDEX idx_course_offering_instructors_user ON course_offering_instructors(user_id, offering_id)");
};
