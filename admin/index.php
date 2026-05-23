<?php
$configured = file_exists(__DIR__ . '/../config/config.php');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>学习记录后台</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f5f6f4; color: #1c2622; }
        header { padding: 20px; background: #2f6f5e; color: white; }
        header a { color: white; }
        main { max-width: 1180px; margin: 0 auto; padding: 20px; display: grid; gap: 18px; }
        section { background: white; border: 1px solid #dfe5df; border-radius: 8px; padding: 16px; }
        input, button { font: inherit; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #cbd5cf; border-radius: 6px; padding: 10px; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2f6f5e; color: white; cursor: pointer; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar button { background: white; color: #1c2622; border: 1px solid #dfe5df; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .kpi { border: 1px solid #e3e9e4; border-radius: 8px; padding: 14px; background: #fbfcfa; }
        .kpi strong { display: block; font-size: 24px; }
        .row { display: grid; grid-template-columns: 1fr 90px 2fr 1.4fr 100px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #eef1ed; }
        .board { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .column { border: 1px solid #e2e8e3; border-radius: 8px; padding: 12px; background: #fafbf9; min-height: 220px; }
        .column h3 { margin-top: 0; }
        .card { border: 1px solid #e7ece8; border-radius: 7px; padding: 10px; margin-bottom: 8px; background: white; }
        .muted { color: #64736c; }
        @media (max-width: 900px) { .grid, .board, .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>学习记录后台</h1>
    <p>每日答题记录、错词统计和掌握进度。词库管理已移到 <a href="../vocabbase/">Vocab Base</a>。</p>
</header>
<main>
    <?php if (!$configured): ?>
        <section><strong>未配置数据库。</strong></section>
    <?php endif; ?>

    <section>
        <div class="grid">
            <label>日期<input id="reportDate" type="date"></label>
            <div style="align-self:end"><button onclick="loadAll()">刷新统计</button></div>
        </div>
        <p class="muted">历史日期</p>
        <div id="dateHistory" class="toolbar"></div>
    </section>

    <section>
        <h2>今日统计</h2>
        <div class="grid">
            <div class="kpi"><strong id="wordsSeen">0</strong><span class="muted">测试过的词</span></div>
            <div class="kpi"><strong id="totalAttempts">0</strong><span class="muted">总作答次数</span></div>
            <div class="kpi"><strong id="correctAttempts">0</strong><span class="muted">答对次数</span></div>
            <div class="kpi"><strong id="wrongAttempts">0</strong><span class="muted">答错次数</span></div>
        </div>
    </section>

    <section>
        <h2>词汇工作流</h2>
        <div class="grid">
            <div class="kpi"><strong id="untestedCount">0</strong><span class="muted">未测试</span></div>
            <div class="kpi"><strong id="workCount">0</strong><span class="muted">需要加强</span></div>
            <div class="kpi"><strong id="learningCount">0</strong><span class="muted">学习中</span></div>
            <div class="kpi"><strong id="masteredCount">0</strong><span class="muted">基本会了</span></div>
        </div>
        <div class="board" style="margin-top:14px">
            <div class="column"><h3>未测试</h3><div id="untestedList"></div></div>
            <div class="column"><h3>需要加强</h3><div id="workList"></div></div>
            <div class="column"><h3>学习中</h3><div id="learningList"></div></div>
            <div class="column"><h3>基本会了</h3><div id="masteredList"></div></div>
        </div>
    </section>

    <section>
        <h2>每日学习记录</h2>
        <p id="reportSummary" class="muted"></p>
        <div id="report"></div>
    </section>

    <section>
        <h2>高频错词汇总</h2>
        <div id="wrongLeaderboard"></div>
    </section>
</main>
<script>
const api = path => '../api/' + path;

async function loadAll() {
    await Promise.all([loadDashboard(), loadReport(), loadWorkflow()]);
}

async function loadDashboard() {
    const date = getDate();
    const res = await fetch(api('dashboard.php?date=' + encodeURIComponent(date)));
    const data = await res.json();
    const daily = data.daily || {};
    const summary = data.status_summary || {};
    document.getElementById('wordsSeen').textContent = daily.words_seen || 0;
    document.getElementById('totalAttempts').textContent = daily.total_attempts || 0;
    document.getElementById('correctAttempts').textContent = daily.correct_attempts || 0;
    document.getElementById('wrongAttempts').textContent = daily.wrong_attempts || 0;
    document.getElementById('untestedCount').textContent = summary.untested_words || 0;
    document.getElementById('workCount').textContent = summary.work_words || 0;
    document.getElementById('learningCount').textContent = summary.learning_words || 0;
    document.getElementById('masteredCount').textContent = summary.mastered_words || 0;
    document.getElementById('wrongLeaderboard').innerHTML = renderRows(data.wrong_leaderboard || [], '暂无错词');
    document.getElementById('dateHistory').innerHTML = (data.trend || []).map(item => `
        <button onclick="setDate('${escapeHtml(item.study_date)}')">${escapeHtml(item.study_date)} · ${escapeHtml(item.words_seen || 0)}词</button>
    `).join('') || '<span class="muted">暂无历史记录</span>';
}

async function loadWorkflow() {
    const date = getDate();
    const statuses = [
        ['untested', 'untestedList'],
        ['work', 'workList'],
        ['learning', 'learningList'],
        ['mastered', 'masteredList']
    ];
    await Promise.all(statuses.map(async ([status, target]) => {
        const res = await fetch(api(`wordbase.php?date=${encodeURIComponent(date)}&status=${status}&limit=20`));
        const data = await res.json();
        document.getElementById(target).innerHTML = renderCards(data.words || []);
    }));
}

async function loadReport() {
    const date = getDate();
    const res = await fetch(api('report.php?date=' + encodeURIComponent(date)));
    const data = await res.json();
    const summary = data.summary || {};
    document.getElementById('reportSummary').textContent =
        `${data.date}：测试 ${summary.total || 0} 个词，正确 ${summary.correct || 0}，错误/曾错 ${summary.wrong || 0}，困难 ${summary.difficult || 0}`;
    const attempts = data.attempts || [];
    document.getElementById('report').innerHTML = attempts.map(item => `
        <div class="row">
            <strong>${escapeHtml(item.word)}</strong>
            <span>${Number(item.remembered) === 1 ? '正确' : '错误'}</span>
            <span>${escapeHtml(item.meaning_zh || '')}</span>
            <span>${Number(item.remembered) === 1 ? '' : '选了：' + escapeHtml(item.chosen_meaning_zh || '')}</span>
            <span>${escapeHtml((item.answered_at || '').slice(11, 16))}</span>
        </div>
    `).join('') || '<p class="muted">这一天还没有记录</p>';
}

function renderCards(rows) {
    return rows.map(item => `
        <div class="card">
            <strong>${escapeHtml(item.word)}</strong>
            <div>${escapeHtml(item.meaning_zh || '')}</div>
            <div class="muted">${escapeHtml(item.part_of_speech || '')} · ${escapeHtml(item.mastery_score || 0)}分 · 错${escapeHtml(item.total_wrong || item.wrong_streak || 0)}</div>
        </div>
    `).join('') || '<p class="muted">暂无</p>';
}

function renderRows(rows, emptyText) {
    return rows.map(item => `
        <div class="row">
            <strong>${escapeHtml(item.word)}</strong>
            <span>错${escapeHtml(item.wrong_count || 0)}</span>
            <span>${escapeHtml(item.meaning_zh || '')}</span>
            <span>${escapeHtml(item.part_of_speech || '')} · ${escapeHtml(item.mastery_score || 0)}分</span>
            <span></span>
        </div>
    `).join('') || `<p class="muted">${emptyText}</p>`;
}

function getDate() {
    return document.getElementById('reportDate').value || new Date().toISOString().slice(0, 10);
}

function setDate(date) {
    document.getElementById('reportDate').value = date;
    loadAll();
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

document.getElementById('reportDate').value = new Date().toISOString().slice(0, 10);
loadAll();
</script>
</body>
</html>
