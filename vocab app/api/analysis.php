<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$period = trim((string)($_GET['period'] ?? 'week'));
if (!in_array($period, ['week', 'month'], true)) {
    respond(['error' => 'Invalid period'], 422);
}

$days = $period === 'month' ? 30 : 7;
$pdo = db();
$currentEnd = date('Y-m-d');
$currentStart = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$previousEnd = date('Y-m-d', strtotime($currentStart . ' -1 day'));
$previousStart = date('Y-m-d', strtotime($previousEnd . ' -' . ($days - 1) . ' days'));

$current = period_stats($pdo, $currentStart, $currentEnd);
$previous = period_stats($pdo, $previousStart, $previousEnd);
$daily = daily_stats($pdo, $currentStart, $currentEnd);
$wrongWords = wrong_words($pdo, $currentStart, $currentEnd);
$improvingWords = improving_words($pdo, $currentStart, $currentEnd);
$summary = build_summary($current, $previous, $days);
$localAnalysis = local_analysis($summary, $wrongWords);

$payload = [
    'period' => $period,
    'days' => $days,
    'current_range' => ['from' => $currentStart, 'to' => $currentEnd],
    'previous_range' => ['from' => $previousStart, 'to' => $previousEnd],
    'summary' => $summary,
    'daily' => $daily,
    'wrong_words' => $wrongWords,
    'improving_words' => $improvingWords,
    'local_analysis' => $localAnalysis,
    'ai_analysis' => null,
    'ai_status' => 'not_requested'
];

$useAi = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty(json_body()['use_ai']);
if ($useAi) {
    $ai = generate_ai_analysis($payload);
    $payload['ai_analysis'] = $ai['analysis'];
    $payload['ai_status'] = $ai['status'];
    if (isset($ai['error'])) {
        $payload['ai_error'] = $ai['error'];
    }
}

respond($payload);

function period_stats(PDO $pdo, string $from, string $to): array
{
    $attemptStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_attempts,
            SUM(remembered = 1) AS correct_attempts,
            SUM(remembered = 0) AS wrong_attempts,
            COUNT(DISTINCT study_date) AS active_days,
            COUNT(DISTINCT word_id) AS unique_words,
            SUM(is_due_review = 0) AS new_attempts,
            SUM(is_due_review = 1) AS review_attempts
         FROM wm_answer_attempts
         WHERE study_date BETWEEN ? AND ?'
    );
    $attemptStmt->execute([$from, $to]);
    $attempts = $attemptStmt->fetch() ?: [];

    $dailyStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS completed_words,
            SUM(remembered = 1) AS completed_correct,
            SUM(ever_wrong = 1 OR remembered = 0) AS difficult_words
         FROM wm_daily_answers
         WHERE study_date BETWEEN ? AND ?'
    );
    $dailyStmt->execute([$from, $to]);
    $daily = $dailyStmt->fetch() ?: [];

    $masteredStmt = $pdo->query(
        'SELECT
            SUM(w.mastery_score >= 80 AND COALESCE(r.wrong_streak, 0) = 0) AS mastered_words,
            SUM(COALESCE(r.wrong_streak, 0) >= 2 OR COALESCE(t.total_wrong, 0) >= 2 OR (COALESCE(t.total_attempts, 0) > 0 AND w.mastery_score < 40)) AS work_words
         FROM wm_words w
         LEFT JOIN wm_reviews r ON r.word_id = w.id
         LEFT JOIN (
            SELECT word_id, COUNT(*) AS total_attempts, SUM(remembered = 0) AS total_wrong
            FROM wm_answer_attempts
            GROUP BY word_id
         ) t ON t.word_id = w.id'
    );
    $mastered = $masteredStmt->fetch() ?: [];

    return [
        'from' => $from,
        'to' => $to,
        'total_attempts' => (int)($attempts['total_attempts'] ?? 0),
        'correct_attempts' => (int)($attempts['correct_attempts'] ?? 0),
        'wrong_attempts' => (int)($attempts['wrong_attempts'] ?? 0),
        'active_days' => (int)($attempts['active_days'] ?? 0),
        'unique_words' => (int)($attempts['unique_words'] ?? 0),
        'new_attempts' => (int)($attempts['new_attempts'] ?? 0),
        'review_attempts' => (int)($attempts['review_attempts'] ?? 0),
        'completed_words' => (int)($daily['completed_words'] ?? 0),
        'completed_correct' => (int)($daily['completed_correct'] ?? 0),
        'difficult_words' => (int)($daily['difficult_words'] ?? 0),
        'mastered_words_now' => (int)($mastered['mastered_words'] ?? 0),
        'work_words_now' => (int)($mastered['work_words'] ?? 0),
    ];
}

function daily_stats(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT
            study_date,
            COUNT(*) AS attempts,
            SUM(remembered = 1) AS correct,
            SUM(remembered = 0) AS wrong,
            COUNT(DISTINCT word_id) AS unique_words
         FROM wm_answer_attempts
         WHERE study_date BETWEEN ? AND ?
         GROUP BY study_date
         ORDER BY study_date ASC'
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $attempts = max(1, (int)$row['attempts']);
        $row['accuracy'] = round(((int)$row['correct'] / $attempts) * 100, 1);
    }
    return $rows;
}

function wrong_words(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT
            w.word,
            w.meaning_zh,
            w.part_of_speech,
            w.mastery_score,
            COUNT(*) AS wrong_count,
            MAX(a.answered_at) AS last_wrong_at
         FROM wm_answer_attempts a
         JOIN wm_words w ON w.id = a.word_id
         WHERE a.study_date BETWEEN ? AND ? AND a.remembered = 0
         GROUP BY a.word_id, w.word, w.meaning_zh, w.part_of_speech, w.mastery_score
         ORDER BY wrong_count DESC, w.mastery_score ASC, w.word ASC
         LIMIT 12'
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

function improving_words(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT
            w.word,
            w.meaning_zh,
            w.part_of_speech,
            w.mastery_score,
            SUM(a.remembered = 1) AS correct_count,
            SUM(a.remembered = 0) AS wrong_count
         FROM wm_answer_attempts a
         JOIN wm_words w ON w.id = a.word_id
         WHERE a.study_date BETWEEN ? AND ?
         GROUP BY a.word_id, w.word, w.meaning_zh, w.part_of_speech, w.mastery_score
         HAVING correct_count >= 2 AND wrong_count = 0
         ORDER BY correct_count DESC, w.mastery_score DESC, w.word ASC
         LIMIT 12'
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

function build_summary(array $current, array $previous, int $days): array
{
    $currentAccuracy = accuracy($current['correct_attempts'], $current['total_attempts']);
    $previousAccuracy = accuracy($previous['correct_attempts'], $previous['total_attempts']);
    $hasPreviousBaseline = (int)$previous['total_attempts'] > 0;
    return [
        'current_accuracy' => $currentAccuracy,
        'previous_accuracy' => $hasPreviousBaseline ? $previousAccuracy : null,
        'accuracy_change' => $hasPreviousBaseline ? round($currentAccuracy - $previousAccuracy, 1) : null,
        'has_previous_baseline' => $hasPreviousBaseline,
        'current_active_days' => $current['active_days'],
        'previous_active_days' => $previous['active_days'],
        'active_days_change' => $current['active_days'] - $previous['active_days'],
        'current_attempts' => $current['total_attempts'],
        'previous_attempts' => $previous['total_attempts'],
        'attempts_change' => $current['total_attempts'] - $previous['total_attempts'],
        'current_unique_words' => $current['unique_words'],
        'previous_unique_words' => $previous['unique_words'],
        'unique_words_change' => $current['unique_words'] - $previous['unique_words'],
        'current_difficult_words' => $current['difficult_words'],
        'previous_difficult_words' => $previous['difficult_words'],
        'difficult_words_change' => $current['difficult_words'] - $previous['difficult_words'],
        'mastered_words_now' => $current['mastered_words_now'],
        'work_words_now' => $current['work_words_now'],
        'expected_days' => $days,
    ];
}

function accuracy(int $correct, int $total): float
{
    if ($total <= 0) return 0.0;
    return round(($correct / $total) * 100, 1);
}

function local_analysis(array $summary, array $wrongWords): string
{
    if ((int)$summary['current_attempts'] === 0) {
        return '这个周期还没有足够数据。先完成至少一天练习，再看趋势会更准确。';
    }
    $parts = [];
    if (empty($summary['has_previous_baseline'])) {
        $parts[] = '本周期已有学习数据，但上一周期没有足够作答记录，所以暂时不能严谨判断是否进步。';
    } else {
        $change = (float)$summary['accuracy_change'];
        if ($change >= 5) {
            $parts[] = '有明显进步，正确率比上一周期提高了 ' . $change . ' 个百分点。';
        } elseif ($change >= 1) {
            $parts[] = '有小幅进步，正确率正在往上走。';
        } elseif ($change > -3) {
            $parts[] = '整体比较稳定，正确率没有明显退步。';
        } else {
            $parts[] = '这一周期正确率下降了 ' . abs($change) . ' 个百分点，需要减少新词压力并增加错词复习。';
        }
    }
    $parts[] = '本周期练习 ' . (int)$summary['current_active_days'] . ' 天，完成 ' . (int)$summary['current_attempts'] . ' 次作答，覆盖 ' . (int)$summary['current_unique_words'] . ' 个不同单词。';
    if (count($wrongWords) > 0) {
        $parts[] = '最需要关注的词是：' . implode('、', array_slice(array_column($wrongWords, 'word'), 0, 5)) . '。';
    }
    return implode('', $parts);
}

function generate_ai_analysis(array $data): array
{
    $settings = current_ai_settings();
    if ($settings === null || (int)$settings['enabled'] !== 1 || trim((string)$settings['api_key']) === '') {
        return ['status' => 'disabled', 'analysis' => null, 'error' => 'AI API is not enabled'];
    }

    $messages = [
        [
            'role' => 'system',
            'content' => '你是高二英语词汇学习教练。只能基于给定数据分析，不要编造。输出中文，简短具体。'
        ],
        [
            'role' => 'user',
            'content' => "请基于下面的结构化数据回答：Are we learning?\n要求：\n1. 先判断是否有进步。\n2. 说明正确率是否提升、练习量是否稳定。\n3. 点出最需要处理的错词模式。\n4. 给下周/下月 3 条具体建议。\n5. 90% 使用数据，10% 给学习建议，不要空泛鼓励。\n\n数据：\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ],
    ];

    $response = call_ai_provider((string)$settings['base_url'], (string)$settings['api_key'], [
        'model' => (string)$settings['model'],
        'messages' => $messages,
        'temperature' => 0.2,
    ]);
    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded) || $response['status'] < 200 || $response['status'] >= 300) {
        return ['status' => 'error', 'analysis' => null, 'error' => 'AI provider request failed'];
    }

    $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
    record_ai_usage(
        (string)$settings['provider'],
        (string)$settings['model'],
        'learning_analysis',
        max(0, (int)($usage['prompt_tokens'] ?? 0)),
        max(0, (int)($usage['completion_tokens'] ?? 0)),
        max(0, (int)($usage['total_tokens'] ?? 0))
    );

    $analysis = $decoded['choices'][0]['message']['content'] ?? '';
    return ['status' => 'ok', 'analysis' => trim((string)$analysis)];
}

function current_ai_settings(): ?array
{
    $stmt = db()->query('SELECT * FROM wm_ai_settings WHERE id = 1');
    $row = $stmt->fetch();
    return $row ?: null;
}

function call_ai_provider(string $url, string $apiKey, array $payload): array
{
    if ($url === '') {
        return ['status' => 422, 'body' => '{"error":"Base URL is empty"}'];
    }
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body === false ? '' : (string)$body];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 45,
            'ignore_errors' => true,
        ],
    ]);
    $body = file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
        $status = (int)$match[1];
    }
    return ['status' => $status, 'body' => $body === false ? '' : (string)$body];
}

function record_ai_usage(string $provider, string $model, string $feature, int $promptTokens, int $completionTokens, int $totalTokens): void
{
    if ($totalTokens <= 0) {
        $totalTokens = $promptTokens + $completionTokens;
    }
    $stmt = db()->prepare(
        'INSERT INTO wm_ai_usage (usage_date, provider, model, feature, prompt_tokens, completion_tokens, total_tokens)
         VALUES (CURDATE(), :provider, :model, :feature, :prompt_tokens, :completion_tokens, :total_tokens)'
    );
    $stmt->execute([
        'provider' => $provider,
        'model' => $model,
        'feature' => $feature,
        'prompt_tokens' => $promptTokens,
        'completion_tokens' => $completionTokens,
        'total_tokens' => $totalTokens,
    ]);
}
