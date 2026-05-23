<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$promptFile = __DIR__ . '/../prompts/text-import.md';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $content = file_exists($promptFile) ? file_get_contents($promptFile) : '';
    respond(['prompt' => $content]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $prompt = (string)($body['prompt'] ?? $_POST['prompt'] ?? '');
    file_put_contents($promptFile, $prompt);
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
