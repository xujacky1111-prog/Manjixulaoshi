<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$code = clean_bank_code((string)($_GET['code'] ?? 'high_school'));
$bankStmt = db()->prepare(
    'SELECT b.code, b.title, b.description, COUNT(w.id) AS word_count
     FROM wm_word_banks b
     LEFT JOIN wm_words w ON w.bank_code = b.code
     WHERE b.code = ?
     GROUP BY b.code, b.title, b.description'
);
$bankStmt->execute([$code]);
$bank = $bankStmt->fetch();
if (!$bank) {
    respond(['error' => 'word bank not found'], 404);
}

$wordStmt = db()->prepare(
    'SELECT word, part_of_speech, meaning_zh, example_en, difficulty
     FROM wm_words
     WHERE bank_code = ?
     ORDER BY word ASC'
);
$wordStmt->execute([$code]);

respond([
    'bank' => $bank,
    'words' => $wordStmt->fetchAll()
]);
