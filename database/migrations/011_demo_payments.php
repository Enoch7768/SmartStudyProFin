<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE payment_demo_settings (id INTEGER PRIMARY KEY CHECK (id = 1), enabled INTEGER NOT NULL DEFAULT 0 CHECK (enabled IN (0, 1)), updated_by INTEGER, updated_at TEXT NOT NULL, FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL)");
    $statement = $pdo->prepare('INSERT INTO payment_demo_settings (id, enabled, updated_at) VALUES (1, 0, :updated_at)');
    $statement->execute(['updated_at' => gmdate('Y-m-d H:i:s')]);
    $pdo->exec("CREATE TABLE demo_payment_events (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, user_id INTEGER NOT NULL, scenario TEXT NOT NULL CHECK (scenario IN ('success', 'failed', 'cancelled')), reference TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT)");
    $pdo->exec('CREATE INDEX idx_demo_payment_events_order ON demo_payment_events(order_id, id DESC)');
};
