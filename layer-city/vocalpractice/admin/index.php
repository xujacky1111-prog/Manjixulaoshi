<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/library.php';

$pdo = vp_db();
$message = '';
$error = '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_admin(): bool
{
    return !empty($_SESSION['vp_admin']);
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (password_verify((string) ($_POST['password'] ?? ''), VP_ADMIN_PASSWORD_HASH)) {
        $_SESSION['vp_admin'] = true;
        header('Location: ./');
        exit;
    }
    $error = 'Password is incorrect.';
}

if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_plan') {
    try {
        $payload = json_decode((string) ($_POST['plan_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        foreach (['slug', 'title', 'description', 'level', 'reason', 'focus', 'dailyGoal', 'lessons'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new RuntimeException("Missing field: {$key}");
            }
        }
        vp_upsert_plan($pdo, $payload, (int) ($_POST['sort_order'] ?? 100));
        vp_touch_version($pdo);
        $message = 'Plan saved and library version updated.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_plan') {
    $stmt = $pdo->prepare('UPDATE plans SET enabled = CASE WHEN enabled = 1 THEN 0 ELSE 1 END, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([':id' => (int) $_POST['plan_id'], ':updated_at' => gmdate('c')]);
    vp_touch_version($pdo);
    header('Location: ./');
    exit;
}

$editPlan = null;
if (is_admin() && isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $editPlan = [
            'slug' => 'new-plan-' . gmdate('YmdHis'),
            'title' => 'New Learning Plan',
            'description' => 'Describe this plan.',
            'level' => 'level-1',
            'reason' => 'interest',
            'focus' => 'confidence',
            'dailyGoal' => 10,
            'enabled' => 1,
            'lessons' => [[
                'title' => 'New Lesson',
                'category' => 'Daily Life',
                'level' => 'Beginner',
                'emoji' => '💬',
                'goal' => 'Practice one useful sentence pattern.',
                'example' => 'I can swim.',
                'exampleZh' => '我会游泳。',
                'pattern' => 'I can ___.',
                'prefix' => 'I can',
                'ending' => '.',
                'drills' => [
                    ['answer' => 'speak English', 'prompt' => '说英语', 'translation' => '我会说英语。'],
                ],
            ]],
        ];
    } else {
        $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = :id');
        $stmt->execute([':id' => (int) $_GET['edit']]);
        $plan = $stmt->fetch();
        if ($plan) {
            $editPlan = vp_plan_payload($pdo, $plan);
            $editPlan['enabled'] = (int) $plan['enabled'];
        }
    }
}

$plans = [];
if (is_admin()) {
    $plans = $pdo->query('SELECT * FROM plans ORDER BY sort_order, id')->fetchAll();
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>VocalPractice Admin</title>
  <style>
    :root { --ink:#17211f; --muted:#6f7c78; --line:#dfe7e4; --bg:#f6f8f6; --accent:#2c7a70; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Inter, system-ui, "Microsoft YaHei", sans-serif; background: var(--bg); color: var(--ink); }
    main { width: min(980px, calc(100% - 28px)); margin: 0 auto; padding: 28px 0; display: grid; gap: 18px; }
    header, section { border: 1px solid var(--line); border-radius: 18px; background: #fff; padding: 18px; }
    header { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    h1, h2, p { margin-top: 0; }
    h1 { margin-bottom: 4px; }
    p { color: var(--muted); line-height: 1.5; }
    a, button { font: inherit; }
    a { color: var(--accent); font-weight: 800; text-decoration: none; }
    input, textarea { width: 100%; border: 1px solid var(--line); border-radius: 12px; padding: 12px; font: inherit; }
    textarea { min-height: 520px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 13px; line-height: 1.45; }
    button, .button { border: 0; border-radius: 999px; background: var(--accent); color: #fff; padding: 10px 16px; font-weight: 800; cursor: pointer; display: inline-block; }
    .secondary { background: #e8f2ef; color: var(--accent); }
    .danger { background: #d45d55; }
    .stack { display: grid; gap: 12px; }
    .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .notice { border-color: #b9e1d8; background: #effaf7; color: var(--accent); }
    .error { border-color: #f1c1bc; background: #fff2f1; color: #9e2f28; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid var(--line); padding: 10px; text-align: left; vertical-align: top; }
    th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
    code { background: #f2f5f4; padding: 2px 6px; border-radius: 6px; }
  </style>
</head>
<body>
<main>
  <header>
    <div>
      <h1>VocalPractice Admin</h1>
      <p>Library version: <code><?= h(vp_library_version($pdo)) ?></code></p>
    </div>
    <?php if (is_admin()): ?>
      <a class="button secondary" href="?logout=1">Logout</a>
    <?php endif; ?>
  </header>

  <?php if ($message): ?><section class="notice"><?= h($message) ?></section><?php endif; ?>
  <?php if ($error): ?><section class="error"><?= h($error) ?></section><?php endif; ?>

  <?php if (!is_admin()): ?>
    <section>
      <h2>Login</h2>
      <form class="stack" method="post">
        <input type="hidden" name="action" value="login">
        <label class="stack">
          <span>Admin password</span>
          <input type="password" name="password" autofocus required>
        </label>
        <button type="submit">Login</button>
      </form>
    </section>
  <?php else: ?>
    <section class="stack">
      <div class="row">
        <h2 style="margin-bottom:0">Plans</h2>
        <a class="button" href="?edit=new">New plan</a>
        <a class="button secondary" href="../api/library.php" target="_blank">Open API</a>
      </div>
      <table>
        <thead><tr><th>Title</th><th>Match</th><th>Goal</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
          <tr>
            <td><strong><?= h($plan['title']) ?></strong><br><small><?= h($plan['slug']) ?></small></td>
            <td><?= h($plan['level']) ?> / <?= h($plan['reason']) ?> / <?= h($plan['focus']) ?></td>
            <td><?= (int) $plan['daily_goal'] ?> min</td>
            <td><?= $plan['enabled'] ? 'Enabled' : 'Disabled' ?></td>
            <td class="row">
              <a class="button secondary" href="?edit=<?= (int) $plan['id'] ?>">Edit</a>
              <form method="post">
                <input type="hidden" name="action" value="toggle_plan">
                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                <button class="<?= $plan['enabled'] ? 'danger' : 'secondary' ?>" type="submit"><?= $plan['enabled'] ? 'Disable' : 'Enable' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <?php if ($editPlan): ?>
      <section class="stack">
        <h2>Edit plan JSON</h2>
        <p>可编辑 plan、lessons、drills 的全部字段。保存后前端 API 会立即返回新版本。</p>
        <form class="stack" method="post">
          <input type="hidden" name="action" value="save_plan">
          <input type="hidden" name="sort_order" value="100">
          <textarea name="plan_json" spellcheck="false"><?= h(json_encode($editPlan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
          <div class="row">
            <button type="submit">Save plan</button>
            <a class="button secondary" href="./">Cancel</a>
          </div>
        </form>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>

