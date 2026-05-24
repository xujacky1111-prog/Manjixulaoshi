<?php
$configured = file_exists(__DIR__ . '/../config/config.php');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f5f6f4; color: #1c2622; }
        header { padding: 20px; background: #2f6f5e; color: white; }
        header a { color: white; }
        main { max-width: 900px; margin: 0 auto; padding: 20px; display: grid; gap: 18px; }
        section { background: white; border: 1px solid #dfe5df; border-radius: 8px; padding: 16px; }
        input, button, select { font: inherit; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #cbd5cf; border-radius: 6px; padding: 10px; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2f6f5e; color: white; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .muted { color: #64736c; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Settings</h1>
    <p>绑定用户自己的 AI API。返回 <a href="../">练习页</a> / <a href="../monitor/">Token Monitor</a></p>
</header>
<main>
    <?php if (!$configured): ?>
        <section><strong>未配置数据库。</strong></section>
    <?php endif; ?>
    <section>
        <h2>AI API 绑定</h2>
        <div class="grid">
            <label>Provider
                <select id="provider" onchange="applyProviderDefaults()"></select>
            </label>
            <label>Model
                <input id="model" placeholder="model">
            </label>
            <label>Base URL
                <input id="baseUrl" placeholder="https://.../chat/completions">
            </label>
            <label>API Key
                <input id="apiKey" type="password" placeholder="留空表示保留原 key">
            </label>
            <label>
                <input id="enabled" type="checkbox" style="width:auto"> 启用 AI API
            </label>
        </div>
        <p><button onclick="saveSettings()">保存设置</button></p>
        <p id="status" class="muted"></p>
        <p class="muted">API Key 保存在服务器数据库里，前端不会显示完整 key。请保护好 Settings 页面访问权限。</p>
    </section>
</main>
<script>
const api = path => '../api/' + path;
let providers = {};

async function loadSettings() {
    const res = await fetch(api('settings.php'));
    const data = await res.json();
    providers = data.providers || {};
    const select = document.getElementById('provider');
    select.innerHTML = Object.entries(providers).map(([key, item]) => `<option value="${escapeHtml(key)}">${escapeHtml(item.label)}</option>`).join('');
    const settings = data.settings || {};
    if (settings.provider) select.value = settings.provider;
    applyProviderDefaults();
    if (settings.base_url) document.getElementById('baseUrl').value = settings.base_url;
    if (settings.model) document.getElementById('model').value = settings.model;
    document.getElementById('enabled').checked = Number(settings.enabled || 0) === 1;
    document.getElementById('status').textContent = settings.api_key_masked ? `当前 key：${settings.api_key_masked}` : '还没有绑定 API Key。';
}

function applyProviderDefaults() {
    const item = providers[document.getElementById('provider').value] || {};
    document.getElementById('baseUrl').value = item.base_url || '';
    document.getElementById('model').value = item.model || '';
}

async function saveSettings() {
    const payload = {
        provider: document.getElementById('provider').value,
        base_url: document.getElementById('baseUrl').value,
        model: document.getElementById('model').value,
        api_key: document.getElementById('apiKey').value,
        enabled: document.getElementById('enabled').checked
    };
    const res = await fetch(api('settings.php'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok) {
        document.getElementById('status').textContent = data.error || '保存失败';
        return;
    }
    document.getElementById('apiKey').value = '';
    document.getElementById('status').textContent = `已保存。当前 key：${data.settings.api_key_masked || '未设置'}`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

loadSettings();
</script>
</body>
</html>
