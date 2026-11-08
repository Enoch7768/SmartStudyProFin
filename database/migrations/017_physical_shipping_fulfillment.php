<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE shipping_zones (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, country_code TEXT NOT NULL DEFAULT 'UG', region_name TEXT NOT NULL DEFAULT '', is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)), created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    $pdo->exec('CREATE INDEX idx_shipping_zones_match ON shipping_zones(country_code, region_name, is_active)');
    $pdo->exec("CREATE TABLE shipping_methods (id INTEGER PRIMARY KEY AUTOINCREMENT, zone_id INTEGER NOT NULL, name TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', fee_ugx INTEGER NOT NULL CHECK (fee_ugx >= 0), estimated_days_min INTEGER NOT NULL DEFAULT 1 CHECK (estimated_days_min >= 0), estimated_days_max INTEGER NOT NULL DEFAULT 3 CHECK (estimated_days_max >= estimated_days_min), is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)), sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE RESTRICT)");
    $pdo->exec('CREATE INDEX idx_shipping_methods_zone ON shipping_methods(zone_id, is_active, sort_order, id)');
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_method_id INTEGER REFERENCES shipping_methods(id) ON DELETE RESTRICT");
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_method_name TEXT NOT NULL DEFAULT ''");
    $pdo->exec('ALTER TABLE orders ADD COLUMN delivery_fee_ugx INTEGER NOT NULL DEFAULT 0 CHECK (delivery_fee_ugx >= 0)');
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_name TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_phone TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_address TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_city TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_region TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_country TEXT NOT NULL DEFAULT 'UG'");
    $pdo->exec("ALTER TABLE orders ADD COLUMN fulfillment_status TEXT NOT NULL DEFAULT 'not_required' CHECK (fulfillment_status IN ('not_required','pending','processing','shipped','delivered','cancelled'))");
    $pdo->exec("ALTER TABLE orders ADD COLUMN carrier_name TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_reference TEXT NOT NULL DEFAULT ''");
    $pdo->exec('ALTER TABLE orders ADD COLUMN shipped_at TEXT');
    $pdo->exec('ALTER TABLE orders ADD COLUMN delivered_at TEXT');
    $pdo->exec('CREATE INDEX idx_orders_fulfillment ON orders(fulfillment_status, created_at DESC)');
};
