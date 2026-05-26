<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function vp_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(VP_DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . VP_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    vp_migrate($pdo);
    vp_seed_if_empty($pdo);
    return $pdo;
}

function vp_migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        level TEXT NOT NULL,
        reason TEXT NOT NULL,
        focus TEXT NOT NULL,
        daily_goal INTEGER NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 100,
        enabled INTEGER NOT NULL DEFAULT 1,
        updated_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS lessons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plan_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        level_label TEXT NOT NULL,
        emoji TEXT NOT NULL,
        goal TEXT NOT NULL,
        example TEXT NOT NULL,
        example_zh TEXT NOT NULL,
        pattern TEXT NOT NULL,
        prefix TEXT NOT NULL,
        ending TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 100,
        FOREIGN KEY(plan_id) REFERENCES plans(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS drills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lesson_id INTEGER NOT NULL,
        answer TEXT NOT NULL,
        prompt TEXT NOT NULL,
        translation TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 100,
        FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
    )');
}

function vp_seed_if_empty(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
    if ($count > 0) {
        return;
    }

    foreach (vp_seed_plans() as $index => $plan) {
        vp_upsert_plan($pdo, $plan, $index + 1);
    }
    vp_touch_version($pdo);
}

function vp_touch_version(PDO $pdo): void
{
    $version = gmdate('YmdHis');
    $stmt = $pdo->prepare('INSERT INTO settings(key, value) VALUES("library_version", :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':value' => $version]);
}

function vp_library_version(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = "library_version"');
    $stmt->execute();
    return (string) ($stmt->fetchColumn() ?: '1');
}

function vp_upsert_plan(PDO $pdo, array $plan, int $sortOrder = 100): int
{
    $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT INTO plans(slug, title, description, level, reason, focus, daily_goal, sort_order, enabled, updated_at)
        VALUES(:slug, :title, :description, :level, :reason, :focus, :daily_goal, :sort_order, :enabled, :updated_at)
        ON CONFLICT(slug) DO UPDATE SET
            title = excluded.title,
            description = excluded.description,
            level = excluded.level,
            reason = excluded.reason,
            focus = excluded.focus,
            daily_goal = excluded.daily_goal,
            sort_order = excluded.sort_order,
            enabled = excluded.enabled,
            updated_at = excluded.updated_at');
    $stmt->execute([
        ':slug' => $plan['slug'],
        ':title' => $plan['title'],
        ':description' => $plan['description'],
        ':level' => $plan['level'],
        ':reason' => $plan['reason'],
        ':focus' => $plan['focus'],
        ':daily_goal' => (int) $plan['dailyGoal'],
        ':sort_order' => $sortOrder,
        ':enabled' => (int) ($plan['enabled'] ?? 1),
        ':updated_at' => $now,
    ]);

    $planId = (int) $pdo->query('SELECT id FROM plans WHERE slug = ' . $pdo->quote($plan['slug']))->fetchColumn();
    $pdo->prepare('DELETE FROM lessons WHERE plan_id = :plan_id')->execute([':plan_id' => $planId]);

    foreach ($plan['lessons'] as $lessonIndex => $lesson) {
        $lessonStmt = $pdo->prepare('INSERT INTO lessons(plan_id, title, category, level_label, emoji, goal, example, example_zh, pattern, prefix, ending, sort_order)
            VALUES(:plan_id, :title, :category, :level_label, :emoji, :goal, :example, :example_zh, :pattern, :prefix, :ending, :sort_order)');
        $lessonStmt->execute([
            ':plan_id' => $planId,
            ':title' => $lesson['title'],
            ':category' => $lesson['category'],
            ':level_label' => $lesson['level'],
            ':emoji' => $lesson['emoji'],
            ':goal' => $lesson['goal'],
            ':example' => $lesson['example'],
            ':example_zh' => $lesson['exampleZh'],
            ':pattern' => $lesson['pattern'],
            ':prefix' => $lesson['prefix'],
            ':ending' => $lesson['ending'],
            ':sort_order' => $lessonIndex + 1,
        ]);
        $lessonId = (int) $pdo->lastInsertId();

        foreach ($lesson['drills'] as $drillIndex => $drill) {
            $drillStmt = $pdo->prepare('INSERT INTO drills(lesson_id, answer, prompt, translation, sort_order)
                VALUES(:lesson_id, :answer, :prompt, :translation, :sort_order)');
            $drillStmt->execute([
                ':lesson_id' => $lessonId,
                ':answer' => $drill['answer'],
                ':prompt' => $drill['prompt'],
                ':translation' => $drill['translation'],
                ':sort_order' => $drillIndex + 1,
            ]);
        }
    }

    return $planId;
}

function vp_plan_payload(PDO $pdo, array $plan): array
{
    $lessonStmt = $pdo->prepare('SELECT * FROM lessons WHERE plan_id = :plan_id ORDER BY sort_order, id');
    $lessonStmt->execute([':plan_id' => $plan['id']]);
    $lessons = [];

    foreach ($lessonStmt->fetchAll() as $lesson) {
        $drillStmt = $pdo->prepare('SELECT answer, prompt, translation FROM drills WHERE lesson_id = :lesson_id ORDER BY sort_order, id');
        $drillStmt->execute([':lesson_id' => $lesson['id']]);
        $lessons[] = [
            'scenario' => $plan['title'],
            'title' => $lesson['title'],
            'category' => $lesson['category'],
            'level' => $lesson['level_label'],
            'emoji' => $lesson['emoji'],
            'goal' => $lesson['goal'],
            'example' => $lesson['example'],
            'exampleZh' => $lesson['example_zh'],
            'pattern' => $lesson['pattern'],
            'prefix' => $lesson['prefix'],
            'ending' => $lesson['ending'],
            'drills' => $drillStmt->fetchAll(),
        ];
    }

    return [
        'id' => (int) $plan['id'],
        'slug' => $plan['slug'],
        'title' => $plan['title'],
        'description' => $plan['description'],
        'level' => $plan['level'],
        'reason' => $plan['reason'],
        'focus' => $plan['focus'],
        'dailyGoal' => (int) $plan['daily_goal'],
        'lessons' => $lessons,
    ];
}

function vp_match_plans(PDO $pdo, array $profile): array
{
    $stmt = $pdo->query('SELECT * FROM plans WHERE enabled = 1');
    $plans = $stmt->fetchAll();

    foreach ($plans as &$plan) {
        $score = 0;
        if (($profile['level'] ?? '') === $plan['level']) {
            $score += 40;
        }
        if (($profile['reason'] ?? '') === $plan['reason']) {
            $score += 30;
        }
        if (($profile['focus'] ?? '') === $plan['focus']) {
            $score += 20;
        }
        $goal = (int) ($profile['dailyGoal'] ?? 10);
        $planGoal = (int) $plan['daily_goal'];
        if ($goal === $planGoal) {
            $score += 15;
        } elseif ($goal > $planGoal) {
            $score += 8;
        }
        $plan['match_score'] = $score;
    }
    unset($plan);

    usort($plans, fn($a, $b) => [$b['match_score'], -$a['sort_order']] <=> [$a['match_score'], -$b['sort_order']]);
    return array_slice($plans, 0, 3);
}

function vp_seed_plans(): array
{
    $makeLesson = function (string $title, string $category, string $level, string $emoji, string $goal, string $example, string $exampleZh, string $pattern, string $prefix, string $ending, array $drills): array {
        return compact('title', 'category', 'level', 'emoji', 'goal', 'example', 'exampleZh', 'pattern', 'prefix', 'ending', 'drills');
    };
    $drills = fn(array $items): array => array_map(fn($row) => ['answer' => $row[0], 'prompt' => $row[1], 'translation' => $row[2]], $items);

    return [
        [
            'slug' => 'beginner-travel-5',
            'title' => 'Beginner Travel Starter',
            'description' => '机场、问路、入住、点餐的生存口语。',
            'level' => 'level-1',
            'reason' => 'travel',
            'focus' => 'confidence',
            'dailyGoal' => 5,
            'lessons' => [
                $makeLesson('At the Airport', 'Travel', 'Beginner', '✈️', 'Say where you need to go.', 'I need to find my gate.', '我需要找到登机口。', 'I need to find ___.', 'I need to find', '.', $drills([['my gate', '我的登机口', '我需要找到我的登机口。'], ['baggage claim', '行李提取处', '我需要找到行李提取处。'], ['the check-in counter', '值机柜台', '我需要找到值机柜台。']])),
                $makeLesson('Asking Directions', 'Travel', 'Beginner', '📍', 'Ask where a place is.', 'Where is the subway?', '地铁在哪里？', 'Where is ___?', 'Where is', '?', $drills([['the subway', '地铁', '地铁在哪里？'], ['the restroom', '洗手间', '洗手间在哪里？'], ['the exit', '出口', '出口在哪里？']])),
                $makeLesson('Ordering Food', 'Food', 'Beginner', '🍜', 'Order one item politely.', 'Can I have water?', '可以给我水吗？', 'Can I have ___?', 'Can I have', '?', $drills([['water', '水', '可以给我水吗？'], ['coffee', '咖啡', '可以给我咖啡吗？'], ['the menu', '菜单', '可以给我菜单吗？']])),
            ],
        ],
        [
            'slug' => 'beginner-travel-10',
            'title' => 'Beginner Travel Routine',
            'description' => '每天 10 分钟练旅行高频句型。',
            'level' => 'level-1',
            'reason' => 'travel',
            'focus' => 'vocabulary',
            'dailyGoal' => 10,
            'lessons' => [
                $makeLesson('Hotel Check-in', 'Travel', 'Beginner', '🏨', 'Check in with simple requests.', 'I have a reservation.', '我有预订。', 'I have ___.', 'I have', '.', $drills([['a reservation', '预订', '我有预订。'], ['a question', '一个问题', '我有一个问题。'], ['two bags', '两个包', '我有两个包。']])),
                $makeLesson('Getting Around', 'Travel', 'Beginner', '🚕', 'Ask for transport help.', 'I want to go downtown.', '我想去市中心。', 'I want to go ___.', 'I want to go', '.', $drills([['downtown', '市中心', '我想去市中心。'], ['to the airport', '去机场', '我想去机场。'], ['to this address', '去这个地址', '我想去这个地址。']])),
                $makeLesson('Simple Problems', 'Travel', 'Beginner', '🧭', 'Explain a small problem.', 'I lost my key.', '我丢了钥匙。', 'I lost ___.', 'I lost', '.', $drills([['my key', '我的钥匙', '我丢了钥匙。'], ['my ticket', '我的票', '我丢了票。'], ['my phone', '我的手机', '我丢了手机。']])),
            ],
        ],
        [
            'slug' => 'beginner-confidence-5',
            'title' => 'Confidence Builder',
            'description' => '用最短句型建立开口信心。',
            'level' => 'level-1',
            'reason' => 'interest',
            'focus' => 'confidence',
            'dailyGoal' => 5,
            'lessons' => [
                $makeLesson('I Can', 'Beginner', 'Beginner', '🏊', 'Talk about abilities.', 'I can swim.', '我会游泳。', 'I can ___.', 'I can', '.', $drills([['ride a bike', '骑自行车', '我会骑自行车。'], ['cook dinner', '做晚饭', '我会做晚饭。'], ['speak English', '说英语', '我会说英语。']])),
                $makeLesson('I Like', 'Beginner', 'Beginner', '⭐', 'Say what you like.', 'I like music.', '我喜欢音乐。', 'I like ___.', 'I like', '.', $drills([['music', '音乐', '我喜欢音乐。'], ['coffee', '咖啡', '我喜欢咖啡。'], ['this place', '这个地方', '我喜欢这个地方。']])),
                $makeLesson('I Need', 'Beginner', 'Beginner', '💬', 'Say what you need.', 'I need help.', '我需要帮助。', 'I need ___.', 'I need', '.', $drills([['help', '帮助', '我需要帮助。'], ['more time', '更多时间', '我需要更多时间。'], ['a break', '休息一下', '我需要休息一下。']])),
            ],
        ],
        [
            'slug' => 'beginner-confidence-10',
            'title' => 'Daily Confidence',
            'description' => '从回应、请求和表达感受开始。',
            'level' => 'level-2',
            'reason' => 'interest',
            'focus' => 'confidence',
            'dailyGoal' => 10,
            'lessons' => [
                $makeLesson('Simple Responses', 'Daily Life', 'Beginner', '🙂', 'Respond naturally.', 'That sounds good.', '听起来不错。', 'That sounds ___.', 'That sounds', '.', $drills([['good', '不错', '听起来不错。'], ['fun', '有趣', '听起来有趣。'], ['hard', '难', '听起来很难。']])),
                $makeLesson('Ask Again', 'Daily Life', 'Beginner', '🔁', 'Ask someone to repeat.', 'Can you say that again?', '你能再说一遍吗？', 'Can you ___?', 'Can you', '?', $drills([['say that again', '再说一遍', '你能再说一遍吗？'], ['speak slowly', '说慢一点', '你能说慢一点吗？'], ['help me', '帮我', '你能帮我吗？']])),
                $makeLesson('Small Feelings', 'Daily Life', 'Beginner', '🌤️', 'Share basic feelings.', 'I feel nervous.', '我感到紧张。', 'I feel ___.', 'I feel', '.', $drills([['nervous', '紧张', '我感到紧张。'], ['ready', '准备好了', '我准备好了。'], ['better', '好些了', '我感觉好些了。']])),
            ],
        ],
        [
            'slug' => 'basic-daily-10',
            'title' => 'Basic Daily Life',
            'description' => '约时间、购物、表达喜好。',
            'level' => 'level-2',
            'reason' => 'communication',
            'focus' => 'confidence',
            'dailyGoal' => 10,
            'lessons' => [
                $makeLesson('Make Plans', 'Daily Life', 'Daily', '🗓️', 'Check if a time works.', 'Does Friday work for you?', '周五适合你吗？', 'Does ___ work for you?', 'Does', 'work for you?', $drills([['Friday', '周五', '周五适合你吗？'], ['tomorrow morning', '明天早上', '明天早上适合你吗？'], ['after lunch', '午饭后', '午饭后适合你吗？']])),
                $makeLesson('Shopping', 'Daily Life', 'Daily', '🛍️', 'Ask about price and size.', 'Do you have a smaller size?', '你们有小一点的尺码吗？', 'Do you have ___?', 'Do you have', '?', $drills([['a smaller size', '小一点的尺码', '你们有小一点的尺码吗？'], ['another color', '其他颜色', '你们有其他颜色吗？'], ['this in stock', '这个有货', '这个有货吗？']])),
                $makeLesson('Preferences', 'Daily Life', 'Daily', '✨', 'Say what you prefer.', 'I prefer tea.', '我更喜欢茶。', 'I prefer ___.', 'I prefer', '.', $drills([['tea', '茶', '我更喜欢茶。'], ['the window seat', '靠窗座位', '我更喜欢靠窗座位。'], ['something lighter', '清淡一点的东西', '我更喜欢清淡一点的东西。']])),
            ],
        ],
        [
            'slug' => 'basic-vocabulary-15',
            'title' => 'Useful Vocabulary Blocks',
            'description' => '积累可以马上说出口的词块。',
            'level' => 'level-2',
            'reason' => 'interest',
            'focus' => 'vocabulary',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('Want To', 'Daily Life', 'Daily', '🧩', 'Use want to with action chunks.', 'I want to try it.', '我想试试。', 'I want to ___.', 'I want to', '.', $drills([['try it', '试试', '我想试试。'], ['learn more', '多了解一点', '我想多了解一点。'], ['take a look', '看一看', '我想看一看。']])),
                $makeLesson('Need To', 'Daily Life', 'Daily', '🛠️', 'Talk about necessary actions.', 'I need to check my schedule.', '我需要查一下日程。', 'I need to ___.', 'I need to', '.', $drills([['check my schedule', '查日程', '我需要查一下日程。'], ['call my friend', '打给朋友', '我需要打给朋友。'], ['finish this first', '先完成这个', '我需要先完成这个。']])),
                $makeLesson('Can We', 'Daily Life', 'Daily', '🤝', 'Make simple suggestions.', 'Can we start now?', '我们可以现在开始吗？', 'Can we ___?', 'Can we', '?', $drills([['start now', '现在开始', '我们可以现在开始吗？'], ['meet tomorrow', '明天见', '我们可以明天见吗？'], ['try again', '再试一次', '我们可以再试一次吗？']])),
            ],
        ],
        [
            'slug' => 'conversation-communication-15',
            'title' => 'Conversation Builder',
            'description' => '观点表达、追问、确认理解。',
            'level' => 'level-3',
            'reason' => 'communication',
            'focus' => 'confidence',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('Give Opinions', 'Daily Life', 'Conversation', '💡', 'Share an opinion clearly.', 'I think it is useful.', '我觉得它有用。', 'I think it is ___.', 'I think it is', '.', $drills([['useful', '有用', '我觉得它有用。'], ['important', '重要', '我觉得它重要。'], ['too expensive', '太贵', '我觉得它太贵。']])),
                $makeLesson('Ask Follow-ups', 'Daily Life', 'Conversation', '❓', 'Ask for more detail.', 'What do you mean by that?', '你那是什么意思？', 'What do you mean by ___?', 'What do you mean by', '?', $drills([['that', '那个', '你那是什么意思？'], ['soon', '很快', '你说很快是什么意思？'], ['better', '更好', '你说更好是什么意思？']])),
                $makeLesson('Confirm Understanding', 'Daily Life', 'Conversation', '✅', 'Check if you understood.', 'So you mean we should wait.', '所以你的意思是我们应该等。', 'So you mean we should ___.', 'So you mean we should', '.', $drills([['wait', '等', '所以你的意思是我们应该等。'], ['leave now', '现在离开', '所以你的意思是我们应该现在离开。'], ['try another way', '换个方法', '所以你的意思是我们应该换个方法。']])),
            ],
        ],
        [
            'slug' => 'conversation-listening-15',
            'title' => 'Listen and Answer',
            'description' => '听懂问题并用固定句型回答。',
            'level' => 'level-3',
            'reason' => 'communication',
            'focus' => 'listening',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('Answer Why', 'Daily Life', 'Conversation', '👂', 'Answer why questions.', 'Because I need more practice.', '因为我需要更多练习。', 'Because I need ___.', 'Because I need', '.', $drills([['more practice', '更多练习', '因为我需要更多练习。'], ['more time', '更多时间', '因为我需要更多时间。'], ['a clear plan', '清晰计划', '因为我需要清晰计划。']])),
                $makeLesson('Answer When', 'Daily Life', 'Conversation', '⏰', 'Answer time questions.', 'I can do it after work.', '我下班后可以做。', 'I can do it ___.', 'I can do it', '.', $drills([['after work', '下班后', '我下班后可以做。'], ['this weekend', '这个周末', '我这个周末可以做。'], ['in the morning', '早上', '我早上可以做。']])),
                $makeLesson('Answer Preferences', 'Daily Life', 'Conversation', '🎧', 'Answer preference questions.', 'I would rather stay home.', '我宁愿待在家。', 'I would rather ___.', 'I would rather', '.', $drills([['stay home', '待在家', '我宁愿待在家。'], ['go earlier', '早点去', '我宁愿早点去。'], ['keep it simple', '保持简单', '我宁愿保持简单。']])),
            ],
        ],
        [
            'slug' => 'skilled-career-20',
            'title' => 'Career Speaking',
            'description' => '会议表达、面试回答、工作更新。',
            'level' => 'level-4',
            'reason' => 'career',
            'focus' => 'vocabulary',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Work Updates', 'Career', 'Skilled', '💼', 'Give concise updates.', 'I am working on the report.', '我正在做报告。', 'I am working on ___.', 'I am working on', '.', $drills([['the report', '报告', '我正在做报告。'], ['the presentation', '演示文稿', '我正在做演示文稿。'], ['the next version', '下一个版本', '我正在做下一个版本。']])),
                $makeLesson('Meeting Input', 'Career', 'Skilled', '📊', 'Add an idea in a meeting.', 'I would suggest a simpler plan.', '我建议一个更简单的计划。', 'I would suggest ___.', 'I would suggest', '.', $drills([['a simpler plan', '更简单的计划', '我建议一个更简单的计划。'], ['more testing', '更多测试', '我建议更多测试。'], ['a short break', '短暂休息', '我建议短暂休息。']])),
                $makeLesson('Interview Answers', 'Career', 'Skilled', '🎯', 'Explain strengths.', 'I am good at solving problems.', '我擅长解决问题。', 'I am good at ___.', 'I am good at', '.', $drills([['solving problems', '解决问题', '我擅长解决问题。'], ['learning quickly', '快速学习', '我擅长快速学习。'], ['working with teams', '团队合作', '我擅长团队合作。']])),
            ],
        ],
        [
            'slug' => 'skilled-pronunciation-15',
            'title' => 'Pronunciation Flow',
            'description' => '连读、重音、短句节奏。',
            'level' => 'level-4',
            'reason' => 'interest',
            'focus' => 'pronunciation',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('Linking Sounds', 'Pronunciation', 'Skilled', '👄', 'Practice linked chunks.', 'I want to ask about it.', '我想问一下这件事。', 'I want to ask about ___.', 'I want to ask about', '.', $drills([['it', '这件事', '我想问一下这件事。'], ['the plan', '计划', '我想问一下计划。'], ['the price', '价格', '我想问一下价格。']])),
                $makeLesson('Sentence Stress', 'Pronunciation', 'Skilled', '🎙️', 'Stress the key idea.', 'The main point is quality.', '重点是质量。', 'The main point is ___.', 'The main point is', '.', $drills([['quality', '质量', '重点是质量。'], ['timing', '时间安排', '重点是时间安排。'], ['practice', '练习', '重点是练习。']])),
                $makeLesson('Natural Rhythm', 'Pronunciation', 'Skilled', '🎵', 'Keep short phrases smooth.', 'Let me think about it.', '让我想一下。', 'Let me ___.', 'Let me', '.', $drills([['think about it', '想一下', '让我想一下。'], ['check first', '先查一下', '让我先查一下。'], ['try again', '再试一次', '让我再试一次。']])),
            ],
        ],
        [
            'slug' => 'fluent-career-20',
            'title' => 'Fluent Career Precision',
            'description' => '精准表达、委婉建议、反驳与补充。',
            'level' => 'level-5',
            'reason' => 'career',
            'focus' => 'vocabulary',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Polite Pushback', 'Career', 'Fluent', '🧠', 'Disagree without sounding harsh.', 'I see your point, but I think we should wait.', '我理解你的观点，但我觉得我们应该等。', 'I see your point, but I think we should ___.', 'I see your point, but I think we should', '.', $drills([['wait', '等', '我理解你的观点，但我觉得我们应该等。'], ['test it first', '先测试', '我理解你的观点，但我觉得我们应该先测试。'], ['review the data', '看数据', '我理解你的观点，但我觉得我们应该看数据。']])),
                $makeLesson('Soft Suggestions', 'Career', 'Fluent', '💬', 'Make suggestions tactfully.', 'It might be better to simplify it.', '简化它可能更好。', 'It might be better to ___.', 'It might be better to', '.', $drills([['simplify it', '简化它', '简化它可能更好。'], ['ask the client', '问客户', '问客户可能更好。'], ['delay the launch', '推迟发布', '推迟发布可能更好。']])),
                $makeLesson('Add Nuance', 'Career', 'Fluent', '🪄', 'Add a precise condition.', 'It depends on the timeline.', '这取决于时间线。', 'It depends on ___.', 'It depends on', '.', $drills([['the timeline', '时间线', '这取决于时间线。'], ['the budget', '预算', '这取决于预算。'], ['the audience', '受众', '这取决于受众。']])),
            ],
        ],
        [
            'slug' => 'fluent-communication-20',
            'title' => 'Natural Conversation',
            'description' => '自然聊天、观点扩展、故事表达。',
            'level' => 'level-5',
            'reason' => 'communication',
            'focus' => 'confidence',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Expand Ideas', 'Daily Life', 'Fluent', '🌱', 'Develop a thought naturally.', 'What I mean is that it takes time.', '我的意思是这需要时间。', 'What I mean is that ___.', 'What I mean is that', '.', $drills([['it takes time', '这需要时间', '我的意思是这需要时间。'], ['we need context', '我们需要背景', '我的意思是我们需要背景。'], ['it is not that simple', '没那么简单', '我的意思是没那么简单。']])),
                $makeLesson('Tell Stories', 'Daily Life', 'Fluent', '📖', 'Set up a short story.', 'The funny thing is that I forgot my keys.', '有趣的是我忘了带钥匙。', 'The funny thing is that ___.', 'The funny thing is that', '.', $drills([['I forgot my keys', '我忘了带钥匙', '有趣的是我忘了带钥匙。'], ['we met before', '我们以前见过', '有趣的是我们以前见过。'], ['it worked anyway', '结果还是成功了', '有趣的是结果还是成功了。']])),
                $makeLesson('React Naturally', 'Daily Life', 'Fluent', '✨', 'React with more nuance.', 'That is exactly what I was thinking.', '这正是我在想的。', 'That is exactly what ___.', 'That is exactly what', '.', $drills([['I was thinking', '我在想的', '这正是我在想的。'], ['we needed', '我们需要的', '这正是我们需要的。'], ['I meant', '我的意思', '这正是我的意思。']])),
            ],
        ],
        [
            'slug' => 'general-interest-10',
            'title' => 'Interest Starter Pack',
            'description' => '围绕兴趣、娱乐、习惯建立口语反射。',
            'level' => 'level-2',
            'reason' => 'interest',
            'focus' => 'vocabulary',
            'dailyGoal' => 10,
            'lessons' => [
                $makeLesson('Hobbies', 'Daily Life', 'Daily', '🎮', 'Talk about hobbies.', 'I am into photography.', '我喜欢摄影。', 'I am into ___.', 'I am into', '.', $drills([['photography', '摄影', '我喜欢摄影。'], ['cooking', '做饭', '我喜欢做饭。'], ['video games', '电子游戏', '我喜欢电子游戏。']])),
                $makeLesson('Habits', 'Daily Life', 'Daily', '☕', 'Talk about routines.', 'I usually read at night.', '我通常晚上读书。', 'I usually ___ at night.', 'I usually', 'at night.', $drills([['read', '读书', '我通常晚上读书。'], ['study', '学习', '我通常晚上学习。'], ['walk', '散步', '我通常晚上散步。']])),
                $makeLesson('Entertainment', 'Daily Life', 'Daily', '🎬', 'Talk about what you watch.', 'I just watched a movie.', '我刚看了一部电影。', 'I just watched ___.', 'I just watched', '.', $drills([['a movie', '一部电影', '我刚看了一部电影。'], ['a show', '一个节目', '我刚看了一个节目。'], ['a video', '一个视频', '我刚看了一个视频。']])),
            ],
        ],
        [
            'slug' => 'other-mixed-10',
            'title' => 'Mixed Starter Pack',
            'description' => '适合不确定目标的通用生活口语。',
            'level' => 'level-2',
            'reason' => 'other',
            'focus' => 'other',
            'dailyGoal' => 10,
            'lessons' => [
                $makeLesson('Ask for Help', 'Daily Life', 'Daily', '🆘', 'Ask for help clearly.', 'Could you help me with this?', '你能帮我处理这个吗？', 'Could you help me with ___?', 'Could you help me with', '?', $drills([['this', '这个', '你能帮我处理这个吗？'], ['my English', '我的英语', '你能帮我学英语吗？'], ['the form', '表格', '你能帮我填表吗？']])),
                $makeLesson('Make Choices', 'Daily Life', 'Daily', '🔀', 'Say which option you want.', 'I will take the first one.', '我要第一个。', 'I will take ___.', 'I will take', '.', $drills([['the first one', '第一个', '我要第一个。'], ['the blue one', '蓝色那个', '我要蓝色那个。'], ['this option', '这个选项', '我要这个选项。']])),
                $makeLesson('Explain Needs', 'Daily Life', 'Daily', '📌', 'Explain what you are trying to do.', 'I am trying to improve my speaking.', '我正在努力提高口语。', 'I am trying to ___.', 'I am trying to', '.', $drills([['improve my speaking', '提高口语', '我正在努力提高口语。'], ['find a better way', '找到更好的方法', '我正在努力找到更好的方法。'], ['learn this pattern', '学习这个句型', '我正在努力学习这个句型。']])),
            ],
        ],
        [
            'slug' => 'beginner-listening-5',
            'title' => 'Beginner Listening Cues',
            'description' => '用短问题训练听懂关键词并回答。',
            'level' => 'level-1',
            'reason' => 'communication',
            'focus' => 'listening',
            'dailyGoal' => 5,
            'lessons' => [
                $makeLesson('Hear and Answer', 'Beginner', 'Beginner', '👂', 'Answer very short questions.', 'Yes, I can help.', '是的，我可以帮忙。', 'Yes, I can ___.', 'Yes, I can', '.', $drills([['help', '帮忙', '是的，我可以帮忙。'], ['try again', '再试一次', '是的，我可以再试一次。'], ['do that', '做那个', '是的，我可以做那个。']])),
            ],
        ],
        [
            'slug' => 'beginner-pronunciation-10',
            'title' => 'Beginner Sound Basics',
            'description' => '从清晰短句开始改善发音。',
            'level' => 'level-1',
            'reason' => 'interest',
            'focus' => 'pronunciation',
            'dailyGoal' => 10,
            'lessons' => [
                $makeLesson('Clear I Am', 'Pronunciation', 'Beginner', '👄', 'Say I am phrases clearly.', 'I am ready.', '我准备好了。', 'I am ___.', 'I am', '.', $drills([['ready', '准备好了', '我准备好了。'], ['busy', '忙', '我很忙。'], ['happy', '开心', '我很开心。']])),
            ],
        ],
        [
            'slug' => 'conversation-travel-15',
            'title' => 'Travel Conversations',
            'description' => '用会话级句型处理旅行中的真实互动。',
            'level' => 'level-3',
            'reason' => 'travel',
            'focus' => 'confidence',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('Explain a Situation', 'Travel', 'Conversation', '🧳', 'Explain what happened while traveling.', 'My flight was delayed.', '我的航班延误了。', 'My ___ was delayed.', 'My', 'was delayed.', $drills([['flight', '航班', '我的航班延误了。'], ['train', '火车', '我的火车延误了。'], ['appointment', '预约', '我的预约延误了。']])),
            ],
        ],
        [
            'slug' => 'conversation-vocabulary-15',
            'title' => 'Conversation Word Blocks',
            'description' => '把常用词块变成会话反应。',
            'level' => 'level-3',
            'reason' => 'interest',
            'focus' => 'vocabulary',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('It Feels Like', 'Daily Life', 'Conversation', '💭', 'Describe impressions naturally.', 'It feels like a good idea.', '这感觉像个好主意。', 'It feels like ___.', 'It feels like', '.', $drills([['a good idea', '好主意', '这感觉像个好主意。'], ['too much work', '工作太多', '这感觉工作量太大。'], ['the right choice', '正确选择', '这感觉是正确选择。']])),
            ],
        ],
        [
            'slug' => 'conversation-pronunciation-20',
            'title' => 'Conversation Rhythm',
            'description' => '训练中长句的停顿、重音和自然节奏。',
            'level' => 'level-3',
            'reason' => 'communication',
            'focus' => 'pronunciation',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Stress the Contrast', 'Pronunciation', 'Conversation', '⚖️', 'Stress contrast clearly.', 'I said today, not tomorrow.', '我说的是今天，不是明天。', 'I said ___, not ___.', 'I said', '.', $drills([['today, tomorrow', '今天，明天', '我说的是今天，不是明天。'], ['here, there', '这里，那里', '我说的是这里，不是那里。'], ['now, later', '现在，之后', '我说的是现在，不是之后。']])),
            ],
        ],
        [
            'slug' => 'skilled-travel-20',
            'title' => 'Skilled Travel Problem Solving',
            'description' => '处理改签、投诉、复杂请求。',
            'level' => 'level-4',
            'reason' => 'travel',
            'focus' => 'confidence',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Request a Change', 'Travel', 'Skilled', '🔄', 'Ask for changes politely.', 'Would it be possible to change my room?', '可以换我的房间吗？', 'Would it be possible to change ___?', 'Would it be possible to change', '?', $drills([['my room', '我的房间', '可以换我的房间吗？'], ['my seat', '我的座位', '可以换我的座位吗？'], ['the booking', '预订', '可以更改预订吗？']])),
            ],
        ],
        [
            'slug' => 'skilled-listening-20',
            'title' => 'Skilled Listening Responses',
            'description' => '听懂细节后做更准确回应。',
            'level' => 'level-4',
            'reason' => 'communication',
            'focus' => 'listening',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Clarify Details', 'Daily Life', 'Skilled', '🔎', 'Ask for exact details.', 'Could you clarify the deadline?', '你能说明一下截止日期吗？', 'Could you clarify ___?', 'Could you clarify', '?', $drills([['the deadline', '截止日期', '你能说明一下截止日期吗？'], ['the next step', '下一步', '你能说明一下下一步吗？'], ['the main issue', '主要问题', '你能说明一下主要问题吗？']])),
            ],
        ],
        [
            'slug' => 'skilled-vocabulary-15',
            'title' => 'Skilled Expression Toolkit',
            'description' => '提升表达精度和替换词块。',
            'level' => 'level-4',
            'reason' => 'career',
            'focus' => 'vocabulary',
            'dailyGoal' => 15,
            'lessons' => [
                $makeLesson('Prioritize', 'Career', 'Skilled', '📌', 'Talk about priorities.', 'We should prioritize quality.', '我们应该优先考虑质量。', 'We should prioritize ___.', 'We should prioritize', '.', $drills([['quality', '质量', '我们应该优先考虑质量。'], ['speed', '速度', '我们应该优先考虑速度。'], ['the user experience', '用户体验', '我们应该优先考虑用户体验。']])),
            ],
        ],
        [
            'slug' => 'fluent-travel-20',
            'title' => 'Fluent Travel Negotiation',
            'description' => '更自然地协商、解释和争取方案。',
            'level' => 'level-5',
            'reason' => 'travel',
            'focus' => 'confidence',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Negotiate Politely', 'Travel', 'Fluent', '🤝', 'Ask for a better solution.', 'Is there any flexibility on the price?', '价格上还有弹性吗？', 'Is there any flexibility on ___?', 'Is there any flexibility on', '?', $drills([['the price', '价格', '价格上还有弹性吗？'], ['the check-in time', '入住时间', '入住时间上还有弹性吗？'], ['the cancellation policy', '取消政策', '取消政策上还有弹性吗？']])),
            ],
        ],
        [
            'slug' => 'fluent-listening-20',
            'title' => 'Fluent Listening Nuance',
            'description' => '听懂隐含意思并自然回应。',
            'level' => 'level-5',
            'reason' => 'communication',
            'focus' => 'listening',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Infer Meaning', 'Daily Life', 'Fluent', '🧠', 'Respond to implied meaning.', 'It sounds like you are worried about timing.', '听起来你担心时间。', 'It sounds like you are worried about ___.', 'It sounds like you are worried about', '.', $drills([['timing', '时间', '听起来你担心时间。'], ['quality', '质量', '听起来你担心质量。'], ['the result', '结果', '听起来你担心结果。']])),
            ],
        ],
        [
            'slug' => 'fluent-pronunciation-20',
            'title' => 'Fluent Delivery Polish',
            'description' => '把高级表达说得更自然、更有节奏。',
            'level' => 'level-5',
            'reason' => 'interest',
            'focus' => 'pronunciation',
            'dailyGoal' => 20,
            'lessons' => [
                $makeLesson('Polished Framing', 'Pronunciation', 'Fluent', '🎙️', 'Frame ideas with natural delivery.', 'The way I see it, we need more context.', '在我看来，我们需要更多背景。', 'The way I see it, we need ___.', 'The way I see it, we need', '.', $drills([['more context', '更多背景', '在我看来，我们需要更多背景。'], ['a clearer goal', '更清晰的目标', '在我看来，我们需要更清晰的目标。'], ['more examples', '更多例子', '在我看来，我们需要更多例子。']])),
            ],
        ],
    ];
}
