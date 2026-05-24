<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>今日单词</title>
    <style>
        :root { --green:#2f6f5e; --ink:#17211d; --muted:#62726b; --line:#dfe6e1; --paper:#fff; --warm:#f6f1e8; --violet:#68599a; --good:#2f6f5e; --bad:#bf4f35; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f4f6f2; color: var(--ink); }
        header { background: var(--green); color: white; padding: 18px 16px; }
        header .wrap, main { max-width: 760px; margin: 0 auto; }
        h1 { margin: 0; font-size: 24px; }
        main { padding: 16px; display: grid; gap: 14px; }
        button { border: 1px solid var(--line); border-radius: 7px; padding: 12px 14px; font: inherit; background: white; color: var(--ink); cursor: pointer; min-height: 46px; text-align: left; }
        button.primary { background: var(--green); color: white; border-color: var(--green); text-align: center; }
        button.correct { background: #e5f2ed; border-color: var(--good); color: var(--good); }
        button.wrong { background: #fae9e4; border-color: var(--bad); color: var(--bad); }
        button:disabled { cursor: default; opacity: 1; }
        .card { border: 1px solid var(--line); border-radius: 8px; padding: 16px; background: var(--paper); }
        .study-card { min-height: 250px; display: grid; align-content: center; gap: 14px; background: var(--warm); }
        .muted { color: var(--muted); }
        .word-line { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .word { font-size: 52px; font-weight: 760; overflow-wrap: anywhere; }
        .speak-button { min-height: 38px; padding: 8px 12px; text-align: center; color: var(--green); border-color: #bdd4ca; }
        .pos { color: var(--violet); font-weight: 650; }
        .example { color: #40534b; line-height: 1.5; }
        .pill { display: inline-block; border-radius: 999px; padding: 3px 9px; background: #e8eee9; color: var(--muted); font-size: 13px; }
        .progress { height: 10px; border-radius: 999px; background: #dde6df; overflow: hidden; }
        .progress span { display: block; height: 100%; width: 0; background: var(--green); transition: width .2s ease; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
        .stats strong { display: block; font-size: 20px; }
        .options { display: grid; gap: 10px; }
        .feedback { min-height: 24px; font-weight: 650; }
        .feedback.good { color: var(--good); }
        .feedback.bad { color: var(--bad); }
        @media (max-width: 560px) { .word { font-size: 40px; } }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <h1>今日单词</h1>
        <div id="subtitle" style="color:#dce9e4">加载中...</div>
    </div>
</header>
<main>
    <div class="card">
        <div class="stats">
            <div><strong id="doneCount">0</strong><span class="muted">已完成</span></div>
            <div><strong id="correctCount">0</strong><span class="muted">正确</span></div>
            <div><strong id="wrongCount">0</strong><span class="muted">错误</span></div>
        </div>
        <div class="progress" style="margin-top:12px"><span id="progressBar"></span></div>
    </div>

    <div id="studyCard" class="card study-card"></div>
    <div id="options" class="options"></div>
    <div id="feedback" class="feedback"></div>
    <button id="nextButton" class="primary" onclick="nextWord()" style="display:none">下一题</button>
</main>
<script>
const state = { plan: [], index: 0, progress: null, locked: false };
const $ = id => document.getElementById(id);

async function api(path, options = {}) {
    const res = await fetch('api/' + path, options);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || '请求失败');
    return data;
}

async function loadPlan() {
    try {
        const data = await api('study.php');
        state.plan = data.all || [];
        state.index = 0;
        state.progress = data.progress || {};
        state.locked = false;
        renderProgress();
        renderCard();
    } catch (error) {
        $('subtitle').textContent = '加载失败';
        $('studyCard').innerHTML = `<div><strong>暂时无法读取单词。</strong><p class="muted">${escapeHtml(error.message)}</p></div>`;
        $('options').innerHTML = '';
    }
}

function renderProgress() {
    const p = state.progress || {};
    const done = Number(p.total || 0);
    const remaining = Math.max(0, state.plan.length - state.index);
    const target = Math.max(done + remaining, Number(p.daily_target || p.daily_new_limit || 80));
    $('subtitle').textContent = remaining ? `今天第 ${done + 1} 个，剩余 ${remaining} 个` : '今日完成';
    $('doneCount').textContent = done;
    $('correctCount').textContent = Number(p.correct || 0);
    $('wrongCount').textContent = Number(p.wrong || 0);
    $('progressBar').style.width = Math.min(100, Math.round(done / Math.max(1, target) * 100)) + '%';
}

function renderCard() {
    const item = state.plan[state.index];
    $('feedback').textContent = '';
    $('feedback').className = 'feedback';
    $('nextButton').style.display = 'none';
    state.locked = false;
    if (!item) {
        $('progressBar').style.width = '100%';
        $('studyCard').innerHTML = `
            <div>
                <span class="pill">完成</span>
                <div class="word">Done</div>
                <div class="example">今天的单词已经完成。明天再打开会继续按复习间隔安排。</div>
            </div>
        `;
        $('options').innerHTML = '';
        return;
    }
    $('studyCard').innerHTML = `
        <div>
            <div><span class="pill">${item.is_due_review == 1 ? '到期复习' : '新词'}</span></div>
            <div class="word-line">
                <div class="word">${escapeHtml(item.word)}</div>
                <button class="speak-button" onclick="speakCurrentWord()">Read</button>
            </div>
            ${item.part_of_speech ? `<div class="pos">${escapeHtml(item.part_of_speech)}</div>` : ''}
            ${item.example_en ? `<div class="example">${escapeHtml(item.example_en)}</div>` : ''}
        </div>
    `;
    $('options').innerHTML = (item.options || []).map((option, i) => `
        <button data-option="${escapeHtml(option)}" onclick="chooseOption(${i})">${escapeHtml(option)}</button>
    `).join('');
    speakWord(item.word);
}

async function chooseOption(index) {
    if (state.locked) return;
    const item = state.plan[state.index];
    if (!item) return;
    const option = (item.options || [])[index];
    if (!option) return;
    state.locked = true;
    const sequence = Number((state.progress && state.progress.last_sequence) || 0) + state.index + 1;
    try {
        const result = await api('study.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({word_id: item.id, chosen_meaning_zh: option, sequence_no: sequence})
        });
        document.querySelectorAll('#options button').forEach(button => {
            button.disabled = true;
            if (button.dataset.option === result.correct_meaning_zh) button.classList.add('correct');
            if (button.dataset.option === option && !result.correct) button.classList.add('wrong');
        });
        if (!state.progress) state.progress = {};
        state.progress.total = Number(state.progress.total || 0) + (result.correct ? 1 : 0);
        state.progress.correct = Number(state.progress.correct || 0) + (result.correct ? 1 : 0);
        state.progress.wrong = Number(state.progress.wrong || 0) + (result.correct ? 0 : 1);
        if (!result.correct) {
            state.plan.push(item);
        }
        $('feedback').textContent = result.correct ? '正确' : `错误，正确答案：${result.correct_meaning_zh}`;
        $('feedback').className = result.correct ? 'feedback good' : 'feedback bad';
        $('nextButton').style.display = 'block';
        renderProgress();
    } catch (error) {
        state.locked = false;
        alert(error.message);
    }
}

function nextWord() {
    state.index += 1;
    renderProgress();
    renderCard();
}

function speakCurrentWord() {
    const item = state.plan[state.index];
    if (item) speakWord(item.word);
}

function speakWord(word) {
    try {
        if (!word || !('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(String(word));
        utterance.lang = 'en-US';
        utterance.rate = 0.85;
        utterance.pitch = 1;
        window.speechSynthesis.speak(utterance);
    } catch (error) {
        // Some mobile browsers block automatic speech until the first user action.
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

loadPlan();
</script>
</body>
</html>
