<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/library.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = vp_db();
    $profile = [
        'level' => $_GET['level'] ?? '',
        'reason' => $_GET['reason'] ?? '',
        'dailyGoal' => $_GET['dailyGoal'] ?? '',
        'focus' => $_GET['focus'] ?? '',
    ];
    $plans = array_map(fn($plan) => vp_plan_payload($pdo, $plan), vp_match_plans($pdo, $profile));

    echo json_encode([
        'ok' => true,
        'version' => vp_library_version($pdo),
        'profile' => $profile,
        'plans' => $plans,
        'defaultPlan' => $plans[0]['slug'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'library_error',
        'message' => $error->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

