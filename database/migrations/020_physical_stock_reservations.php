<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE cms_products ADD COLUMN stock_quantity INTEGER CHECK (stock_quantity IS NULL OR stock_quantity >= 0)');
    $pdo->exec('ALTER TABLE cms_products ADD COLUMN stock_reserved INTEGER NOT NULL DEFAULT 0 CHECK (stock_reserved >= 0)');
    $pdo->exec("CREATE TABLE product_stock_reservations (id INTEGER PRIMARY KEY AUTOINCREMENT, order_item_id INTEGER NOT NULL UNIQUE, product_id INTEGER NOT NULL, quantity INTEGER NOT NULL CHECK (quantity > 0), status TEXT NOT NULL DEFAULT 'reserved' CHECK (status IN ('reserved','committed','released')), created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT, FOREIGN KEY (product_id) REFERENCES cms_products(id) ON DELETE RESTRICT)");
    $pdo->exec('CREATE INDEX idx_product_stock_reservations_product ON product_stock_reservations(product_id,status)');
};
