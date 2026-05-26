<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $bankCode = clean_bank_code((string)($_GET['bank_code'] ?? 'high_school'));
    if ($q !== '') {
        $stmt = $pdo->prepare('SELECT * FROM wm_words WHERE bank_code = :bank_code AND (word LIKE :q OR meaning_zh LIKE :q) ORDER BY created_at DESC LIMIT 500');
        $stmt->execute(['bank_code' => $bankCode, 'q' => '%' . $q . '%']);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM wm_words WHERE bank_code = ? ORDER BY created_at DESC LIMIT 500');
        $stmt->execute([$bankCode]);
    }
    respond(['words' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = clean_word_payload(json_body() ?: $_POST);
    if ($data['word'] === '' || $data['meaning_zh'] === '') {
        respond(['error' => 'word and meaning_zh are required'], 422);
    }
    ensure_word_bank($pdo, $data['bank_code']);
    $stmt = $pdo->prepare(
        'INSERT INTO wm_words (bank_code, word, part_of_speech, meaning_zh, example_en, source, difficulty)
         VALUES (:bank_code, :word, :part_of_speech, :meaning_zh, :example_en, :source, :difficulty)
         ON DUPLICATE KEY UPDATE part_of_speech=VALUES(part_of_speech), meaning_zh=VALUES(meaning_zh), example_en=VALUES(example_en), source=VALUES(source), difficulty=VALUES(difficulty)'
    );
    $stmt->execute($data);
    respond(['ok' => true]);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    $data = clean_word_payload($body);
    if ($id <= 0 || $data['word'] === '' || $data['meaning_zh'] === '') {
        respond(['error' => 'id, word and meaning_zh are required'], 422);
    }
    $data['id'] = $id;
    $stmt = $pdo->prepare(
        'UPDATE wm_words SET bank_code=:bank_code, word=:word, part_of_speech=:part_of_speech, meaning_zh=:meaning_zh, example_en=:example_en, source=:source, difficulty=:difficulty WHERE id=:id'
    );
    $stmt->execute($data);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(['error' => 'id is required'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM wm_words WHERE id = ?');
    $stmt->execute([$id]);
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
