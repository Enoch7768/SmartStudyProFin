<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("ALTER TABLE course_offering_enrolments ADD COLUMN completed_at TEXT");
    $pdo->exec("CREATE TABLE offering_certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, offering_id INTEGER NOT NULL, verification_code TEXT NOT NULL UNIQUE, issued_at TEXT NOT NULL, UNIQUE(user_id, offering_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY (offering_id) REFERENCES course_offerings(id) ON DELETE RESTRICT)");
    $pdo->exec("CREATE INDEX idx_offering_certificates_user_date ON offering_certificates(user_id, issued_at DESC)");
};
