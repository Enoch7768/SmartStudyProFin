<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE cms_products ADD COLUMN read_price_ugx INTEGER NOT NULL DEFAULT 0 CHECK (read_price_ugx >= 0)');
    $pdo->exec('ALTER TABLE cms_products ADD COLUMN download_price_ugx INTEGER NOT NULL DEFAULT 0 CHECK (download_price_ugx >= 0)');
    $pdo->exec('UPDATE cms_products SET read_price_ugx = price_ugx, download_price_ugx = price_ugx');
    $pdo->exec("ALTER TABLE order_items ADD COLUMN product_entitlement TEXT NOT NULL DEFAULT 'download' CHECK (product_entitlement IN ('download','read','physical','both'))");
    $pdo->exec('CREATE INDEX idx_order_items_entitlement ON order_items(item_type, product_type, product_entitlement, order_id)');
};
