<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Google sign-in is not enabled in this installation. Use your SmartStudyPro email and password.']);
