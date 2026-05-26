CREATE TABLE IF NOT EXISTS wm_word_banks (
    code VARCHAR(40) NOT NULL PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO wm_word_banks (code, title, description, sort_order)
VALUES ('high_school', '高中词库', '面向高中英语和高考复习的本机下载词库。', 10)
ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), sort_order=VALUES(sort_order);

ALTER TABLE wm_words ADD COLUMN bank_code VARCHAR(40) NOT NULL DEFAULT 'high_school' AFTER id;
ALTER TABLE wm_words DROP INDEX uniq_word;
ALTER TABLE wm_words ADD UNIQUE KEY uniq_bank_word (bank_code, word);
ALTER TABLE wm_words ADD KEY idx_bank_code (bank_code);
ALTER TABLE wm_words ADD CONSTRAINT fk_wm_words_bank FOREIGN KEY (bank_code) REFERENCES wm_word_banks(code) ON DELETE CASCADE;
