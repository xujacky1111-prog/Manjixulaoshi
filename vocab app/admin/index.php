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
        body { margin: 0; padding-bottom: 84px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f5f6f4; color: #1c2622; }
        header { padding: 20px; background: #2f6f5e; color: white; }
        header a { color: white; }
        .topbar { max-width: 1180px; margin: 0 auto; display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
        .topbar h1 { margin: 0; }
        .topbar p { margin-bottom: 0; color: #dce9e4; }
        .settings-menu { position: relative; }
        .settings-menu summary { list-style: none; border: 1px solid rgba(255,255,255,.45); border-radius: 8px; padding: 9px 12px; cursor: pointer; font-weight: 650; }
        .settings-menu summary::-webkit-details-marker { display: none; }
        .settings-menu[open] summary { background: rgba(255,255,255,.12); }
        .settings-menu .menu { position: absolute; right: 0; top: 44px; min-width: 180px; display: grid; gap: 4px; padding: 8px; border-radius: 8px; background: white; box-shadow: 0 14px 30px rgba(28,38,34,.18); border: 1px solid #dfe5df; }
        .settings-menu .menu a { color: #1c2622; text-decoration: none; padding: 10px; border-radius: 7px; }
        .settings-menu .menu a:hover { background: #f1f5f2; }
        main { max-width: 1180px; margin: 0 auto; padding: 20px; display: grid; gap: 18px; }
        section { background: white; border: 1px solid #dfe5df; border-radius: 8px; padding: 16px; }
        input, button { font: inherit; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #cbd5cf; border-radius: 6px; padding: 10px; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2f6f5e; color: white; cursor: pointer; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar button { background: white; color: #1c2622; border: 1px solid #dfe5df; }
        .tabs button.active { background: #e5f2ed; color: #2f6f5e; border-color: #b7d3c8; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .kpi { border: 1px solid #e3e9e4; border-radius: 8px; padding: 14px; background: #fbfcfa; }
        .kpi strong { display: block; font-size: 24px; }
        .row { display: grid; grid-template-columns: 1fr 90px 2fr 1.4fr 100px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #eef1ed; }
        .board { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .column { border: 1px solid #e2e8e3; border-radius: 8px; padding: 12px; background: #fafbf9; min-height: 220px; }
        .column h3 { margin-top: 0; }
        .card { border: 1px solid #e7ece8; border-radius: 7px; padding: 10px; margin-bottom: 8px; background: white; }
        .analysis { white-space: pre-wrap; line-height: 1.65; background: #fbfcfa; border: 1px solid #e3e9e4; border-radius: 8px; padding: 14px; }
        .muted { color: #64736c; }
        .bottom-tabs { position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; background: rgba(255,255,255,.96); border-top: 1px solid #dfe5df; box-shadow: 0 -8px 24px rgba(28,38,34,.08); }
        .bottom-tabs .inner { max-width: 760px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 8px 12px calc(8px + env(safe-area-inset-bottom)); }
        .bottom-tabs a { display: grid; gap: 2px; justify-items: center; padding: 8px 6px; border-radius: 8px; color: #64736c; text-decoration: none; font-size: 12px; font-weight: 650; }
        .bottom-tabs a strong { font-size: 18px; line-height: 1; }
        .bottom-tabs a.active { background: #e5f2ed; color: #2f6f5e; }
        @media (max-width: 900px) { .topbar { display: grid; } .settings-menu .menu { right: auto; left: 0; } .grid, .board, .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <div class="topbar">
        <div>
            <h1>学习记录后台</h1>
            <p>每日答题记录、错词统计和掌握进度。</p>
        </div>
        <details class="settings-menu">
            <summary>设置</summary>
            <div class="menu">
                <a href="../settings/">API 设置</a>
                <a href="../monitor/">Token Monitor</a>
                <a href="../vocabbase/">Vocab Base</a>
            </div>
        </details>
    </div>
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
        <div class="toolbar tabs" style="margin-top:14px">
            <button id="workflowTabUntested" class="active" onclick="setWorkflowStatus('untested')">未测试</button>
            <button id="workflowTabWork" onclick="setWorkflowStatus('work')">需要加强</button>
            <button id="workflowTabLearning" onclick="setWorkflowStatus('learning')">学习中</button>
            <button id="workflowTabMastered" onclick="setWorkflowStatus('mastered')">基本会了</button>
        </div>
        <div id="workflowList" style="margin-top:12px"></div>
    </section>

    <section>
        <h2>AI 学习分析</h2>
        <div class="toolbar">
            <button onclick="loadAnalysis('week')">分析最近 7 天</button>
            <button onclick="loadAnalysis('month')">分析最近 30 天</button>
        </div>
        <p class="muted">系统先整理 90% 的真实数据，再让 AI 做 10% 的学习判断：Are we learning?</p>
        <div class="grid">
            <div class="kpi"><strong id="analysisAccuracy">0%</strong><span class="muted">本周期正确率</span></div>
            <div class="kpi"><strong id="analysisChange">0</strong><span class="muted">较上一周期</span></div>
            <div class="kpi"><strong id="analysisDays">0</strong><span class="muted">练习天数</span></div>
            <div class="kpi"><strong id="analysisAttempts">0</strong><span class="muted">作答次数</span></div>
        </div>
        <h3>数据结论</h3>
        <div id="localAnalysis" class="analysis muted">点击上面的按钮生成分析。</div>
        <h3>AI 分析</h3>
        <div id="aiAnalysis" class="analysis muted">绑定并启用 API 后，会在这里生成 AI 学习判断。</div>
        <h3>本周期高频错词</h3>
        <div id="analysisWrongWords"></div>
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
<div class="bottom-tabs" aria-label="主导航">
    <div class="inner">
        <a href="../"><strong>W</strong><span>Word</span></a>
        <a href="../vocabbase/"><strong>V</strong><span>Vocab Base</span></a>
        <a class="active" href="./"><strong>A</strong><span>Admin</span></a>
    </div>
</div>
<script>
const api = path => '../api/' + path;
let workflowStatus = 'untested';

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
}

async function loadWorkflow() {
    const date = getDate();
    const res = await fetch(api(`wordbase.php?date=${encodeURIComponent(date)}&status=${workflowStatus}&limit=50`));
    const data = await res.json();
    document.getElementById('workflowList').innerHTML = renderCards(data.words || []);
    updateWorkflowTabs();
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

async function loadAnalysis(period) {
    document.getElementById('localAnalysis').textContent = '正在整理学习数据...';
    document.getElementById('aiAnalysis').textContent = '正在请求 AI 分析...';
    const res = await fetch(api('analysis.php?period=' + encodeURIComponent(period)), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({use_ai: true})
    });
    const data = await res.json();
    if (!res.ok) {
        document.getElementById('localAnalysis').textContent = data.error || '分析失败';
        document.getElementById('aiAnalysis').textContent = '';
        return;
    }
    const summary = data.summary || {};
    document.getElementById('analysisAccuracy').textContent = `${summary.current_accuracy || 0}%`;
    document.getElementById('analysisChange').textContent = summary.accuracy_change === null ? '暂无基线' : `${Number(summary.accuracy_change || 0) > 0 ? '+' : ''}${summary.accuracy_change || 0} pts`;
    document.getElementById('analysisDays').textContent = `${summary.current_active_days || 0}/${summary.expected_days || 0}`;
    document.getElementById('analysisAttempts').textContent = summary.current_attempts || 0;
    document.getElementById('localAnalysis').textContent = data.local_analysis || '暂无数据结论。';
    document.getElementById('aiAnalysis').textContent = data.ai_analysis || (data.ai_error ? `AI 暂不可用：${data.ai_error}` : 'AI 暂不可用，请先在 Settings 绑定并启用 API。');
    document.getElementById('analysisWrongWords').innerHTML = renderAnalysisWrongWords(data.wrong_words || []);
}

function renderAnalysisWrongWords(rows) {
    return rows.map(item => `
        <div class="row">
            <strong>${escapeHtml(item.word)}</strong>
            <span>错${escapeHtml(item.wrong_count || 0)}</span>
            <span>${escapeHtml(item.meaning_zh || '')}</span>
            <span>${escapeHtml(item.part_of_speech || '')} · ${escapeHtml(item.mastery_score || 0)}分</span>
            <span>${escapeHtml((item.last_wrong_at || '').slice(5, 16))}</span>
        </div>
    `).join('') || '<p class="muted">本周期没有高频错词</p>';
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

function setWorkflowStatus(status) {
    workflowStatus = status;
    updateWorkflowTabs();
    loadWorkflow();
}

function updateWorkflowTabs() {
    const map = {
        untested: 'workflowTabUntested',
        work: 'workflowTabWork',
        learning: 'workflowTabLearning',
        mastered: 'workflowTabMastered'
    };
    Object.entries(map).forEach(([status, id]) => {
        document.getElementById(id).classList.toggle('active', workflowStatus === status);
    });
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
