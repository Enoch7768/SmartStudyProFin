<?php
declare(strict_types=1);
return static function (PDO $pdo): void {
$pdo->exec("CREATE TABLE contact_messages (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NOT NULL,subject TEXT NOT NULL,message TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'new' CHECK(status IN('new','read','archived')),ip_hash TEXT NOT NULL,created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
$pdo->exec('CREATE INDEX idx_contact_messages_status_created ON contact_messages(status,created_at DESC,id DESC)');
$pdo->exec('CREATE INDEX idx_contact_messages_ip_created ON contact_messages(ip_hash,created_at)');
};
