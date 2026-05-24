<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
    $to = trim((string)($_GET['to'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        respond(['error' => 'Invalid date range'], 422);
    }

    $summaryStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS calls,
            SUM(prompt_tokens) AS prompt_tokens,
            SUM(completion_tokens) AS completion_tokens,
            SUM(total_tokens) AS total_tokens,
            SUM(cost_estimate) AS cost_estimate
         FROM wm_ai_usage
         WHERE usage_date BETWEEN ? AND ?'
    );
    $summaryStmt->execute([$from, $to]);

    $dailyStmt = $pdo->prepare(
        'SELECT usage_date, provider, model, COUNT(*) AS calls, SUM(total_tokens) AS total_tokens, SUM(cost_estimate) AS cost_estimate
         FROM wm_ai_usage
         WHERE usage_date BETWEEN ? AND ?
         GROUP BY usage_date, provider, model
         ORDER BY usage_date DESC, provider ASC'
    );
    $dailyStmt->execute([$from, $to]);

    $recentStmt = $pdo->prepare(
        'SELECT usage_date, provider, model, feature, prompt_tokens, completion_tokens, total_tokens, cost_estimate, created_at
         FROM wm_ai_usage
         WHERE usage_date BETWEEN ? AND ?
         ORDER BY created_at DESC
         LIMIT 100'
    );
    $recentStmt->execute([$from, $to]);

    respond([
        'from' => $from,
        'to' => $to,
        'summary' => $summaryStmt->fetch() ?: [],
        'daily' => $dailyStmt->fetchAll(),
        'recent' => $recentStmt->fetchAll()
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $stmt = $pdo->prepare(
        'INSERT INTO wm_ai_usage (usage_date, provider, model, feature, prompt_tokens, completion_tokens, total_tokens, cost_estimate)
         VALUES (CURDATE(), :provider, :model, :feature, :prompt_tokens, :completion_tokens, :total_tokens, :cost_estimate)'
    );
    $promptTokens = max(0, (int)($body['prompt_tokens'] ?? 0));
    $completionTokens = max(0, (int)($body['completion_tokens'] ?? 0));
    $totalTokens = max($promptTokens + $completionTokens, (int)($body['total_tokens'] ?? 0));
    $stmt->execute([
        'provider' => trim((string)($body['provider'] ?? 'manual')),
        'model' => trim((string)($body['model'] ?? '')),
        'feature' => trim((string)($body['feature'] ?? 'manual')),
        'prompt_tokens' => $promptTokens,
        'completion_tokens' => $completionTokens,
        'total_tokens' => $totalTokens,
        'cost_estimate' => max(0, (float)($body['cost_estimate'] ?? 0))
    ]);
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
