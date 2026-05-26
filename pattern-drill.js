const fallbackLessons = [
  {
    scenario: "能力表达",
    title: "能力表达",
    category: "Beginner",
    level: "Beginner",
    emoji: "🏊",
    goal: "用 I can 表达自己会做什么。",
    example: "I can swim.",
    exampleZh: "我会游泳。",
    pattern: "I can ___.",
    prefix: "I can",
    ending: ".",
    drills: [
      { answer: "ride a bike", prompt: "骑自行车", translation: "我会骑自行车。" },
      { answer: "cook dinner", prompt: "做晚饭", translation: "我会做晚饭。" },
      { answer: "speak English", prompt: "说英语", translation: "我会说英语。" },
      { answer: "drive a car", prompt: "开车", translation: "我会开车。" },
    ],
  },
  {
    scenario: "礼貌请求",
    title: "礼貌点餐",
    category: "Food",
    level: "Daily",
    emoji: "☕",
    goal: "礼貌地请求食物、饮品或服务。",
    example: "Could I get some water?",
    exampleZh: "可以给我一些水吗？",
    pattern: "Could I get ___?",
    prefix: "Could I get",
    ending: "?",
    drills: [
      { answer: "an iced latte", prompt: "一杯冰拿铁", translation: "可以给我一杯冰拿铁吗？" },
      { answer: "the bill", prompt: "账单", translation: "可以给我账单吗？" },
      { answer: "a table for two", prompt: "两人桌", translation: "可以给我一张两人桌吗？" },
      { answer: "this to go", prompt: "这个打包", translation: "这个可以给我打包吗？" },
    ],
  },
  {
    scenario: "寻找地点",
    title: "寻找地点",
    category: "Travel",
    level: "Daily",
    emoji: "📍",
    goal: "说清楚你正在找什么地点或东西。",
    example: "I’m looking for a cafe.",
    exampleZh: "我在找一家咖啡店。",
    pattern: "I’m looking for ___.",
    prefix: "I’m looking for",
    ending: ".",
    drills: [
      { answer: "the subway station", prompt: "地铁站", translation: "我在找地铁站。" },
      { answer: "a pharmacy", prompt: "药店", translation: "我在找一家药店。" },
      { answer: "Gate 12", prompt: "12 号登机口", translation: "我在找 12 号登机口。" },
      { answer: "somewhere to sit", prompt: "能坐的地方", translation: "我在找一个能坐的地方。" },
    ],
  },
  {
    scenario: "约时间",
    title: "约时间",
    category: "Daily Life",
    level: "Beginner",
    emoji: "🗓️",
    goal: "确认某个时间是否适合对方。",
    example: "Does Friday work for you?",
    exampleZh: "周五适合你吗？",
    pattern: "Does ___ work for you?",
    prefix: "Does",
    ending: "work for you?",
    drills: [
      { answer: "tomorrow morning", prompt: "明天早上", translation: "明天早上适合你吗？" },
      { answer: "three pm", prompt: "下午三点", translation: "下午三点适合你吗？" },
      { answer: "next Monday", prompt: "下周一", translation: "下周一适合你吗？" },
      { answer: "after lunch", prompt: "午饭后", translation: "午饭后适合你吗？" },
    ],
  },
];
let lessons = [...fallbackLessons];

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const categories = ["All", "Beginner", "Daily Life", "Food", "Travel", "Career", "Pronunciation"];
const categoryLabels = {
  All: "全部",
  Beginner: "入门",
  "Daily Life": "日常",
  Food: "餐饮",
  Travel: "旅行",
  Career: "职场",
  Pronunciation: "发音",
};
const levelLabels = {
  Beginner: "初学者",
  Daily: "基础",
  Conversation: "会话",
  Skilled: "熟练",
  Fluent: "流利",
  "level-1": "初学者",
  "level-2": "基础",
  "level-3": "会话",
  "level-4": "熟练",
  "level-5": "流利",
};
const planTitleLabels = {
  "beginner-travel-5": "初学旅行 5 分钟",
  "beginner-travel-10": "初学旅行 10 分钟",
  "beginner-confidence-5": "开口自信入门",
  "beginner-confidence-10": "每日自信练习",
  "basic-daily-10": "基础日常口语",
  "basic-vocabulary-15": "实用词块练习",
  "conversation-communication-15": "会话表达训练",
  "conversation-listening-15": "听懂并回答",
  "skilled-career-20": "职场口语进阶",
  "skilled-pronunciation-15": "发音连贯训练",
  "fluent-career-20": "流利职场表达",
  "fluent-communication-20": "自然聊天表达",
  "general-interest-10": "兴趣口语入门",
  "other-mixed-10": "通用生活口语",
  "beginner-listening-5": "初学听力提示",
  "beginner-pronunciation-10": "初学发音基础",
  "conversation-travel-15": "旅行会话训练",
  "conversation-vocabulary-15": "会话词块训练",
  "conversation-pronunciation-20": "会话节奏训练",
  "skilled-travel-20": "熟练旅行应对",
  "skilled-listening-20": "熟练听力回应",
  "skilled-vocabulary-15": "熟练表达工具箱",
  "fluent-travel-20": "流利旅行协商",
  "fluent-listening-20": "流利听力细节",
  "fluent-pronunciation-20": "流利表达打磨",
};
const lessonTitleLabels = {
  "At the Airport": "机场沟通",
  "Asking Directions": "问路",
  "Ordering Food": "点餐",
  "Hotel Check-in": "酒店入住",
  "Getting Around": "交通出行",
  "Simple Problems": "简单问题",
  "I Can": "我会做什么",
  "I Like": "表达喜欢",
  "I Need": "表达需求",
  "Simple Responses": "简单回应",
  "Ask Again": "请求重复",
  "Small Feelings": "表达感受",
  "Make Plans": "约时间",
  Shopping: "购物",
  Preferences: "表达偏好",
  "Want To": "想做什么",
  "Need To": "需要做什么",
  "Can We": "提出建议",
  "Give Opinions": "表达观点",
  "Ask Follow-ups": "继续追问",
  "Confirm Understanding": "确认理解",
  "Answer Why": "回答原因",
  "Answer When": "回答时间",
  "Answer Preferences": "回答偏好",
  "Work Updates": "工作进展",
  "Meeting Input": "会议发言",
  "Interview Answers": "面试回答",
  "Linking Sounds": "连读练习",
  "Sentence Stress": "句子重音",
  "Natural Rhythm": "自然节奏",
  "Polite Pushback": "委婉反驳",
  "Soft Suggestions": "委婉建议",
  "Add Nuance": "补充细节",
  "Expand Ideas": "扩展观点",
  "Tell Stories": "讲小故事",
  "React Naturally": "自然回应",
  Hobbies: "兴趣爱好",
  Habits: "日常习惯",
  Entertainment: "娱乐表达",
  "Ask for Help": "请求帮助",
  "Make Choices": "做选择",
  "Explain Needs": "说明需求",
  "Hear and Answer": "听懂并回答",
  "Clear I Am": "清晰说 I am",
  "Explain a Situation": "说明情况",
  "It Feels Like": "描述感受",
  "Stress the Contrast": "强调对比",
  "Request a Change": "请求更改",
  "Clarify Details": "确认细节",
  Prioritize: "表达优先级",
  "Negotiate Politely": "礼貌协商",
  "Infer Meaning": "理解隐含意思",
  "Polished Framing": "自然表达框架",
};
const goalLabels = {
  "Say where you need to go.": "说清楚你需要去哪里。",
  "Ask where a place is.": "询问某个地点在哪里。",
  "Order one item politely.": "礼貌地点一个东西。",
  "Check in with simple requests.": "用简单请求完成酒店入住。",
  "Ask for transport help.": "请求交通出行帮助。",
  "Explain a small problem.": "说明一个小问题。",
  "Talk about abilities.": "表达自己会做什么。",
  "Say what you like.": "表达自己喜欢什么。",
  "Say what you need.": "表达自己需要什么。",
  "Respond naturally.": "自然地做出回应。",
  "Ask someone to repeat.": "请对方重复或放慢速度。",
  "Share basic feelings.": "表达基本感受。",
  "Check if a time works.": "确认某个时间是否合适。",
  "Ask about price and size.": "询问价格、尺码或库存。",
  "Say what you prefer.": "表达自己的偏好。",
  "Use want to with action chunks.": "用 want to 连接常用动作词块。",
  "Talk about necessary actions.": "说明必须要做的事。",
  "Make simple suggestions.": "提出简单建议。",
  "Share an opinion clearly.": "清楚表达观点。",
  "Ask for more detail.": "追问更多细节。",
  "Check if you understood.": "确认自己是否理解对方。",
  "Answer why questions.": "回答为什么。",
  "Answer time questions.": "回答时间相关问题。",
  "Answer preference questions.": "回答偏好问题。",
  "Give concise updates.": "简洁汇报进展。",
  "Add an idea in a meeting.": "在会议中补充想法。",
  "Explain strengths.": "说明自己的优势。",
  "Practice linked chunks.": "练习连读词块。",
  "Stress the key idea.": "突出句子重点。",
  "Keep short phrases smooth.": "把短语说得更顺。",
  "Disagree without sounding harsh.": "不生硬地表达不同意见。",
  "Make suggestions tactfully.": "更委婉地提出建议。",
  "Add a precise condition.": "补充更准确的条件。",
  "Develop a thought naturally.": "自然展开一个想法。",
  "Set up a short story.": "开始讲一个小故事。",
  "React with more nuance.": "更有层次地回应。",
  "Talk about hobbies.": "谈论兴趣爱好。",
  "Talk about routines.": "谈论日常习惯。",
  "Talk about what you watch.": "谈论你看过的内容。",
  "Ask for help clearly.": "清楚地请求帮助。",
  "Say which option you want.": "说出你想选哪一个。",
  "Explain what you are trying to do.": "说明你正在尝试做什么。",
  "Answer very short questions.": "回答很短的问题。",
  "Say I am phrases clearly.": "清晰说出 I am 句型。",
  "Explain what happened while traveling.": "说明旅行中发生的情况。",
  "Describe impressions naturally.": "自然描述你的感受或印象。",
  "Stress contrast clearly.": "清楚强调对比。",
  "Ask for changes politely.": "礼貌地请求更改。",
  "Ask for exact details.": "询问准确细节。",
  "Talk about priorities.": "表达优先级。",
  "Ask for a better solution.": "争取更好的解决方案。",
  "Respond to implied meaning.": "回应对方话里的隐含意思。",
  "Frame ideas with natural delivery.": "用自然节奏表达观点框架。",
};
const apiStorageKey = "patternDrillApiSettings";
const libraryStorageKey = "patternDrillLibrary";
const onboardingStorageKey = "patternDrillLearningProfile";
const onboardingSteps = [
  {
    id: "level",
    question: "你的英语口语水平如何？",
    hint: "选择最接近你现在开口表达的状态。",
    options: [
      { value: "level-1", mark: "1", title: "等级 1：初学者", detail: "我可以自我介绍，并说出简单短语。" },
      { value: "level-2", mark: "2", title: "等级 2：基础", detail: "我能理解简单句子，但说话容易卡住。" },
      { value: "level-3", mark: "3", title: "等级 3：会话", detail: "我可以进行日常对话，并表达基本观点。" },
      { value: "level-4", mark: "4", title: "等级 4：熟练", detail: "我能讨论多数话题，但还想更自然。" },
      { value: "level-5", mark: "5", title: "等级 5：流利", detail: "我想提升表达细节和接近母语者的自然度。" },
    ],
  },
  {
    id: "reason",
    question: "你为什么想提高英语口语？",
    hint: "我们会据此优先推荐练习主题。",
    options: [
      { value: "travel", mark: "✈️", title: "旅行", detail: "问路、点餐、入住、交通。" },
      { value: "interest", mark: "🌟", title: "个人兴趣", detail: "让英语成为日常表达的一部分。" },
      { value: "communication", mark: "🧑", title: "与人交流", detail: "聊天、社交、表达想法。" },
      { value: "career", mark: "💼", title: "职业发展", detail: "面试、会议、工作沟通。" },
      { value: "other", mark: "🎯", title: "其他", detail: "先从通用生活口语开始。" },
    ],
  },
  {
    id: "dailyGoal",
    question: "你的每日学习目标是什么？",
    hint: "短时间高频练习更适合句型口语训练。",
    options: [
      { value: "5", mark: "5", title: "5 分钟 / 天", detail: "随意，保持感觉。" },
      { value: "10", mark: "10", title: "10 分钟 / 天", detail: "规律，适合每天打卡。" },
      { value: "15", mark: "15", title: "15 分钟 / 天", detail: "认真，能完成多轮跟读。" },
      { value: "20", mark: "20", title: "20 分钟 / 天", detail: "专注，适合深度训练。" },
    ],
  },
  {
    id: "focus",
    question: "你最想改善哪个方面？",
    hint: "反馈会围绕这个目标组织。",
    options: [
      { value: "confidence", mark: "🗣️", title: "增强自信", detail: "先让自己敢开口。" },
      { value: "vocabulary", mark: "🎓", title: "学习实用词汇", detail: "积累能马上说出来的词块。" },
      { value: "listening", mark: "👂", title: "提高听力", detail: "听懂句型和替换内容。" },
      { value: "pronunciation", mark: "👄", title: "改善发音", detail: "更重视发音、重音和连读。" },
      { value: "other", mark: "🎯", title: "其他", detail: "先用综合练习建立口语反射。" },
    ],
  },
];
const apiProviders = [
  {
    id: "kimi",
    name: "Kimi",
    baseUrl: "https://api.moonshot.cn/v1",
    model: "moonshot-v1-8k",
  },
  {
    id: "doubao",
    name: "豆包",
    baseUrl: "https://ark.cn-beijing.volces.com/api/v3",
    model: "doubao-lite-4k",
  },
  {
    id: "deepseek",
    name: "DeepSeek",
    baseUrl: "https://api.deepseek.com/v1",
    model: "deepseek-chat",
  },
  {
    id: "custom",
    name: "自定义 OpenAI 兼容接口",
    baseUrl: "",
    model: "",
  },
];

const state = {
  view: "onboarding",
  activeCategory: "All",
  onboardingStep: 0,
  onboardingAnswers: {},
  selectedPlanSlug: "",
  library: null,
  lessonIndex: 0,
  drillIndex: 0,
  isRecording: false,
  mediaRecorder: null,
  recognition: null,
  audioChunks: [],
  recordingUrl: "",
  transcript: "",
};

const elements = {
  onboardingView: document.querySelector("#onboardingView"),
  onboardingBackBtn: document.querySelector("#onboardingBackBtn"),
  onboardingProgressFill: document.querySelector("#onboardingProgressFill"),
  onboardingStepLabel: document.querySelector("#onboardingStepLabel"),
  onboardingQuestion: document.querySelector("#onboardingQuestion"),
  onboardingHint: document.querySelector("#onboardingHint"),
  onboardingOptions: document.querySelector("#onboardingOptions"),
  onboardingContinueBtn: document.querySelector("#onboardingContinueBtn"),
  homeView: document.querySelector("#homeView"),
  settingsView: document.querySelector("#settingsView"),
  trainingView: document.querySelector("#trainingView"),
  categoryTabs: document.querySelector("#categoryTabs"),
  lessonGrid: document.querySelector("#lessonGrid"),
  learningPlanCard: document.querySelector("#learningPlanCard"),
  learningPlanText: document.querySelector("#learningPlanText"),
  contentStatusText: document.querySelector("#contentStatusText"),
  planSelect: document.querySelector("#planSelect"),
  planPickerWrap: document.querySelector("#planPickerWrap"),
  updateContentBtn: document.querySelector("#updateContentBtn"),
  editPlanBtn: document.querySelector("#editPlanBtn"),
  startTrainingBtn: document.querySelector("#startTrainingBtn"),
  openSettingsBtn: document.querySelector("#openSettingsBtn"),
  backFromSettingsBtn: document.querySelector("#backFromSettingsBtn"),
  backHomeBtn: document.querySelector("#backHomeBtn"),
  apiProviderSelect: document.querySelector("#apiProviderSelect"),
  apiKeyInput: document.querySelector("#apiKeyInput"),
  apiModelInput: document.querySelector("#apiModelInput"),
  apiBaseUrlInput: document.querySelector("#apiBaseUrlInput"),
  saveApiSettingsBtn: document.querySelector("#saveApiSettingsBtn"),
  clearApiSettingsBtn: document.querySelector("#clearApiSettingsBtn"),
  apiStatusPill: document.querySelector("#apiStatusPill"),
  scenarioSelect: document.querySelector("#scenarioSelect"),
  exampleSentence: document.querySelector("#exampleSentence"),
  exampleTranslation: document.querySelector("#exampleTranslation"),
  practiceSentence: document.querySelector("#practiceSentence"),
  practicePrompt: document.querySelector("#practicePrompt"),
  playExampleBtn: document.querySelector("#playExampleBtn"),
  playTargetBtn: document.querySelector("#playTargetBtn"),
  recordToggleBtn: document.querySelector("#recordToggleBtn"),
  statusLine: document.querySelector("#statusLine"),
  recordingPlayback: document.querySelector("#recordingPlayback"),
  transcriptText: document.querySelector("#transcriptText"),
  translationText: document.querySelector("#translationText"),
  feedbackList: document.querySelector("#feedbackList"),
  prevBtn: document.querySelector("#prevBtn"),
  nextBtn: document.querySelector("#nextBtn"),
  progressFill: document.querySelector("#progressFill"),
  lessonLevel: document.querySelector("#lessonLevel"),
  trainingTitle: document.querySelector("#trainingTitle"),
  lessonEmoji: document.querySelector("#lessonEmoji"),
  lessonGoal: document.querySelector("#lessonGoal"),
  phraseChips: document.querySelector("#phraseChips"),
};

function currentLesson() {
  return lessons[state.lessonIndex];
}

function currentDrill() {
  return currentLesson().drills[state.drillIndex];
}

function targetSentence() {
  const lesson = currentLesson();
  const drill = currentDrill();

  if (lesson.pattern.startsWith("Does")) {
    return `${lesson.prefix} ${drill.answer} ${lesson.ending}`;
  }

  return `${lesson.prefix} ${drill.answer}${lesson.ending}`;
}

function blankSentence() {
  const lesson = currentLesson();
  return lesson.pattern.replace("___", '<span class="blank">________</span>');
}

function normalize(text) {
  return text
    .toLowerCase()
    .replace(/[’']/g, "")
    .replace(/[^a-z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function words(text) {
  return normalize(text).split(" ").filter(Boolean);
}

function labelFromMap(map, value, fallback = value) {
  return map[value] || fallback || "";
}

function displayCategory(category) {
  return labelFromMap(categoryLabels, category);
}

function displayLevel(level) {
  return labelFromMap(levelLabels, level);
}

function displayPlanTitle(plan) {
  return planTitleLabels[plan?.slug] || plan?.title || "";
}

function displayLessonTitle(lesson) {
  return lessonTitleLabels[lesson?.title] || lesson?.title || lesson?.scenario || "";
}

function displayLessonGoal(lesson) {
  return goalLabels[lesson?.goal] || lesson?.goal || "";
}

function speak(text) {
  if (!("speechSynthesis" in window)) {
    setStatus("当前浏览器不支持朗读，可以直接看文本跟读。", true);
    return;
  }

  window.speechSynthesis.cancel();
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = "en-US";
  utterance.rate = 0.88;
  window.speechSynthesis.speak(utterance);
}

function setStatus(message, isWarning = false) {
  elements.statusLine.textContent = message;
  elements.statusLine.classList.toggle("warning", isWarning);
}

function readLearningProfile() {
  try {
    return JSON.parse(localStorage.getItem(onboardingStorageKey)) || null;
  } catch {
    return null;
  }
}

function writeLearningProfile(profile) {
  localStorage.setItem(onboardingStorageKey, JSON.stringify(profile));
}

function readLibraryCache() {
  try {
    return JSON.parse(localStorage.getItem(libraryStorageKey)) || null;
  } catch {
    return null;
  }
}

function writeLibraryCache(library) {
  localStorage.setItem(libraryStorageKey, JSON.stringify(library));
}

function libraryEndpoint(profile) {
  const params = new URLSearchParams({
    level: profile?.level || "",
    reason: profile?.reason || "",
    dailyGoal: profile?.dailyGoal || "",
    focus: profile?.focus || "",
  });
  return `api/library.php?${params.toString()}`;
}

function applyLibrary(library, preferredSlug = "") {
  if (!library?.plans?.length) {
    lessons = [...fallbackLessons];
    state.library = null;
    state.selectedPlanSlug = "";
    return;
  }

  const selectedPlan =
    library.plans.find((plan) => plan.slug === preferredSlug) ||
    library.plans.find((plan) => plan.slug === library.defaultPlan) ||
    library.plans[0];

  state.library = library;
  state.selectedPlanSlug = selectedPlan.slug;
  lessons = selectedPlan.lessons?.length ? selectedPlan.lessons : [...fallbackLessons];
  state.lessonIndex = 0;
  state.drillIndex = 0;
  renderScenarioOptions();
  renderCategoryTabs();
  renderLessonCards();
  renderPlanPicker();
}

function loadCachedLibrary() {
  const cached = readLibraryCache();
  if (cached?.plans?.length) {
    applyLibrary(cached, cached.selectedPlanSlug || cached.defaultPlan);
  }
}

async function downloadLibrary(profile = readLearningProfile(), options = {}) {
  if (!profile) {
    elements.contentStatusText.textContent = "请先完成开始问卷，再下载匹配内容。";
    return;
  }

  elements.contentStatusText.textContent = "正在更新线上内容...";
  try {
    const response = await fetch(libraryEndpoint(profile), { cache: "no-store" });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const library = await response.json();
    if (!library.ok || !library.plans?.length) {
      throw new Error(library.message || "没有匹配内容");
    }
    library.cachedAt = new Date().toISOString();
    writeLibraryCache(library);
    applyLibrary(library, options.preferredSlug || library.defaultPlan);
    updateLearningPlanSummary();
  } catch (error) {
    loadCachedLibrary();
    const fallback = readLibraryCache() ? "正在使用已缓存的线上内容。" : "线上内容暂时不可用，正在使用内置入门内容。";
    elements.contentStatusText.textContent = fallback;
  }
}

function getOptionLabel(stepId, value) {
  const step = onboardingSteps.find((item) => item.id === stepId);
  return step?.options.find((option) => option.value === value)?.title || "";
}

function recommendedCategoryFromProfile(profile) {
  if (profile?.reason === "travel") {
    return "Travel";
  }

  if (profile?.reason === "career" || profile?.reason === "communication") {
    return "Daily Life";
  }

  if (profile?.level === "level-1" || profile?.level === "level-2") {
    return "Beginner";
  }

  return "All";
}

function updateLearningPlanSummary() {
  const profile = readLearningProfile();

  if (!profile) {
    elements.learningPlanText.textContent = "完成开始问卷后，这里会显示你的学习计划。";
    return;
  }

  const level = getOptionLabel("level", profile.level).replace("等级 ", "L");
  const reason = getOptionLabel("reason", profile.reason);
  const goal = getOptionLabel("dailyGoal", profile.dailyGoal).split(" / ")[0];
  const focus = getOptionLabel("focus", profile.focus);
  elements.learningPlanText.textContent = `${level} · ${reason} · 每天 ${goal} · ${focus}`;
  if (state.library?.plans?.length) {
    const selected = state.library.plans.find((plan) => plan.slug === state.selectedPlanSlug) || state.library.plans[0];
    elements.contentStatusText.textContent = `线上内容包：${displayPlanTitle(selected)} · v${state.library.version}`;
  } else if (readLibraryCache()?.plans?.length) {
    elements.contentStatusText.textContent = "已缓存线上内容，可离线继续使用。";
  } else {
    elements.contentStatusText.textContent = "正在使用内置入门内容。";
  }
}

function renderOnboarding() {
  const step = onboardingSteps[state.onboardingStep];
  const selectedValue = state.onboardingAnswers[step.id];
  const progress = ((state.onboardingStep + 1) / onboardingSteps.length) * 100;

  elements.onboardingStepLabel.textContent = `第 ${state.onboardingStep + 1} 步，共 ${onboardingSteps.length} 步`;
  elements.onboardingQuestion.textContent = step.question;
  elements.onboardingHint.textContent = step.hint;
  elements.onboardingProgressFill.style.width = `${progress}%`;
  elements.onboardingBackBtn.disabled = state.onboardingStep === 0;
  elements.onboardingContinueBtn.disabled = !selectedValue;
  elements.onboardingOptions.innerHTML = "";

  step.options.forEach((option) => {
    const button = document.createElement("button");
    button.className = "onboarding-option";
    button.classList.toggle("is-selected", option.value === selectedValue);
    button.type = "button";
    button.innerHTML = `
      <span class="option-mark">${option.mark}</span>
      <span class="option-content">
        <strong>${option.title}</strong>
        <span>${option.detail}</span>
      </span>
    `;
    button.addEventListener("click", () => {
      state.onboardingAnswers[step.id] = option.value;
      renderOnboarding();
    });
    elements.onboardingOptions.appendChild(button);
  });
}

function showOnboarding(reset = false) {
  state.view = "onboarding";
  if (reset) {
    state.onboardingStep = 0;
    state.onboardingAnswers = readLearningProfile() || {};
  }

  renderOnboarding();
  elements.onboardingView.classList.remove("is-hidden");
  elements.homeView.classList.add("is-hidden");
  elements.settingsView.classList.add("is-hidden");
  elements.trainingView.classList.add("is-hidden");
}

function completeOnboarding() {
  const profile = {
    ...state.onboardingAnswers,
    completedAt: new Date().toISOString(),
  };

  writeLearningProfile(profile);
  state.activeCategory = recommendedCategoryFromProfile(profile);
  renderCategoryTabs();
  renderLessonCards();
  updateLearningPlanSummary();
  showHome();
  downloadLibrary(profile);
}

function nextOnboardingStep() {
  if (state.onboardingStep < onboardingSteps.length - 1) {
    state.onboardingStep += 1;
    renderOnboarding();
    return;
  }

  completeOnboarding();
}

function previousOnboardingStep() {
  state.onboardingStep = Math.max(0, state.onboardingStep - 1);
  renderOnboarding();
}

function showHome() {
  state.view = "home";
  if (state.isRecording) {
    stopRecording();
  } else {
    resetRecordingState();
  }

  elements.onboardingView.classList.add("is-hidden");
  elements.homeView.classList.remove("is-hidden");
  elements.settingsView.classList.add("is-hidden");
  elements.trainingView.classList.add("is-hidden");
  updateLearningPlanSummary();
}

function showSettings() {
  state.view = "settings";
  if (state.isRecording) {
    stopRecording();
  }

  loadApiSettingsIntoForm();
  elements.onboardingView.classList.add("is-hidden");
  elements.homeView.classList.add("is-hidden");
  elements.settingsView.classList.remove("is-hidden");
  elements.trainingView.classList.add("is-hidden");
}

function showTraining(lessonIndex = state.lessonIndex) {
  state.view = "training";
  state.lessonIndex = lessonIndex;
  state.drillIndex = 0;
  renderLesson();
  elements.onboardingView.classList.add("is-hidden");
  elements.homeView.classList.add("is-hidden");
  elements.settingsView.classList.add("is-hidden");
  elements.trainingView.classList.remove("is-hidden");
}

function getProvider(providerId) {
  return apiProviders.find((provider) => provider.id === providerId) || apiProviders[0];
}

function readApiSettings() {
  try {
    return JSON.parse(localStorage.getItem(apiStorageKey)) || null;
  } catch {
    return null;
  }
}

function writeApiSettings(settings) {
  localStorage.setItem(apiStorageKey, JSON.stringify(settings));
}

function updateApiStatus() {
  const settings = readApiSettings();
  const isBound = Boolean(settings?.apiKey);
  elements.apiStatusPill.textContent = isBound ? `已绑定：${getProvider(settings.providerId).name}` : "未绑定";
  elements.apiStatusPill.classList.toggle("is-bound", isBound);
  elements.openSettingsBtn.textContent = isBound ? "✓" : "设置";
}

function renderApiProviderOptions() {
  elements.apiProviderSelect.innerHTML = "";

  apiProviders.forEach((provider) => {
    const option = document.createElement("option");
    option.value = provider.id;
    option.textContent = provider.name;
    elements.apiProviderSelect.appendChild(option);
  });
}

function loadApiSettingsIntoForm() {
  const settings = readApiSettings();
  const provider = getProvider(settings?.providerId);

  elements.apiProviderSelect.value = provider.id;
  elements.apiKeyInput.value = settings?.apiKey || "";
  elements.apiModelInput.value = settings?.model || provider.model;
  elements.apiBaseUrlInput.value = settings?.baseUrl || provider.baseUrl;
  updateApiStatus();
}

function applyProviderPreset() {
  const provider = getProvider(elements.apiProviderSelect.value);
  elements.apiModelInput.value = provider.model;
  elements.apiBaseUrlInput.value = provider.baseUrl;
}

function saveApiSettings() {
  const provider = getProvider(elements.apiProviderSelect.value);
  const settings = {
    providerId: provider.id,
    apiKey: elements.apiKeyInput.value.trim(),
    model: elements.apiModelInput.value.trim(),
    baseUrl: elements.apiBaseUrlInput.value.trim(),
    savedAt: new Date().toISOString(),
  };

  writeApiSettings(settings);
  updateApiStatus();
}

function clearApiSettings() {
  localStorage.removeItem(apiStorageKey);
  loadApiSettingsIntoForm();
}

function renderCategoryTabs() {
  elements.categoryTabs.innerHTML = "";

  categories.forEach((category) => {
    const button = document.createElement("button");
    button.className = "category-chip";
    button.classList.toggle("is-active", category === state.activeCategory);
    button.type = "button";
    button.textContent = displayCategory(category);
    button.addEventListener("click", () => {
      state.activeCategory = category;
      renderCategoryTabs();
      renderLessonCards();
    });
    elements.categoryTabs.appendChild(button);
  });
}

function renderPlanPicker() {
  const plans = state.library?.plans || [];
  elements.planSelect.innerHTML = "";
  elements.planPickerWrap.classList.toggle("is-hidden", plans.length <= 1);

  plans.forEach((plan) => {
    const option = document.createElement("option");
    option.value = plan.slug;
    option.textContent = `${displayPlanTitle(plan)}（${plan.dailyGoal} 分钟）`;
    elements.planSelect.appendChild(option);
  });

  if (plans.length) {
    elements.planSelect.value = state.selectedPlanSlug;
  }
}

function renderLessonCards() {
  elements.lessonGrid.innerHTML = "";

  lessons.forEach((lesson, index) => {
    if (state.activeCategory !== "All" && lesson.category !== state.activeCategory && lesson.level !== state.activeCategory) {
      return;
    }

    const firstDrill = lesson.drills[0];
    const card = document.createElement("button");
    card.className = "lesson-card";
    card.type = "button";
    card.innerHTML = `
      <div>
        <div class="lesson-icon" aria-hidden="true">${lesson.emoji}</div>
      </div>
      <span>
        <strong>${displayLessonTitle(lesson)}</strong>
        ${lesson.example}
      </span>
      <div class="lesson-meta">
        <small>${displayLevel(lesson.level)}</small>
        <small>${firstDrill.prompt}</small>
      </div>
    `;
    card.addEventListener("click", () => showTraining(index));
    elements.lessonGrid.appendChild(card);
  });
}

function renderScenarioOptions() {
  elements.scenarioSelect.innerHTML = "";

  lessons.forEach((lesson, index) => {
    const option = document.createElement("option");
    option.value = String(index);
    option.textContent = displayLessonTitle(lesson);
    elements.scenarioSelect.appendChild(option);
  });
}

function renderFeedback(items) {
  elements.feedbackList.innerHTML = "";
  items.forEach((item) => {
    const li = document.createElement("li");
    li.textContent = item;
    elements.feedbackList.appendChild(li);
  });
}

function renderPhraseChips() {
  const lesson = currentLesson();
  const drill = currentDrill();
  const chips = [lesson.prefix, drill.answer, targetSentence()];

  elements.phraseChips.innerHTML = "";
  chips.forEach((chipText) => {
    const chip = document.createElement("span");
    chip.className = "phrase-chip";
    chip.textContent = chipText;
    elements.phraseChips.appendChild(chip);
  });
}

function renderLesson() {
  const lesson = currentLesson();
  const drill = currentDrill();
  const progress = ((state.drillIndex + 1) / lesson.drills.length) * 100;

  elements.scenarioSelect.value = String(state.lessonIndex);
  elements.lessonLevel.textContent = displayLevel(lesson.level);
  elements.trainingTitle.textContent = displayLessonTitle(lesson);
  elements.lessonEmoji.textContent = lesson.emoji;
  elements.lessonGoal.textContent = displayLessonGoal(lesson);
  elements.exampleSentence.textContent = lesson.example;
  elements.exampleTranslation.textContent = lesson.exampleZh;
  elements.practiceSentence.innerHTML = blankSentence();
  elements.practicePrompt.textContent = `提示：${drill.prompt}`;
  elements.progressFill.style.width = `${progress}%`;
  elements.prevBtn.disabled = state.drillIndex === 0;
  elements.nextBtn.disabled = state.drillIndex === lesson.drills.length - 1;
  elements.transcriptText.textContent = "等待录音...";
  elements.translationText.textContent = "完成一次练习后显示。";
  renderFeedback(["按下圆形按钮，说出完整句子。系统会识别你说了什么，并和目标句对比。"]);
  renderPhraseChips();
  resetRecordingState();
}

function resetRecordingState() {
  if (state.recordingUrl) {
    URL.revokeObjectURL(state.recordingUrl);
  }

  state.isRecording = false;
  state.audioChunks = [];
  state.recordingUrl = "";
  state.transcript = "";
  elements.recordToggleBtn.classList.remove("is-recording");
  elements.recordToggleBtn.setAttribute("aria-label", "开始录音");
  elements.recordingPlayback.hidden = true;
  elements.recordingPlayback.removeAttribute("src");
  setStatus("按下圆形按钮，说出上面的完整句子。");
}

function buildRecognition() {
  if (!SpeechRecognition) {
    return null;
  }

  const recognition = new SpeechRecognition();
  recognition.lang = "en-US";
  recognition.interimResults = true;
  recognition.continuous = true;

  recognition.addEventListener("result", (event) => {
    let finalText = "";
    let interimText = "";

    for (let index = event.resultIndex; index < event.results.length; index += 1) {
      const phrase = event.results[index][0].transcript;
      if (event.results[index].isFinal) {
        finalText += phrase;
      } else {
        interimText += phrase;
      }
    }

    state.transcript = `${state.transcript} ${finalText}`.trim();
    elements.transcriptText.textContent = state.transcript || interimText || "正在听...";
  });

  recognition.addEventListener("error", () => {
    setStatus("语音识别暂时不可用，但录音仍可回放。建议使用 Chrome 或 Edge。", true);
  });

  return recognition;
}

async function startRecording() {
  if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
    setStatus("当前浏览器不支持录音。你仍然可以听例句并跟读。", true);
    return;
  }

  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    state.isRecording = true;
    state.audioChunks = [];
    state.transcript = "";
    elements.transcriptText.textContent = "正在听...";
    elements.translationText.textContent = "录完后显示。";
    renderFeedback(["正在录音。说完整句子后，再按一次圆形按钮停止。"]);

    state.mediaRecorder = new MediaRecorder(stream);
    state.mediaRecorder.addEventListener("dataavailable", (event) => {
      if (event.data.size > 0) {
        state.audioChunks.push(event.data);
      }
    });
    state.mediaRecorder.addEventListener("stop", () => {
      const blob = new Blob(state.audioChunks, { type: state.mediaRecorder.mimeType });
      state.recordingUrl = URL.createObjectURL(blob);
      elements.recordingPlayback.src = state.recordingUrl;
      elements.recordingPlayback.hidden = false;
      stream.getTracks().forEach((track) => track.stop());
      analyzeAnswer();
    });

    state.recognition = buildRecognition();
    state.recognition?.start();
    state.mediaRecorder.start();

    elements.recordToggleBtn.classList.add("is-recording");
    elements.recordToggleBtn.setAttribute("aria-label", "停止录音");
    setStatus("正在录音和识别。说完后再按一下停止。");
  } catch (error) {
    const denied = error.name === "NotAllowedError" || error.name === "SecurityError";
    setStatus(denied ? "麦克风权限被拒绝。请允许麦克风后再练习。" : "无法启动录音，请检查麦克风。", true);
  }
}

function stopRecording() {
  if (!state.isRecording) {
    return;
  }

  state.isRecording = false;
  elements.recordToggleBtn.classList.remove("is-recording");
  elements.recordToggleBtn.setAttribute("aria-label", "开始录音");
  setStatus("正在生成反馈...");

  try {
    state.recognition?.stop();
  } catch {
    // Some browsers throw if recognition already ended.
  }

  if (state.mediaRecorder?.state === "recording") {
    state.mediaRecorder.stop();
  } else {
    analyzeAnswer();
  }
}

function analyzeAnswer() {
  const expected = targetSentence();
  const expectedWords = words(expected);
  const spokenWords = words(state.transcript);
  const expectedSet = new Set(expectedWords);
  const spokenSet = new Set(spokenWords);
  const missing = expectedWords.filter((word) => !spokenSet.has(word));
  const extra = spokenWords.filter((word) => !expectedSet.has(word));
  const exact = normalize(expected) === normalize(state.transcript);
  const drill = currentDrill();

  elements.transcriptText.textContent = state.transcript || "没有识别到清晰内容。";
  elements.translationText.textContent = state.transcript
    ? drill.translation
    : "还没有足够的识别结果可以翻译。";

  if (!SpeechRecognition) {
    renderFeedback([
      "当前浏览器不支持语音转文字，所以只能回放录音自查。",
      `目标句：${expected}`,
      "想要自动识别和纠错，建议用 Chrome 或 Edge 打开 localhost 页面。",
    ]);
    setStatus("录音完成。当前浏览器没有语音识别能力。", true);
    return;
  }

  if (!state.transcript) {
    renderFeedback([
      "我没有听清你说的内容，可以靠近麦克风再试一次。",
      `你应该说：${expected}`,
      `中文意思：${drill.translation}`,
    ]);
    setStatus("没有识别到清晰语音。", true);
    return;
  }

  if (exact || missing.length === 0) {
    renderFeedback([
      "这句结构是对的。",
      `你说的是：${state.transcript}`,
      `它可以理解为：${drill.translation}`,
      "下一步可以把语速放慢一点，再练一次让连接更自然。",
    ]);
    setStatus("完成，句型匹配得不错。");
    return;
  }

  const feedback = [
    `你应该说：${expected}`,
    `我听到的是：${state.transcript}`,
  ];

  if (missing.length > 0) {
    feedback.push(`可能漏掉或说错了：${missing.join(", ")}`);
  }

  if (extra.length > 0) {
    feedback.push(`多识别到的词：${extra.join(", ")}`);
  }

  feedback.push(`这句话的意思是：${drill.translation}`);
  renderFeedback(feedback);
  setStatus("已生成反馈，可以回放录音对照。");
}

function bindEvents() {
  elements.onboardingBackBtn.addEventListener("click", previousOnboardingStep);
  elements.onboardingContinueBtn.addEventListener("click", nextOnboardingStep);
  elements.editPlanBtn.addEventListener("click", () => showOnboarding(true));
  elements.updateContentBtn.addEventListener("click", () => downloadLibrary(readLearningProfile(), { preferredSlug: state.selectedPlanSlug }));
  elements.planSelect.addEventListener("change", (event) => {
    applyLibrary(state.library, event.target.value);
    updateLearningPlanSummary();
  });
  elements.startTrainingBtn.addEventListener("click", () => showTraining(0));
  elements.openSettingsBtn.addEventListener("click", showSettings);
  elements.backFromSettingsBtn.addEventListener("click", showHome);
  elements.backHomeBtn.addEventListener("click", showHome);
  elements.apiProviderSelect.addEventListener("change", applyProviderPreset);
  elements.saveApiSettingsBtn.addEventListener("click", saveApiSettings);
  elements.clearApiSettingsBtn.addEventListener("click", clearApiSettings);

  elements.scenarioSelect.addEventListener("change", (event) => {
    state.lessonIndex = Number(event.target.value);
    state.drillIndex = 0;
    renderLesson();
  });

  elements.playExampleBtn.addEventListener("click", () => speak(currentLesson().example));
  elements.playTargetBtn.addEventListener("click", () => speak(targetSentence()));
  elements.recordToggleBtn.addEventListener("click", () => {
    if (state.isRecording) {
      stopRecording();
    } else {
      startRecording();
    }
  });

  elements.prevBtn.addEventListener("click", () => {
    state.drillIndex = Math.max(0, state.drillIndex - 1);
    renderLesson();
  });

  elements.nextBtn.addEventListener("click", () => {
    state.drillIndex = Math.min(currentLesson().drills.length - 1, state.drillIndex + 1);
    renderLesson();
  });
}

renderLessonCards();
renderCategoryTabs();
renderPlanPicker();
renderApiProviderOptions();
renderScenarioOptions();
bindEvents();
renderLesson();
updateApiStatus();
loadCachedLibrary();
if (readLearningProfile()) {
  state.activeCategory = recommendedCategoryFromProfile(readLearningProfile());
  renderCategoryTabs();
  renderLessonCards();
  showHome();
  downloadLibrary(readLearningProfile(), { preferredSlug: state.selectedPlanSlug });
} else {
  showOnboarding(true);
}

if (!SpeechRecognition) {
  setStatus("提示：这个浏览器不支持语音转文字。录音可用，但自动纠错需要 Chrome 或 Edge。", true);
}
