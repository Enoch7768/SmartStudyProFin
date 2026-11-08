<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE order_items ADD COLUMN course_offering_id INTEGER REFERENCES course_offerings(id) ON DELETE SET NULL');
    $pdo->exec("ALTER TABLE order_items ADD COLUMN course_offering_mode TEXT NOT NULL DEFAULT 'legacy'");
    $pdo->exec("ALTER TABLE order_items ADD COLUMN course_offering_title TEXT NOT NULL DEFAULT ''");
    $pdo->exec("CREATE TABLE course_offering_enrolments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, offering_id INTEGER NOT NULL, order_item_id INTEGER UNIQUE, source TEXT NOT NULL CHECK (source IN ('payment','manual','free')), status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','completed','revoked')), created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE, FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL, UNIQUE(user_id, offering_id))");
    $pdo->exec('CREATE INDEX idx_course_offering_enrolments_user ON course_offering_enrolments(user_id,status,offering_id)');
    $pdo->exec('CREATE INDEX idx_order_items_offering ON order_items(course_offering_id,order_id)');
};
