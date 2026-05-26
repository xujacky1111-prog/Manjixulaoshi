<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$bankCode = clean_bank_code((string)($_GET['bank_code'] ?? 'high_school'));
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$limit = max(20, min(500, (int)($_GET['limit'] ?? 100)));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    respond(['error' => 'Invalid date'], 422);
}

$where = ['w.bank_code = :bank_code'];
$params = ['date' => $date, 'bank_code' => $bankCode];
if ($q !== '') {
    $where[] = '(w.word LIKE :q OR w.meaning_zh LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$having = '';
if ($status === 'mastered') {
    $having = 'HAVING status = "mastered"';
} elseif ($status === 'work') {
    $having = 'HAVING status = "work_on_it"';
} elseif ($status === 'untested') {
    $having = 'HAVING status = "untested"';
} elseif ($status === 'learning') {
    $having = 'HAVING status = "learning"';
} elseif ($status === 'wrong_today') {
    $having = 'HAVING wrong_today = 1';
} elseif ($status === 'correct_today') {
    $having = 'HAVING correct_today = 1';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "
    SELECT
        w.id,
        w.bank_code,
        w.word,
        w.part_of_speech,
        w.meaning_zh,
        w.example_en,
        w.difficulty,
        w.mastery_score,
        w.last_mastered_at,
        COALESCE(r.correct_streak, 0) AS correct_streak,
        COALESCE(r.wrong_streak, 0) AS wrong_streak,
        r.next_review_date,
        COALESCE(t.total_attempts, 0) AS total_attempts,
        COALESCE(t.total_wrong, 0) AS total_wrong,
        COALESCE(t.total_correct, 0) AS total_correct,
        COALESCE(a.remembered, NULL) AS remembered_today,
        COALESCE(a.ever_wrong, 0) AS wrong_today,
        CASE WHEN a.remembered = 1 THEN 1 ELSE 0 END AS correct_today,
        COALESCE(a.attempt_count, 0) AS attempts_today,
        CASE
            WHEN COALESCE(t.total_attempts, 0) = 0 AND r.id IS NULL AND w.mastery_score = 0 THEN 'untested'
            WHEN w.mastery_score >= 80 AND COALESCE(r.wrong_streak, 0) = 0 THEN 'mastered'
            WHEN COALESCE(a.ever_wrong, 0) = 1 OR COALESCE(r.wrong_streak, 0) >= 2 OR COALESCE(t.total_wrong, 0) >= 2 OR (COALESCE(t.total_attempts, 0) > 0 AND w.mastery_score < 40) THEN 'work_on_it'
            ELSE 'learning'
        END AS status
    FROM wm_words w
    LEFT JOIN wm_reviews r ON r.word_id = w.id
    LEFT JOIN wm_daily_answers a ON a.word_id = w.id AND a.study_date = :date
    LEFT JOIN (
        SELECT
            word_id,
            COUNT(*) AS total_attempts,
            SUM(remembered = 0) AS total_wrong,
            SUM(remembered = 1) AS total_correct
        FROM wm_answer_attempts
        GROUP BY word_id
    ) t ON t.word_id = w.id
    $whereSql
    $having
    ORDER BY
        FIELD(status, 'untested', 'work_on_it', 'learning', 'mastered'),
        wrong_today DESC,
        total_wrong DESC,
        w.mastery_score ASC,
        w.difficulty DESC,
        w.word ASC
    LIMIT $limit
";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$words = $stmt->fetchAll();

$summaryStmt = db()->prepare("
    SELECT
        COUNT(*) AS total_words,
        SUM(COALESCE(t.total_attempts, 0) = 0 AND r.id IS NULL AND w.mastery_score = 0) AS untested_words,
        SUM(w.mastery_score >= 80 AND COALESCE(r.wrong_streak, 0) = 0) AS mastered_words,
        SUM(COALESCE(a.ever_wrong, 0) = 1 OR COALESCE(r.wrong_streak, 0) >= 2 OR COALESCE(t.total_wrong, 0) >= 2 OR (COALESCE(t.total_attempts, 0) > 0 AND w.mastery_score < 40)) AS work_words,
        SUM(COALESCE(t.total_attempts, 0) > 0 AND NOT (w.mastery_score >= 80 AND COALESCE(r.wrong_streak, 0) = 0) AND NOT (COALESCE(a.ever_wrong, 0) = 1 OR COALESCE(r.wrong_streak, 0) >= 2 OR COALESCE(t.total_wrong, 0) >= 2 OR w.mastery_score < 40)) AS learning_words,
        SUM(a.remembered = 1) AS correct_today,
        SUM(a.ever_wrong = 1 OR a.remembered = 0) AS wrong_today,
        SUM(a.word_id IS NOT NULL) AS studied_today
    FROM wm_words w
    LEFT JOIN wm_reviews r ON r.word_id = w.id
    LEFT JOIN wm_daily_answers a ON a.word_id = w.id AND a.study_date = ?
    LEFT JOIN (
        SELECT
            word_id,
            COUNT(*) AS total_attempts,
            SUM(remembered = 0) AS total_wrong
        FROM wm_answer_attempts
        GROUP BY word_id
    ) t ON t.word_id = w.id
    WHERE w.bank_code = ?
");
$summaryStmt->execute([$date, $bankCode]);

respond([
    'date' => $date,
    'summary' => $summaryStmt->fetch() ?: [],
    'words' => $words
]);
