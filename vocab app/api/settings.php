<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = current_ai_settings();
    if ($settings !== null) {
        $settings['api_key_masked'] = mask_secret((string)$settings['api_key']);
        unset($settings['api_key']);
    }
    respond(['settings' => $settings, 'providers' => ai_provider_defaults()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $provider = trim((string)($body['provider'] ?? 'deepseek'));
    $defaults = ai_provider_defaults();
    if (!isset($defaults[$provider])) {
        respond(['error' => 'Unsupported provider'], 422);
    }

    $baseUrl = trim((string)($body['base_url'] ?? $defaults[$provider]['base_url']));
    $model = trim((string)($body['model'] ?? $defaults[$provider]['model']));
    $apiKey = trim((string)($body['api_key'] ?? ''));
    $enabled = !empty($body['enabled']) ? 1 : 0;

    $existing = current_ai_settings();
    if ($apiKey === '' && $existing !== null && $provider === $existing['provider']) {
        $apiKey = (string)$existing['api_key'];
    }
    if ($enabled && $apiKey === '') {
        respond(['error' => 'API key is required when enabled'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO wm_ai_settings (id, provider, base_url, model, api_key, enabled)
         VALUES (1, :provider, :base_url, :model, :api_key, :enabled)
         ON DUPLICATE KEY UPDATE provider=VALUES(provider), base_url=VALUES(base_url), model=VALUES(model), api_key=VALUES(api_key), enabled=VALUES(enabled)'
    );
    $stmt->execute([
        'provider' => $provider,
        'base_url' => $baseUrl,
        'model' => $model,
        'api_key' => $apiKey,
        'enabled' => $enabled
    ]);

    $settings = current_ai_settings();
    $settings['api_key_masked'] = mask_secret((string)$settings['api_key']);
    unset($settings['api_key']);
    respond(['ok' => true, 'settings' => $settings]);
}

respond(['error' => 'Method not allowed'], 405);

function current_ai_settings(): ?array
{
    $stmt = db()->query('SELECT * FROM wm_ai_settings WHERE id = 1');
    $row = $stmt->fetch();
    return $row ?: null;
}

function ai_provider_defaults(): array
{
    return [
        'deepseek' => [
            'label' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com/chat/completions',
            'model' => 'deepseek-chat'
        ],
        'kimi' => [
            'label' => 'Kimi',
            'base_url' => 'https://api.moonshot.cn/v1/chat/completions',
            'model' => 'moonshot-v1-8k'
        ],
        'doubao' => [
            'label' => '豆包',
            'base_url' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
            'model' => ''
        ],
        'openai_compatible' => [
            'label' => 'OpenAI-compatible',
            'base_url' => '',
            'model' => ''
        ]
    ];
}

function mask_secret(string $value): string
{
    if ($value === '') return '';
    if (strlen($value) <= 8) return str_repeat('*', strlen($value));
    return substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
}
