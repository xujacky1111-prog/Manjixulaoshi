<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$bankCode = clean_bank_code((string)($body['bank_code'] ?? 'high_school'));
$text = (string)($body['text'] ?? '');
$rows = parse_import_text($text, $bankCode);

if ($rows === []) {
    respond(['imported' => 0, 'skipped' => 0, 'message' => '没有识别到单词。']);
}

$pdo = db();
ensure_word_bank($pdo, $bankCode);
$stmt = $pdo->prepare(
    'INSERT INTO wm_words (bank_code, word, part_of_speech, meaning_zh, example_en, source, difficulty)
     VALUES (:bank_code, :word, :part_of_speech, :meaning_zh, :example_en, :source, :difficulty)
     ON DUPLICATE KEY UPDATE part_of_speech=VALUES(part_of_speech), meaning_zh=VALUES(meaning_zh), example_en=VALUES(example_en), source=VALUES(source), difficulty=VALUES(difficulty)'
);

$imported = 0;
foreach ($rows as $row) {
    $stmt->execute($row);
    $imported += 1;
}

respond(['imported' => $imported, 'skipped' => max(0, count(preg_split('/\R/', $text)) - $imported)]);

function parse_import_text(string $text, string $bankCode): array
{
    $rows = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || preg_match('/^\|?\s*-{3,}/', $line) || stripos($line, 'word') === 0 || preg_match('/^\|\s*word\s*\|/i', $line)) {
            continue;
        }

        $parts = [];
        if (strpos($line, '|') !== false) {
            $parts = array_map('trim', explode('|', trim($line, " \t\n\r\0\x0B|")));
        } elseif (strpos($line, "\t") !== false) {
            $parts = array_map('trim', explode("\t", $line));
        } elseif (strpos($line, ',') !== false) {
            $parts = str_getcsv($line);
            $parts = array_map('trim', $parts);
        }

        if (count($parts) < 2) {
            continue;
        }

        $word = strtolower(trim((string)($parts[0] ?? '')));
        $pos = trim((string)($parts[1] ?? ''));
        $meaning = trim((string)($parts[2] ?? ''));
        $example = trim((string)($parts[3] ?? ''));

        if (count($parts) === 2) {
            $meaning = $pos;
            $pos = '';
        }

        if ($word === '' || $meaning === '') {
            continue;
        }

        $rows[$word] = [
            'word' => $word,
            'bank_code' => $bankCode,
            'part_of_speech' => $pos,
            'meaning_zh' => $meaning,
            'example_en' => $example,
            'source' => 'web_import',
            'difficulty' => 1
        ];
    }

    return array_values($rows);
}
