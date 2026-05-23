CREATE TABLE IF NOT EXISTS wm_words (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(80) NOT NULL,
    part_of_speech VARCHAR(40) NOT NULL DEFAULT '',
    meaning_zh TEXT NOT NULL,
    example_en TEXT NOT NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'admin',
    difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
    mastery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_mastered_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_word (word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wm_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    word_id BIGINT UNSIGNED NOT NULL,
    next_review_date DATE NOT NULL,
    interval_days INT UNSIGNED NOT NULL DEFAULT 0,
    correct_streak INT UNSIGNED NOT NULL DEFAULT 0,
    wrong_streak INT UNSIGNED NOT NULL DEFAULT 0,
    last_reviewed_at TIMESTAMP NULL DEFAULT NULL,
    is_new TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_review_word (word_id),
    KEY idx_next_review_date (next_review_date),
    CONSTRAINT fk_wm_reviews_word FOREIGN KEY (word_id) REFERENCES wm_words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wm_daily_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_date DATE NOT NULL,
    word_id BIGINT UNSIGNED NOT NULL,
    word VARCHAR(80) NOT NULL,
    meaning_zh TEXT NOT NULL,
    chosen_meaning_zh TEXT NULL,
    remembered TINYINT(1) NOT NULL,
    ever_wrong TINYINT(1) NOT NULL DEFAULT 0,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
    is_due_review TINYINT(1) NOT NULL DEFAULT 0,
    sequence_no INT UNSIGNED NOT NULL DEFAULT 0,
    answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_daily_word (study_date, word_id),
    KEY idx_study_date (study_date),
    CONSTRAINT fk_wm_daily_answers_word FOREIGN KEY (word_id) REFERENCES wm_words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wm_answer_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_date DATE NOT NULL,
    word_id BIGINT UNSIGNED NOT NULL,
    word VARCHAR(80) NOT NULL,
    meaning_zh TEXT NOT NULL,
    chosen_meaning_zh TEXT NOT NULL,
    remembered TINYINT(1) NOT NULL,
    is_due_review TINYINT(1) NOT NULL DEFAULT 0,
    sequence_no INT UNSIGNED NOT NULL DEFAULT 0,
    answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempt_date (study_date),
    KEY idx_attempt_word_date (word_id, study_date),
    CONSTRAINT fk_wm_answer_attempts_word FOREIGN KEY (word_id) REFERENCES wm_words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
