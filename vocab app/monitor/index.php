<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Token Monitor</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f5f6f4; color: #1c2622; }
        header { padding: 20px; background: #2f6f5e; color: white; }
        header a { color: white; }
        main { max-width: 1100px; margin: 0 auto; padding: 20px; display: grid; gap: 18px; }
        section { background: white; border: 1px solid #dfe5df; border-radius: 8px; padding: 16px; }
        input, button { font: inherit; }
        input { border: 1px solid #cbd5cf; border-radius: 6px; padding: 10px; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2f6f5e; color: white; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .kpi { border: 1px solid #e3e9e4; border-radius: 8px; padding: 14px; background: #fbfcfa; }
        .kpi strong { display: block; font-size: 24px; }
        .row { display: grid; grid-template-columns: 120px 1fr 1fr 110px 110px 110px; gap: 8px; padding: 8px 0; border-bottom: 1px solid #eef1ed; }
        .muted { color: #64736c; }
        @media (max-width: 760px) { .grid, .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Token Monitor</h1>
    <p>监控 AI API token 使用量。返回 <a href="../settings/">Settings</a></p>
</header>
<main>
    <section>
        <div class="grid">
            <label>From <input id="fromDate" type="date"></label>
            <label>To <input id="toDate" type="date"></label>
            <div style="align-self:end"><button onclick="loadUsage()">刷新</button></div>
        </div>
    </section>
    <section>
        <h2>汇总</h2>
        <div class="grid">
            <div class="kpi"><strong id="calls">0</strong><span class="muted">调用次数</span></div>
            <div class="kpi"><strong id="promptTokens">0</strong><span class="muted">Prompt tokens</span></div>
            <div class="kpi"><strong id="completionTokens">0</strong><span class="muted">Completion tokens</span></div>
            <div class="kpi"><strong id="totalTokens">0</strong><span class="muted">Total tokens</span></div>
        </div>
    </section>
    <section>
        <h2>每日用量</h2>
        <div id="daily"></div>
    </section>
    <section>
        <h2>最近记录</h2>
        <div id="recent"></div>
    </section>
</main>
<script>
const api = path => '../api/' + path;

async function loadUsage() {
    const from = document.getElementById('fromDate').value;
    const to = document.getElementById('toDate').value;
    const res = await fetch(api(`usage.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`));
    const data = await res.json();
    const summary = data.summary || {};
    document.getElementById('calls').textContent = summary.calls || 0;
    document.getElementById('promptTokens').textContent = summary.prompt_tokens || 0;
    document.getElementById('completionTokens').textContent = summary.completion_tokens || 0;
    document.getElementById('totalTokens').textContent = summary.total_tokens || 0;
    document.getElementById('daily').innerHTML = renderRows(data.daily || [], true);
    document.getElementById('recent').innerHTML = renderRows(data.recent || [], false);
}

function renderRows(rows, daily) {
    return rows.map(row => `
        <div class="row">
            <strong>${escapeHtml(row.usage_date)}</strong>
            <span>${escapeHtml(row.provider || '')}</span>
            <span>${escapeHtml(row.model || '')}</span>
            <span>${escapeHtml(row.calls || row.feature || '')}</span>
            <span>${escapeHtml(row.total_tokens || 0)} tokens</span>
            <span>${escapeHtml(row.cost_estimate || 0)}</span>
        </div>
    `).join('') || '<p class="muted">暂无 token 使用记录。</p>';
}

function isoDate(offsetDays) {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);
    return date.toISOString().slice(0, 10);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

document.getElementById('fromDate').value = isoDate(-30);
document.getElementById('toDate').value = isoDate(0);
loadUsage();
</script>
</body>
</html>
