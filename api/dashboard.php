<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$pdo = db();
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    respond(['error' => 'Invalid date'], 422);
}

$dailyStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS words_seen,
        SUM(d.remembered = 1) AS correct_attempts,
        SUM(d.remembered = 0 OR d.ever_wrong = 1) AS wrong_attempts,
        COALESCE(t.total_attempts, COUNT(*)) AS total_attempts
     FROM wm_daily_answers d
     LEFT JOIN (
        SELECT study_date, COUNT(*) AS total_attempts
        FROM wm_answer_attempts
        WHERE study_date = ?
        GROUP BY study_date
     ) t ON t.study_date = d.study_date
     WHERE d.study_date = ?
     GROUP BY d.study_date, t.total_attempts'
);
$dailyStmt->execute([$date, $date]);

$trend = $pdo->query(
    'SELECT
        d.study_date,
        COUNT(*) AS words_seen,
        SUM(d.remembered = 1) AS correct_attempts,
        SUM(d.remembered = 0 OR d.ever_wrong = 1) AS wrong_attempts,
        COALESCE(t.total_attempts, COUNT(*)) AS total_attempts
     FROM wm_daily_answers d
     LEFT JOIN (
        SELECT study_date, COUNT(*) AS total_attempts
        FROM wm_answer_attempts
        GROUP BY study_date
     ) t ON t.study_date = d.study_date
     GROUP BY d.study_date, t.total_attempts
     ORDER BY d.study_date DESC
     LIMIT 14'
)->fetchAll();

$wrongLeaderboard = $pdo->query(
    'SELECT
        w.word,
        w.meaning_zh,
        w.part_of_speech,
        w.mastery_score,
        COUNT(*) AS wrong_count,
        MAX(a.answered_at) AS last_wrong_at
     FROM wm_answer_attempts a
     JOIN wm_words w ON w.id = a.word_id
     WHERE a.remembered = 0
     GROUP BY a.word_id, w.word, w.meaning_zh, w.part_of_speech, w.mastery_score
     ORDER BY wrong_count DESC, w.mastery_score ASC, w.word ASC
     LIMIT 30'
)->fetchAll();

$knownWords = $pdo->query(
    'SELECT w.word, w.meaning_zh, w.part_of_speech, w.mastery_score, COALESCE(r.correct_streak, 0) AS correct_streak
     FROM wm_words w
     LEFT JOIN wm_reviews r ON r.word_id = w.id
     WHERE w.mastery_score >= 80 AND COALESCE(r.wrong_streak, 0) = 0
     ORDER BY w.mastery_score DESC, correct_streak DESC, w.word ASC
     LIMIT 30'
)->fetchAll();

$untested = $pdo->query(
    'SELECT w.word, w.meaning_zh, w.part_of_speech, w.mastery_score
     FROM wm_words w
     LEFT JOIN wm_reviews r ON r.word_id = w.id
     LEFT JOIN wm_answer_attempts a ON a.word_id = w.id
     WHERE a.id IS NULL AND r.id IS NULL AND w.mastery_score = 0
     ORDER BY w.word ASC
     LIMIT 30'
)->fetchAll();

$statusStmt = $pdo->query(
    'SELECT
        COUNT(*) AS total_words,
        SUM(COALESCE(t.total_attempts, 0) = 0 AND r.id IS NULL AND w.mastery_score = 0) AS untested_words,
        SUM(w.mastery_score >= 80 AND COALESCE(r.wrong_streak, 0) = 0) AS mastered_words,
        SUM(COALESCE(r.wrong_streak, 0) >= 2 OR COALESCE(t.total_wrong, 0) >= 2 OR (COALESCE(t.total_attempts, 0) > 0 AND w.mastery_score < 40)) AS work_words,
        SUM(COALESCE(t.total_attempts, 0) > 0 AND w.mastery_score BETWEEN 40 AND 79 AND COALESCE(r.wrong_streak, 0) < 2 AND COALESCE(t.total_wrong, 0) < 2) AS learning_words
     FROM wm_words w
     LEFT JOIN wm_reviews r ON r.word_id = w.id
     LEFT JOIN (
        SELECT word_id, COUNT(*) AS total_attempts, SUM(remembered = 0) AS total_wrong
        FROM wm_answer_attempts
        GROUP BY word_id
     ) t ON t.word_id = w.id'
);

respond([
    'date' => $date,
    'daily' => $dailyStmt->fetch() ?: [],
    'trend' => $trend,
    'status_summary' => $statusStmt->fetch() ?: [],
    'wrong_leaderboard' => $wrongLeaderboard,
    'known_words' => $knownWords,
    'untested_words' => $untested
]);
