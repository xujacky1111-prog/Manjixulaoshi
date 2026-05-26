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
        body { margin: 0; padding-bottom: 84px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f5f6f4; color: #1c2622; }
        header { padding: 20px; background: #2f6f5e; color: white; }
        header a { color: white; }
        main { max-width: 1180px; margin: 0 auto; padding: 20px; display: grid; gap: 18px; }
        section { background: white; border: 1px solid #dfe5df; border-radius: 8px; padding: 16px; }
        input, textarea, button, select { font: inherit; }
        input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid #cbd5cf; border-radius: 6px; padding: 10px; }
        textarea { min-height: 180px; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2f6f5e; color: white; cursor: pointer; }
        button.secondary { background: #6d5b98; }
        button.danger { background: #bf4f35; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .row { display: grid; grid-template-columns: 1fr 80px 2fr 1.5fr 80px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #eef1ed; }
        .muted { color: #64736c; }
        .bottom-tabs { position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; background: rgba(255,255,255,.96); border-top: 1px solid #dfe5df; }
        .bottom-tabs .inner { max-width: 760px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 8px 12px calc(8px + env(safe-area-inset-bottom)); }
        .bottom-tabs a { display: grid; gap: 2px; justify-items: center; padding: 8px 6px; border-radius: 8px; color: #64736c; text-decoration: none; font-size: 12px; font-weight: 650; }
        .bottom-tabs a strong { font-size: 18px; line-height: 1; }
        .bottom-tabs a.active { background: #e5f2ed; color: #2f6f5e; }
        @media (max-width: 760px) { .grid, .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Vocab Base</h1>
    <p>管理云端词库。APK 会从这里下载词库，但学习记录只保存在手机本地。</p>
</header>
<main>
    <?php if (!$configured): ?>
        <section><strong>未配置数据库。</strong></section>
    <?php endif; ?>

    <section>
        <h2>词库</h2>
        <div class="grid">
            <select id="bankCode" onchange="loadWords(); loadWordBase();"></select>
            <input id="newBankCode" placeholder="新词库代码，例如 junior_high">
            <input id="bankTitle" placeholder="词库标题，例如 高中词库">
        </div>
        <p>
            <button onclick="saveBank()">准备词库</button>
            <button class="danger" onclick="clearBank()">清空当前词库</button>
        </p>
        <p id="bankStatus" class="muted"></p>
    </section>

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
        <h2>批量导入</h2>
        <p class="muted">支持每行：word | pos | 中文释义 | example，也支持 Tab 或 CSV。</p>
        <textarea id="bulkText" placeholder="ability | n. | 能力 | Reading builds ability."></textarea>
        <p><button onclick="bulkImport()">批量导入</button></p>
        <p id="bulkStatus" class="muted"></p>
    </section>

    <section>
        <h2>词库进度</h2>
        <div class="grid">
            <input id="wordBaseDate" type="date">
            <input id="wordBaseQuery" placeholder="搜索单词或中文">
            <select id="wordBaseStatus">
                <option value="all">全部</option>
                <option value="untested">未测试</option>
                <option value="work">需要加强</option>
                <option value="learning">学习中</option>
                <option value="mastered">已掌握</option>
            </select>
            <button onclick="loadWordBase()">查看进度</button>
        </div>
        <p id="wordBaseSummary" class="muted"></p>
        <div id="wordBase"></div>
    </section>

    <section>
        <h2>词库搜索</h2>
        <p><input id="q" placeholder="搜索" oninput="loadWords()"></p>
        <div id="words"></div>
    </section>
</main>
<div class="bottom-tabs">
    <div class="inner">
        <a href="../"><strong>W</strong><span>Word</span></a>
        <a class="active" href="./"><strong>V</strong><span>Vocab Base</span></a>
        <a href="../admin/"><strong>A</strong><span>Admin</span></a>
    </div>
</div>
<script>
const api = path => '../api/' + path;

function selectedBankCode() {
    return document.getElementById('newBankCode').value.trim() || document.getElementById('bankCode').value || 'high_school';
}

async function loadBanks() {
    const res = await fetch(api('word-banks.php'));
    const data = await res.json();
    const banks = data.banks || [];
    const select = document.getElementById('bankCode');
    select.innerHTML = banks.map(bank => `<option value="${escapeHtml(bank.code)}">${escapeHtml(bank.title)} (${Number(bank.word_count || 0)})</option>`).join('');
    if (!select.innerHTML) select.innerHTML = '<option value="high_school">高中词库 (0)</option>';
}

async function saveBank() {
    const code = selectedBankCode();
    const title = document.getElementById('bankTitle').value.trim() || code;
    const res = await fetch(api('word-bank-save.php'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({code, title})
    });
    document.getElementById('bankStatus').textContent = res.ok ? `${title} 已准备好。` : '保存失败';
    if (res.ok) document.getElementById('newBankCode').value = '';
    await loadBanks();
    document.getElementById('bankCode').value = code;
}

async function clearBank() {
    if (!confirm('确定清空当前词库所有单词和学习记录吗？')) return;
    const res = await fetch(api('word-bank-clear.php'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bank_code: selectedBankCode()})
    });
    document.getElementById('bankStatus').textContent = res.ok ? '当前词库已清空' : '清空失败';
    await loadBanks();
    loadWords();
    loadWordBase();
}

async function loadWords() {
    const q = encodeURIComponent(document.getElementById('q').value.trim());
    const res = await fetch(api('words.php?bank_code=' + encodeURIComponent(selectedBankCode()) + '&q=' + q));
    const data = await res.json();
    document.getElementById('words').innerHTML = (data.words || []).map(w => `
        <div class="row">
            <strong>${escapeHtml(w.word)}</strong>
            <span>${escapeHtml(w.part_of_speech || '')}</span>
            <span>${escapeHtml(w.meaning_zh || '')}</span>
            <span>${escapeHtml(w.example_en || '无例句')}</span>
            <button onclick="deleteWord(${Number(w.id)})">删除</button>
        </div>
    `).join('') || '<p class="muted">暂无单词</p>';
}

async function saveWord() {
    const payload = {
        bank_code: selectedBankCode(),
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
        await loadBanks();
        loadWords();
        loadWordBase();
    }
}

async function bulkImport() {
    const res = await fetch(api('import.php'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bank_code: selectedBankCode(), text: document.getElementById('bulkText').value})
    });
    const data = await res.json();
    document.getElementById('bulkStatus').textContent = res.ok ? `已导入/更新 ${data.imported || 0} 个词` : '导入失败';
    if (res.ok) {
        document.getElementById('bulkText').value = '';
        await loadBanks();
        loadWords();
        loadWordBase();
    }
}

async function deleteWord(id) {
    const res = await fetch(api('words.php?id=' + id), { method: 'DELETE' });
    if (res.ok) {
        await loadBanks();
        loadWords();
        loadWordBase();
    }
}

async function loadWordBase() {
    const date = document.getElementById('wordBaseDate').value || new Date().toISOString().slice(0, 10);
    const q = document.getElementById('wordBaseQuery').value.trim();
    const status = document.getElementById('wordBaseStatus').value;
    const url = api('wordbase.php?bank_code=' + encodeURIComponent(selectedBankCode()) + '&date=' + encodeURIComponent(date) + '&q=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(status) + '&limit=200');
    const res = await fetch(url);
    const data = await res.json();
    const summary = data.summary || {};
    document.getElementById('wordBaseSummary').textContent =
        `总词 ${summary.total_words || 0}，未测试 ${summary.untested_words || 0}，已掌握 ${summary.mastered_words || 0}，需要加强 ${summary.work_words || 0}`;
    document.getElementById('wordBase').innerHTML = (data.words || []).map(renderWordBaseRow).join('') || '<p class="muted">暂无记录</p>';
}

function renderWordBaseRow(item) {
    return `
        <div class="row">
            <strong>${escapeHtml(item.word)}</strong>
            <span>${escapeHtml(item.mastery_score || 0)}分</span>
            <span>${escapeHtml(item.meaning_zh || '')}</span>
            <span>${escapeHtml(item.status || '')}${item.example_en ? ' / ' + escapeHtml(item.example_en) : ''}</span>
            <span>${escapeHtml(item.part_of_speech || '')}</span>
        </div>
    `;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

document.getElementById('wordBaseDate').value = new Date().toISOString().slice(0, 10);
loadBanks().then(() => {
    loadWords();
    loadWordBase();
});
</script>
</body>
</html>
