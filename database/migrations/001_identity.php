<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL, PRIMARY KEY (role_id, permission_id), FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL COLLATE NOCASE UNIQUE, password_hash TEXT, google_subject TEXT UNIQUE, avatar_url TEXT, status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'pending')), email_verified_at TEXT, last_login_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE user_roles (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, assigned_at TEXT NOT NULL, PRIMARY KEY (user_id, role_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT)");
    $pdo->exec("CREATE TABLE password_reset_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER, action TEXT NOT NULL, entity_type TEXT, entity_id TEXT, details_json TEXT, ip_address TEXT, created_at TEXT NOT NULL, FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL)");
    $pdo->exec('CREATE INDEX idx_users_status ON users(status)');
    $pdo->exec('CREATE INDEX idx_password_reset_user_expiry ON password_reset_tokens(user_id, expires_at)');
    $pdo->exec('CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at)');
    $now = gmdate('Y-m-d H:i:s');
    $role = $pdo->prepare('INSERT INTO roles (name, slug, created_at) VALUES (:name, :slug, :created_at)');
    $role->execute(['name' => 'Administrator', 'slug' => 'administrator', 'created_at' => $now]);
    $role->execute(['name' => 'Student', 'slug' => 'student', 'created_at' => $now]);
    $permissions = ['manage_users' => 'Manage users', 'manage_content' => 'Manage content', 'manage_commerce' => 'Manage commerce', 'view_reports' => 'View reports', 'manage_settings' => 'Manage settings'];
    $insert = $pdo->prepare('INSERT INTO permissions (name, slug, created_at) VALUES (:name, :slug, :created_at)');
    foreach ($permissions as $slug => $label) $insert->execute(['name' => $label, 'slug' => $slug, 'created_at' => $now]);
    $adminId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'administrator'")->fetchColumn();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) SELECT :role_id, id FROM permissions')->execute(['role_id' => $adminId]);
};
