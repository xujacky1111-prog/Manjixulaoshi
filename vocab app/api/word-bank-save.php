<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$code = clean_bank_code((string)($body['code'] ?? $body['bank_code'] ?? 'high_school'));
$title = trim((string)($body['title'] ?? ''));
$description = trim((string)($body['description'] ?? ''));

if ($title === '') {
    $title = strtoupper(str_replace('_', ' ', $code));
}

$stmt = db()->prepare(
    'INSERT INTO wm_word_banks (code, title, description, sort_order)
     VALUES (:code, :title, :description, 100)
     ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description)'
);
$stmt->execute(['code' => $code, 'title' => $title, 'description' => $description]);

respond(['ok' => true, 'bank' => ['code' => $code, 'title' => $title, 'description' => $description]]);
