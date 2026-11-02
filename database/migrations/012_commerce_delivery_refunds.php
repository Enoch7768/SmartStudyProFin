<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE cms_product_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, media_asset_id INTEGER NOT NULL, asset_role TEXT NOT NULL CHECK (asset_role IN ('gallery', 'download')), sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, UNIQUE(product_id, media_asset_id, asset_role), FOREIGN KEY (product_id) REFERENCES cms_products(id) ON DELETE CASCADE, FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE CASCADE)");
    $pdo->exec('CREATE INDEX idx_cms_product_assets_product ON cms_product_assets(product_id, asset_role, sort_order, id)');
    $pdo->exec("CREATE TABLE order_item_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, order_item_id INTEGER NOT NULL, media_asset_id INTEGER, storage_path TEXT NOT NULL, original_name TEXT NOT NULL, mime_type TEXT NOT NULL, file_size INTEGER NOT NULL CHECK (file_size > 0), checksum_sha256 TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE, FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL)");
    $pdo->exec('CREATE INDEX idx_order_item_assets_item ON order_item_assets(order_item_id, sort_order, id)');
    $pdo->exec('CREATE UNIQUE INDEX idx_payments_one_active_provider ON payments(order_id, provider) WHERE status IN (\'created\', \'pending\')');
    $pdo->exec('ALTER TABLE refunds ADD COLUMN requested_by INTEGER REFERENCES users(id) ON DELETE RESTRICT');
    $pdo->exec('ALTER TABLE refunds ADD COLUMN resolved_by INTEGER REFERENCES users(id) ON DELETE RESTRICT');
    $pdo->exec('ALTER TABLE refunds ADD COLUMN resolution_reference TEXT');
    $pdo->exec('ALTER TABLE refunds ADD COLUMN resolved_at TEXT');
    $pdo->exec("ALTER TABLE refunds ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
    $pdo->exec("CREATE TABLE refund_audit_events (id INTEGER PRIMARY KEY AUTOINCREMENT, refund_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, action TEXT NOT NULL CHECK (action IN ('requested', 'succeeded', 'failed')), amount_ugx INTEGER NOT NULL CHECK (amount_ugx > 0), reason TEXT NOT NULL DEFAULT '', reference TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, FOREIGN KEY (refund_id) REFERENCES refunds(id) ON DELETE RESTRICT, FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT)");
    $pdo->exec('CREATE INDEX idx_refund_audit_refund ON refund_audit_events(refund_id, id)');
    $pdo->exec('CREATE UNIQUE INDEX idx_refunds_provider_reference ON refunds(provider_refund_id) WHERE provider_refund_id IS NOT NULL AND provider_refund_id != ""');
};
