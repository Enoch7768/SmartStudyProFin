<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$application = require dirname(__DIR__) . '/bootstrap/app.php';

use App\Release\DiagnosticsService;

$report = (new DiagnosticsService($application['root']))->report();
if (in_array('--json', $argv, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    printf("SmartStudyPro %s diagnostics\n", $report['version']);
    foreach ($report['checks'] as $label => $passed) printf("[%s] %s\n", $passed ? 'PASS' : 'FAIL', $label);
    printf("Migrations pending: %d\n", count($report['pending_migrations']));
    if ($report['free_disk_bytes'] !== null) printf("Free disk space: %.2f GB\n", $report['free_disk_bytes'] / 1073741824);
}
exit($report['healthy'] ? 0 : 1);
