<?php

require_once 'cms-init.php';
require_once 'auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$answers = $input['answers'] ?? []; 

if (!is_array($answers) || empty($answers) || !function_exists('cockpit')) {
    echo json_encode(['error' => 'No answers submitted']);
    exit;
}

$score = 0;
$total = 0;
$results = [];

foreach ($answers as $quizId => $selected) {
    $quiz = cockpit('content')->item('Quizzes', ['_id' => $quizId]);
    if (!$quiz) continue;

    $total++;
    $correct = trim($quiz['Correct Answer'] ?? '');
    $isCorrect = (trim((string) $selected) === $correct);
    if ($isCorrect) $score++;

    $results[$quizId] = [
        'correct' => $isCorrect,
        'correct_answer' => $correct,
    ];
}

echo json_encode([
    'score' => $score,
    'total' => $total,
    'results' => $results,
]);