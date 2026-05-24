<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$messages = $body['messages'] ?? null;
if (!is_array($messages) || count($messages) === 0) {
    respond(['error' => 'messages is required'], 422);
}

$settings = current_ai_settings();
if ($settings === null || (int)$settings['enabled'] !== 1 || trim((string)$settings['api_key']) === '') {
    respond(['error' => 'AI API is not enabled'], 422);
}

$payload = [
    'model' => (string)$settings['model'],
    'messages' => $messages,
    'temperature' => isset($body['temperature']) ? (float)$body['temperature'] : 0.3,
];

if (isset($body['response_format'])) {
    $payload['response_format'] = $body['response_format'];
}

$response = call_ai_provider((string)$settings['base_url'], (string)$settings['api_key'], $payload);
$decoded = json_decode($response['body'], true);
if (!is_array($decoded)) {
    respond(['error' => 'AI provider returned invalid JSON', 'raw' => $response['body']], 502);
}

$usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
record_ai_usage(
    (string)$settings['provider'],
    (string)$settings['model'],
    trim((string)($body['feature'] ?? 'general')),
    max(0, (int)($usage['prompt_tokens'] ?? 0)),
    max(0, (int)($usage['completion_tokens'] ?? 0)),
    max(0, (int)($usage['total_tokens'] ?? 0))
);

respond([
    'ok' => $response['status'] >= 200 && $response['status'] < 300,
    'status' => $response['status'],
    'provider' => $settings['provider'],
    'model' => $settings['model'],
    'data' => $decoded,
], $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);

function current_ai_settings(): ?array
{
    $stmt = db()->query('SELECT * FROM wm_ai_settings WHERE id = 1');
    $row = $stmt->fetch();
    return $row ?: null;
}

function call_ai_provider(string $url, string $apiKey, array $payload): array
{
    if ($url === '') {
        respond(['error' => 'Base URL is empty'], 422);
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
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            respond(['error' => 'AI request failed: ' . $error], 502);
        }
        return ['status' => $status, 'body' => (string)$body];
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
    if ($body === false) {
        respond(['error' => 'AI request failed'], 502);
    }
    return ['status' => $status, 'body' => (string)$body];
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
