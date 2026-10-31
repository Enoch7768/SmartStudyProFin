<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) Security::redirect('login.php?redirect=admin%2Fcms.php');
if (!Auth::hasRole($pdo, (int) $user['id'], 'administrator')) {
    http_response_code(403);
    exit('You do not have permission to access the administrator area.');
}
Security::redirect('admin/cms.php');
