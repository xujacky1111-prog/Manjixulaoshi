<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    $bankCode = clean_bank_code((string)($_GET['bank_code'] ?? 'high_school'));
    $dailyTarget = 80;
    $newLimit = 80;
    $reviewLimit = 80;
    $today = date('Y-m-d');

    $statsStmt = $pdo->prepare(
        'SELECT
            SUM(remembered = 1) AS completed,
            SUM(remembered = 1) AS correct,
            SUM(ever_wrong = 1 OR remembered = 0) AS wrong,
            SUM(remembered = 1 AND is_due_review = 0) AS new_done,
            SUM(remembered = 1 AND is_due_review = 1) AS review_done,
            SUM(remembered = 0) AS pending_wrong,
            MAX(sequence_no) AS last_sequence,
            SUM(ever_wrong = 1) AS difficult
         FROM wm_daily_answers
         WHERE study_date = ?'
    );
    $statsStmt->execute([$today]);
    $stats = $statsStmt->fetch() ?: [];
    $completed = (int)($stats['completed'] ?? 0);
    $newDone = (int)($stats['new_done'] ?? 0);
    $reviewDone = (int)($stats['review_done'] ?? 0);
    $pendingWrong = (int)($stats['pending_wrong'] ?? 0);
    $remainingTotal = max(0, $dailyTarget - $completed);
    $remainingReviews = min($reviewLimit, $remainingTotal + $pendingWrong);

    $reviewStmt = $pdo->prepare(
        'SELECT w.*, r.next_review_date, r.interval_days, r.correct_streak, r.wrong_streak, r.is_new, 1 AS is_due_review
         FROM wm_words w
         JOIN wm_reviews r ON r.word_id = w.id
         WHERE w.bank_code = :bank_code AND r.next_review_date <= :today AND r.is_new = 0
           AND NOT EXISTS (
               SELECT 1 FROM wm_daily_answers a
               WHERE a.word_id = w.id AND a.study_date = :today_answered AND a.remembered = 1
           )
         ORDER BY
           EXISTS (
               SELECT 1 FROM wm_daily_answers a2
               WHERE a2.word_id = w.id AND a2.study_date = :today_wrong AND a2.remembered = 0
           ) DESC,
           r.wrong_streak DESC,
           RAND()
         LIMIT ' . $remainingReviews
    );
    $reviewStmt->execute(['bank_code' => $bankCode, 'today' => $today, 'today_answered' => $today, 'today_wrong' => $today]);
    $dueReviews = $reviewStmt->fetchAll();
    $remainingNew = min(max(0, $newLimit - $newDone), max(0, $remainingTotal - count($dueReviews)));

    $dueIds = array_map(static fn(array $row): int => (int)$row['id'], $dueReviews);
    $newSql = 'SELECT w.*, r.next_review_date, r.interval_days, r.correct_streak, r.wrong_streak, COALESCE(r.is_new, 1) AS is_new, 0 AS is_due_review
               FROM wm_words w
               LEFT JOIN wm_reviews r ON r.word_id = w.id
               WHERE w.bank_code = ?
                 AND (r.id IS NULL OR r.is_new = 1)
                 AND NOT EXISTS (
                     SELECT 1 FROM wm_daily_answers a
                     WHERE a.word_id = w.id AND a.study_date = ? AND a.remembered = 1
                 )';
    if ($dueIds !== []) {
        $newSql .= ' AND w.id NOT IN (' . implode(',', array_fill(0, count($dueIds), '?')) . ')';
    }
    $newSql .= ' ORDER BY RAND() LIMIT ' . $remainingNew;
    $newStmt = $pdo->prepare($newSql);
    $newStmt->execute(array_merge([$bankCode, $today], $dueIds));
    $newWords = $newStmt->fetchAll();

    $all = array_merge($dueReviews, $newWords);
    shuffle($all);
    $all = attach_options($pdo, $all);

    respond([
        'due_reviews' => $dueReviews,
        'new_words' => $newWords,
        'all' => $all,
        'progress' => [
            'date' => $today,
            'total' => $completed,
            'correct' => (int)($stats['correct'] ?? 0),
            'wrong' => (int)($stats['wrong'] ?? 0),
            'new_done' => $newDone,
            'review_done' => $reviewDone,
            'pending_wrong' => $pendingWrong,
            'difficult' => (int)($stats['difficult'] ?? 0),
            'last_sequence' => (int)($stats['last_sequence'] ?? 0),
            'daily_target' => $dailyTarget,
            'daily_new_limit' => $newLimit,
            'daily_review_limit' => $reviewLimit
        ]
    ]);
}

if ($method === 'POST') {
    $body = json_body();
    $wordId = (int)($body['word_id'] ?? 0);
    $chosenMeaning = trim((string)($body['chosen_meaning_zh'] ?? ''));
    $sequenceNo = max(1, (int)($body['sequence_no'] ?? 1));
    if ($wordId <= 0) {
        respond(['error' => 'word_id is required'], 422);
    }

    $wordStmt = $pdo->prepare(
        'SELECT w.*, r.is_new
         FROM wm_words w
         LEFT JOIN wm_reviews r ON r.word_id = w.id
         WHERE w.id = ?'
    );
    $wordStmt->execute([$wordId]);
    $word = $wordStmt->fetch();
    if (!$word) {
        respond(['error' => 'word not found'], 404);
    }
    if ($chosenMeaning === '') {
        respond(['error' => 'chosen_meaning_zh is required'], 422);
    }

    $existingStmt = $pdo->prepare('SELECT * FROM wm_reviews WHERE word_id = ?');
    $existingStmt->execute([$wordId]);
    $existing = $existingStmt->fetch() ?: null;
    $isDueReview = $existing && (int)$existing['is_new'] === 0 ? 1 : 0;
    $remembered = normalize_answer($chosenMeaning) === normalize_answer((string)$word['meaning_zh']);

    $schedule = next_schedule($existing, $remembered);
    $mastery = next_mastery((int)($word['mastery_score'] ?? 0), $remembered);
    $difficulty = next_difficulty((int)($word['difficulty'] ?? 1), $remembered);

    $attemptStmt = $pdo->prepare(
        'INSERT INTO wm_answer_attempts (study_date, word_id, word, meaning_zh, chosen_meaning_zh, remembered, is_due_review, sequence_no)
         VALUES (CURDATE(), :word_id, :word, :meaning_zh, :chosen_meaning_zh, :remembered, :is_due_review, :sequence_no)'
    );
    $attemptStmt->execute([
        'word_id' => $wordId,
        'word' => $word['word'],
        'meaning_zh' => $word['meaning_zh'],
        'chosen_meaning_zh' => $chosenMeaning,
        'remembered' => $remembered ? 1 : 0,
        'is_due_review' => $isDueReview,
        'sequence_no' => $sequenceNo
    ]);

    $answerStmt = $pdo->prepare(
        'INSERT INTO wm_daily_answers (study_date, word_id, word, meaning_zh, chosen_meaning_zh, remembered, ever_wrong, attempt_count, is_due_review, sequence_no)
         VALUES (CURDATE(), :word_id, :word, :meaning_zh, :chosen_meaning_zh, :remembered, :ever_wrong, 1, :is_due_review, :sequence_no)
         ON DUPLICATE KEY UPDATE
           chosen_meaning_zh=VALUES(chosen_meaning_zh),
           remembered=VALUES(remembered),
           ever_wrong=GREATEST(ever_wrong, VALUES(ever_wrong)),
           attempt_count=attempt_count + 1,
           is_due_review=VALUES(is_due_review),
           sequence_no=VALUES(sequence_no),
           answered_at=CURRENT_TIMESTAMP'
    );
    $answerStmt->execute([
        'word_id' => $wordId,
        'word' => $word['word'],
        'meaning_zh' => $word['meaning_zh'],
        'chosen_meaning_zh' => $chosenMeaning,
        'remembered' => $remembered ? 1 : 0,
        'ever_wrong' => $remembered ? 0 : 1,
        'is_due_review' => $isDueReview,
        'sequence_no' => $sequenceNo
    ]);

    $wordUpdateStmt = $pdo->prepare(
        'UPDATE wm_words
         SET mastery_score = :mastery_score,
             difficulty = :difficulty,
             last_mastered_at = CASE WHEN :mastery_score >= 80 THEN NOW() ELSE last_mastered_at END
         WHERE id = :word_id'
    );
    $wordUpdateStmt->execute([
        'mastery_score' => $mastery,
        'difficulty' => $difficulty,
        'word_id' => $wordId
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO wm_reviews (word_id, next_review_date, interval_days, correct_streak, wrong_streak, last_reviewed_at, is_new)
         VALUES (:word_id, :next_review_date, :interval_days, :correct_streak, :wrong_streak, NOW(), 0)
         ON DUPLICATE KEY UPDATE next_review_date=VALUES(next_review_date), interval_days=VALUES(interval_days), correct_streak=VALUES(correct_streak), wrong_streak=VALUES(wrong_streak), last_reviewed_at=NOW(), is_new=0'
    );
    $stmt->execute([
        'word_id' => $wordId,
        'next_review_date' => $schedule['next_review_date'],
        'interval_days' => $schedule['interval_days'],
        'correct_streak' => $schedule['correct_streak'],
        'wrong_streak' => $schedule['wrong_streak']
    ]);

    respond([
        'ok' => true,
        'correct' => $remembered,
        'correct_meaning_zh' => $word['meaning_zh'],
        'mastery_score' => $mastery,
        'schedule' => $schedule
    ]);
}

respond(['error' => 'Method not allowed'], 405);

function next_schedule(?array $existing, bool $remembered): array
{
    if (!$remembered) {
        return [
            'next_review_date' => date('Y-m-d'),
            'interval_days' => 0,
            'correct_streak' => 0,
            'wrong_streak' => (int)($existing['wrong_streak'] ?? 0) + 1
        ];
    }

    $intervals = [1, 3, 7, 14, 30];
    $current = (int)($existing['interval_days'] ?? 0);
    $next = 30;
    foreach ($intervals as $interval) {
        if ($interval > $current) {
            $next = $interval;
            break;
        }
    }

    return [
        'next_review_date' => date('Y-m-d', strtotime('+' . $next . ' days')),
        'interval_days' => $next,
        'correct_streak' => (int)($existing['correct_streak'] ?? 0) + 1,
        'wrong_streak' => 0
    ];
}

function attach_options(PDO $pdo, array $words): array
{
    if ($words === []) {
        return [];
    }

    foreach ($words as &$word) {
        $correct = (string)$word['meaning_zh'];
        $options = [$correct];
        $pool = option_pool($pdo, $word);
        foreach ($pool as $meaning) {
            $meaning = (string)$meaning;
            if (normalize_answer($meaning) === normalize_answer($correct)) {
                continue;
            }
            $options[] = $meaning;
            if (count($options) >= 4) {
                break;
            }
        }
        while (count($options) < 4) {
            $options[] = '以上都不对';
        }
        shuffle($options);
        $word['options'] = $options;
    }
    unset($word);

    return $words;
}

function option_pool(PDO $pdo, array $word): array
{
    $stmt = $pdo->prepare(
        'SELECT meaning_zh
         FROM wm_words
         WHERE bank_code = :bank_code
           AND id <> :id
           AND part_of_speech = :pos
           AND difficulty BETWEEN :low AND :high
         ORDER BY RAND()
         LIMIT 12'
    );
    $difficulty = (int)($word['difficulty'] ?? 1);
    $stmt->execute([
        'id' => (int)$word['id'],
        'bank_code' => (string)($word['bank_code'] ?? 'high_school'),
        'pos' => (string)($word['part_of_speech'] ?? ''),
        'low' => max(1, $difficulty - 1),
        'high' => min(5, $difficulty + 1)
    ]);
    $pool = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($pool) < 3) {
        $fallback = $pdo->prepare(
            'SELECT meaning_zh
             FROM wm_words
             WHERE bank_code = :bank_code
               AND id <> :id
             ORDER BY ABS(difficulty - :difficulty), RAND()
             LIMIT 50'
        );
        $fallback->execute([
            'id' => (int)$word['id'],
            'bank_code' => (string)($word['bank_code'] ?? 'high_school'),
            'difficulty' => $difficulty
        ]);
        $pool = array_merge($pool, $fallback->fetchAll(PDO::FETCH_COLUMN));
    }

    return array_values(array_unique(array_map('strval', $pool)));
}

function normalize_answer(string $value): string
{
    return preg_replace('/\s+/u', '', trim($value)) ?? trim($value);
}

function next_mastery(int $current, bool $remembered): int
{
    if ($remembered) {
        return min(100, $current + 20);
    }
    return max(0, $current - 25);
}

function next_difficulty(int $current, bool $remembered): int
{
    if ($remembered) {
        return max(1, $current - 1);
    }
    return min(5, $current + 1);
}
