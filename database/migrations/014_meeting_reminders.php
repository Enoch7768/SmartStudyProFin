<?php
declare(strict_types=1);
return static function (PDO $pdo): void {
$pdo->exec("CREATE TABLE meeting_reminders (id INTEGER PRIMARY KEY AUTOINCREMENT,session_id INTEGER NOT NULL,user_id INTEGER NOT NULL,reminder_type TEXT NOT NULL CHECK(reminder_type IN('24h','1h')),sent_at TEXT NOT NULL,UNIQUE(session_id,user_id,reminder_type),FOREIGN KEY(session_id) REFERENCES course_sessions(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)");
$pdo->exec("CREATE INDEX idx_meeting_reminders_sent ON meeting_reminders(sent_at)");
};
