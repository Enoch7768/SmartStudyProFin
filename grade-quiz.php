<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
http_response_code(410);
echo json_encode(['error' => 'The legacy quiz endpoint has been retired. Submit quizzes from the authenticated SmartStudyPro learning portal.']);
