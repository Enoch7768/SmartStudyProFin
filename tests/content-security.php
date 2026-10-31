<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Cms\HtmlSanitizer;

$input = '<p onclick="alert(1)">Keep <strong>formatted text</strong>.</p><script>alert(2)</script><a href="javascript:alert(3)" onclick="alert(4)">unsafe</a><a href="https://example.com" target="_blank">safe link</a><img src="data:image/svg+xml,evil"><iframe src="https://evil.example">frame text</iframe><font><script>alert(5)</script><em>nested text</em></font>';
$output = HtmlSanitizer::clean($input);
foreach (['<script', '<iframe', 'onclick=', 'javascript:', 'data:image', 'alert('] as $forbidden) {
    if (stripos($output, $forbidden) !== false) throw new RuntimeException('Unsafe rich content survived sanitization: ' . $forbidden);
}
if (!str_contains($output, '<strong>formatted text</strong>') || !str_contains($output, '<em>nested text</em>')) throw new RuntimeException('Safe rich text formatting was not preserved.');
if (!str_contains($output, 'href="https://example.com"') || !str_contains($output, 'rel="noopener noreferrer"')) throw new RuntimeException('Safe external links were not preserved securely.');
if (strlen($output) > 524288) throw new RuntimeException('Sanitized content exceeded its size limit.');

$oversized = false;
try {
    HtmlSanitizer::clean(str_repeat('x', 524289));
} catch (RuntimeException) {
    $oversized = true;
}
if (!$oversized) throw new RuntimeException('Oversized CMS content was not rejected.');

echo "PASS script, event handler and unsafe URL removal\nPASS safe rich text and external links preserved\nPASS nested untrusted elements sanitized\nPASS content size limit enforced\n";
