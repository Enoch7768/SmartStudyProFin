<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE refund_items (id INTEGER PRIMARY KEY AUTOINCREMENT, refund_id INTEGER NOT NULL, order_item_id INTEGER NOT NULL, amount_ugx INTEGER NOT NULL CHECK (amount_ugx > 0), created_at TEXT NOT NULL, UNIQUE(refund_id, order_item_id), FOREIGN KEY (refund_id) REFERENCES refunds(id) ON DELETE RESTRICT, FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT)");
    $pdo->exec('CREATE INDEX idx_refund_items_order_item ON refund_items(order_item_id, refund_id)');
};
