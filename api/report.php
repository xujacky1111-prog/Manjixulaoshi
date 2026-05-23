<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    respond(['error' => 'Invalid date'], 422);
}

$pdo = db();
$summaryStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(remembered = 1) AS correct,
        SUM(remembered = 0 OR ever_wrong = 1) AS wrong,
        SUM(ever_wrong = 1) AS difficult,
        SUM(is_due_review = 0) AS new_count,
        SUM(is_due_review = 1) AS review_count
     FROM wm_daily_answers
     WHERE study_date = ?'
);
$summaryStmt->execute([$date]);

$answersStmt = $pdo->prepare(
    'SELECT a.word_id, a.word, a.meaning_zh, a.chosen_meaning_zh, a.remembered, a.ever_wrong, a.attempt_count,
            a.is_due_review, a.sequence_no, a.answered_at, w.mastery_score, w.difficulty, COALESCE(r.wrong_streak, 0) AS wrong_streak
     FROM wm_daily_answers a
     JOIN wm_words w ON w.id = a.word_id
     LEFT JOIN wm_reviews r ON r.word_id = a.word_id
     WHERE study_date = ?
     ORDER BY sequence_no ASC, answered_at ASC'
);
$answersStmt->execute([$date]);
$answers = $answersStmt->fetchAll();

$attemptsStmt = $pdo->prepare(
    'SELECT word_id, word, meaning_zh, chosen_meaning_zh, remembered, is_due_review, sequence_no, answered_at
     FROM wm_answer_attempts
     WHERE study_date = ?
     ORDER BY sequence_no ASC, answered_at ASC'
);
$attemptsStmt->execute([$date]);
$attempts = $attemptsStmt->fetchAll();

$dates = $pdo->query(
    'SELECT study_date, COUNT(*) AS total, SUM(remembered = 1) AS correct_count, SUM(remembered = 0 OR ever_wrong = 1) AS wrong_count
     FROM wm_daily_answers
     GROUP BY study_date
     ORDER BY study_date DESC
     LIMIT 30'
)->fetchAll();

respond([
    'date' => $date,
    'summary' => $summaryStmt->fetch() ?: [],
    'answers' => $answers,
    'correct_answers' => array_values(array_filter($answers, static fn(array $item): bool => (int)$item['remembered'] === 1)),
    'wrong_attempts' => array_values(array_filter($attempts, static fn(array $item): bool => (int)$item['remembered'] === 0)),
    'difficult_words' => array_values(array_filter($answers, static fn(array $item): bool => (int)$item['ever_wrong'] === 1 || (int)$item['wrong_streak'] >= 2 || (int)$item['mastery_score'] < 40)),
    'attempts' => $attempts,
    'dates' => $dates
]);
