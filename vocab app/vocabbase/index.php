<?php
$configured = file_exists(__DIR__ . '/../config/config.php');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vocab Base</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f5f6f4; color: #1c2622; }
        header { padding: 20px; background: #2f6f5e; color: white; }
        header a { color: white; }
        main { max-width: 1180px; margin: 0 auto; padding: 20px; display: grid; gap: 18px; }
        section { background: white; border: 1px solid #dfe5df; border-radius: 8px; padding: 16px; }
        input, textarea, button, select { font: inherit; }
        input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid #cbd5cf; border-radius: 6px; padding: 10px; }
        textarea { min-height: 160px; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2f6f5e; color: white; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .row { display: grid; grid-template-columns: 1fr 80px 2fr 1.5fr 80px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #eef1ed; }
        .muted { color: #64736c; }
        @media (max-width: 760px) { .grid, .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Vocab Base</h1>
    <p>新增单词、维护词库和查看词库进度。学习统计在 <a href="../admin/">Admin</a>。</p>
</header>
<main>
    <?php if (!$configured): ?>
        <section><strong>未配置数据库。</strong></section>
    <?php endif; ?>

    <section>
        <h2>新增或更新单词</h2>
        <div class="grid">
            <input id="word" placeholder="word">
            <input id="pos" placeholder="pos">
            <input id="meaning" placeholder="中文释义">
            <input id="example" placeholder="example">
        </div>
        <p><button onclick="saveWord()">保存单词</button></p>
        <p id="wordStatus" class="muted"></p>
    </section>

    <section>
        <h2>多行导入</h2>
        <p class="muted">每行一个词：<code>word 中文释义 词性 可选例句</code>，例如 <code>policy 政策；方针 n. This policy helps every student.</code>。也支持 Tab 分隔。</p>
        <textarea id="bulkText" placeholder="policy 政策；方针 n. This policy helps every student.&#10;politician 政治家 n. The politician visits our school."></textarea>
        <p><button onclick="bulkImport()">批量导入</button></p>
        <p id="bulkStatus" class="muted"></p>
    </section>

    <section>
        <h2>Word Base / 词库进度</h2>
        <div class="grid">
            <input id="wordBaseDate" type="date">
            <input id="wordBaseQuery" placeholder="搜索单词或中文">
            <select id="wordBaseStatus">
                <option value="all">全部</option>
                <option value="untested">未测试</option>
                <option value="work">需要加强</option>
                <option value="learning">学习中</option>
                <option value="wrong_today">今日错过</option>
                <option value="correct_today">今日正确</option>
                <option value="mastered">已掌握</option>
            </select>
            <button onclick="loadWordBase()">查看词库进度</button>
        </div>
        <p id="wordBaseSummary" class="muted"></p>
        <div id="wordBase"></div>
    </section>

    <section>
        <h2>词库搜索</h2>
        <p><input id="q" placeholder="搜索" oninput="loadWords()"></p>
        <div id="words"></div>
    </section>

    <section>
        <h2>AI 文本导入 Prompt</h2>
        <textarea id="prompt"></textarea>
        <p><button onclick="savePrompt()">保存 Prompt</button></p>
        <p id="promptStatus" class="muted"></p>
    </section>
</main>
<script>
const api = path => '../api/' + path;

async function loadWords() {
    const q = encodeURIComponent(document.getElementById('q').value.trim());
    const res = await fetch(api('words.php?q=' + q));
    const data = await res.json();
    document.getElementById('words').innerHTML = (data.words || []).map(w => `
        <div class="row">
            <strong>${escapeHtml(w.word)}</strong>
            <span>${escapeHtml(w.part_of_speech || '')}</span>
            <span>${escapeHtml(w.meaning_zh || '')}</span>
            <span>${escapeHtml(w.example_en || '无例句')}</span>
            <button onclick="deleteWord(${w.id})">删除</button>
        </div>
    `).join('') || '<p class="muted">暂无单词</p>';
}

async function saveWord() {
    const payload = {
        word: document.getElementById('word').value,
        part_of_speech: document.getElementById('pos').value,
        meaning_zh: document.getElementById('meaning').value,
        example_en: document.getElementById('example').value,
        source: 'vocabbase'
    };
    const res = await fetch(api('words.php'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    document.getElementById('wordStatus').textContent = res.ok ? '已保存' : '保存失败';
    if (res.ok) {
        ['word', 'pos', 'meaning', 'example'].forEach(id => document.getElementById(id).value = '');
        loadWords();
        loadWordBase();
    }
}

async function bulkImport() {
    const lines = document.getElementById('bulkText').value.split(/\r?\n/);
    const records = [];
    for (const raw of lines) {
        const line = raw.trim();
        if (!line || line.startsWith('#')) continue;
        const record = parseWordLine(line);
        if (record) records.push(record);
    }
    if (!records.length) {
        document.getElementById('bulkStatus').textContent = '没有识别到可导入的词。';
        return;
    }

    let imported = 0;
    let failed = 0;
    for (const record of records) {
        const res = await fetch(api('words.php'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(record)
        });
        if (res.ok) imported += 1; else failed += 1;
    }
    document.getElementById('bulkStatus').textContent = `已导入/更新 ${imported} 个词${failed ? `，失败 ${failed} 个` : ''}。`;
    if (imported) {
        document.getElementById('bulkText').value = '';
        loadWords();
        loadWordBase();
    }
}

function parseWordLine(line) {
    const tabParts = line.split(/\t+/).map(part => part.trim()).filter(Boolean);
    if (tabParts.length >= 4) {
        return wordPayload(tabParts[0], tabParts[1], tabParts[2], tabParts.slice(3).join(' '));
    }
    if (tabParts.length === 3) {
        return wordPayload(tabParts[0], tabParts[1], tabParts[2], '');
    }
    const parts = line.split(/\s+/).filter(Boolean);
    if (parts.length < 3) return null;
    const word = parts[0];
    const posIndex = parts.findIndex((part, index) => index > 0 && isPartOfSpeech(part));
    if (posIndex < 2) return null;
    const meaning = parts.slice(1, posIndex).join(' ');
    const pos = parts[posIndex];
    const example = parts.slice(posIndex + 1).join(' ');
    return wordPayload(word, meaning, pos, example);
}

function isPartOfSpeech(value) {
    return /^(n|v|adj|adv|prep|pron|conj|interj|num|art)\.?($|\/)/i.test(String(value || '').trim());
}

function wordPayload(word, meaning, pos, example = '') {
    word = String(word || '').trim().toLowerCase();
    meaning = String(meaning || '').trim();
    pos = String(pos || '').trim();
    if (!word || !meaning || !pos) return null;
    return {
        word,
        part_of_speech: pos,
        meaning_zh: meaning,
        example_en: String(example || '').trim(),
        source: 'vocabbase_bulk',
        difficulty: 1
    };
}

async function deleteWord(id) {
    const res = await fetch(api('words.php?id=' + id), { method: 'DELETE' });
    if (res.ok) {
        loadWords();
        loadWordBase();
    }
}

async function loadWordBase() {
    const date = document.getElementById('wordBaseDate').value || new Date().toISOString().slice(0, 10);
    const q = document.getElementById('wordBaseQuery').value.trim();
    const status = document.getElementById('wordBaseStatus').value;
    const url = api('wordbase.php?date=' + encodeURIComponent(date) + '&q=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(status) + '&limit=200');
    const res = await fetch(url);
    const data = await res.json();
    const summary = data.summary || {};
    document.getElementById('wordBaseSummary').textContent =
        `总词 ${summary.total_words || 0}，未测试 ${summary.untested_words || 0}，已掌握 ${summary.mastered_words || 0}，需要加强 ${summary.work_words || 0}，今日已学 ${summary.studied_today || 0}`;
    document.getElementById('wordBase').innerHTML = (data.words || []).map(renderWordBaseRow).join('') || '<p class="muted">暂无记录</p>';
}

function renderWordBaseRow(item) {
    const labels = { untested: '未测试', mastered: '已掌握', work_on_it: '需要加强', learning: '学习中' };
    const today = Number(item.wrong_today) === 1 ? '今日错过' : (Number(item.correct_today) === 1 ? '今日正确' : '今日未学');
    return `
        <div class="row">
            <strong>${escapeHtml(item.word)}</strong>
            <span>${escapeHtml(item.mastery_score || 0)}分</span>
            <span>${escapeHtml(item.meaning_zh || '')}</span>
            <span>${escapeHtml(labels[item.status] || item.status)} / ${escapeHtml(today)} / 错${escapeHtml(item.total_wrong || 0)}${item.example_en ? ' / ' + escapeHtml(item.example_en) : ''}</span>
            <span>${escapeHtml(item.part_of_speech || '')}</span>
        </div>
    `;
}

async function loadPrompt() {
    const res = await fetch(api('prompt.php'));
    const data = await res.json();
    document.getElementById('prompt').value = data.prompt || '';
}

async function savePrompt() {
    const res = await fetch(api('prompt.php'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({prompt: document.getElementById('prompt').value})
    });
    document.getElementById('promptStatus').textContent = res.ok ? '已保存' : '保存失败';
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

document.getElementById('wordBaseDate').value = new Date().toISOString().slice(0, 10);
loadWords();
loadWordBase();
loadPrompt();
</script>
</body>
</html>
