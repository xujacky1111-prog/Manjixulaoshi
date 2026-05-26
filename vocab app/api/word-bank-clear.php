<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$code = clean_bank_code((string)($body['bank_code'] ?? 'high_school'));

ensure_word_bank(db(), $code);
$stmt = db()->prepare('DELETE FROM wm_words WHERE bank_code = ?');
$stmt->execute([$code]);

respond(['ok' => true, 'bank_code' => $code, 'deleted_words' => $stmt->rowCount()]);
