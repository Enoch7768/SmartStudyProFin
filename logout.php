<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Security;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(405);
    exit('A valid form submission is required to sign out.');
}

Auth::logout();
Security::redirect('index.php');
