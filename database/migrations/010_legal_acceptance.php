<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_legal_acceptances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        document TEXT NOT NULL CHECK (document IN ('terms', 'privacy')),
        version TEXT NOT NULL,
        accepted_at TEXT NOT NULL,
        ip_hash TEXT,
        user_agent_hash TEXT,
        UNIQUE(user_id, document, version)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_legal_acceptances_user_date ON user_legal_acceptances(user_id, accepted_at DESC)');
};
