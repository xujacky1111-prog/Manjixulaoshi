<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing config/config.php. Copy config.sample.php first.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

function db(): PDO
{
    global $config;
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    }
    return $pdo;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function respond($payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_admin(): void
{
    global $config;
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($_GET['token'] ?? '');
    if (!hash_equals((string)$config['admin_token'], (string)$token)) {
        respond(['error' => 'Unauthorized'], 401);
    }
}

function clean_word_payload(array $data): array
{
    return [
        'word' => strtolower(trim((string)($data['word'] ?? ''))),
        'part_of_speech' => trim((string)($data['part_of_speech'] ?? $data['pos'] ?? '')),
        'meaning_zh' => trim((string)($data['meaning_zh'] ?? $data['meaning'] ?? '')),
        'example_en' => trim((string)($data['example_en'] ?? $data['example'] ?? '')),
        'source' => trim((string)($data['source'] ?? 'admin')),
        'difficulty' => max(1, min(5, (int)($data['difficulty'] ?? 1)))
    ];
}
