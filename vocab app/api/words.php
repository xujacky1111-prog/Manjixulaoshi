<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $stmt = $pdo->prepare('SELECT * FROM wm_words WHERE word LIKE :q OR meaning_zh LIKE :q ORDER BY created_at DESC LIMIT 500');
        $stmt->execute(['q' => '%' . $q . '%']);
    } else {
        $stmt = $pdo->query('SELECT * FROM wm_words ORDER BY created_at DESC LIMIT 500');
    }
    respond(['words' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = clean_word_payload(json_body() ?: $_POST);
    if ($data['word'] === '' || $data['meaning_zh'] === '') {
        respond(['error' => 'word and meaning_zh are required'], 422);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO wm_words (word, part_of_speech, meaning_zh, example_en, source, difficulty)
         VALUES (:word, :part_of_speech, :meaning_zh, :example_en, :source, :difficulty)
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
        'UPDATE wm_words SET word=:word, part_of_speech=:part_of_speech, meaning_zh=:meaning_zh, example_en=:example_en, source=:source, difficulty=:difficulty WHERE id=:id'
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
