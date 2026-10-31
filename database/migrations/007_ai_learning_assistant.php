<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE ai_assistant_conversations (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE ai_assistant_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER NOT NULL, role TEXT NOT NULL CHECK (role IN ('user', 'assistant')), content TEXT NOT NULL, sources_json TEXT NOT NULL DEFAULT '[]', created_at TEXT NOT NULL, FOREIGN KEY (conversation_id) REFERENCES ai_assistant_conversations(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE ai_assistant_limits (user_id INTEGER PRIMARY KEY, window_started_at TEXT NOT NULL, request_count INTEGER NOT NULL DEFAULT 0 CHECK (request_count >= 0), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
    $pdo->exec('CREATE INDEX idx_ai_conversations_user_updated ON ai_assistant_conversations(user_id, updated_at DESC)');
    $pdo->exec('CREATE INDEX idx_ai_messages_conversation ON ai_assistant_messages(conversation_id, id)');
};