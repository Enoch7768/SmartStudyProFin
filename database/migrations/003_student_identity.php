<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE student_profiles (user_id INTEGER PRIMARY KEY, phone TEXT NOT NULL DEFAULT '', location TEXT NOT NULL DEFAULT '', bio TEXT NOT NULL DEFAULT '', preferred_theme TEXT NOT NULL DEFAULT 'system' CHECK (preferred_theme IN ('system', 'light', 'dark')), created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
    $pdo->exec("INSERT INTO student_profiles (user_id, created_at, updated_at) SELECT id, created_at, updated_at FROM users");
    $pdo->exec("CREATE TABLE password_reset_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, email_hash TEXT NOT NULL, ip_hash TEXT NOT NULL, requested_at TEXT NOT NULL)");
    $pdo->exec("CREATE INDEX idx_password_reset_requests_lookup ON password_reset_requests(email_hash, ip_hash, requested_at)");
    $pdo->exec("CREATE INDEX idx_student_profiles_location ON student_profiles(location)");
};
