SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE wm_answer_attempts;
TRUNCATE TABLE wm_daily_answers;
TRUNCATE TABLE wm_reviews;
TRUNCATE TABLE wm_words;
TRUNCATE TABLE wm_ai_usage;
TRUNCATE TABLE wm_ai_settings;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO wm_word_banks (code, title, description, sort_order)
VALUES ('high_school', '高中词库', '面向高中英语和高考复习的本机下载词库。', 10)
ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), sort_order=VALUES(sort_order);
