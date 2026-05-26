# VocabApp

VocabApp is a lightweight PHP/MySQL vocabulary training web app for high-school English review. It is designed for simple shared hosting such as Hostinger: upload the files, import the SQL schema, configure MySQL, and open the site in a browser.

## Features

- Browser-based multiple-choice vocabulary practice.
- Daily target of 80 correctly answered words.
- Wrong answers repeat on the same day until answered correctly.
- Spaced review scheduling with review intervals.
- Per-word mastery score and difficulty tracking.
- Admin dashboard for daily records, wrong-word statistics, and learning workflow.
- AI learning analysis for the last 7 or 30 days, with data-first progress checks.
- Vocab Base page for adding words, bulk import, searching, and progress review.
- Settings page for binding a user's own AI API key.
- Token Monitor page for tracking AI API token usage.
- All dates and daily records use Beijing time (`Asia/Shanghai`, MySQL session `+08:00`).

## Pages

- `/index.php` or `/`: student practice page.
- `/admin/`: learning records, daily statistics, and workflow dashboard.
- `/vocabbase/`: word management, bulk import, and Word Base progress.
- `/settings/`: bind DeepSeek, Kimi, Doubao, or another OpenAI-compatible API.
- `/monitor/`: review AI token usage by day and recent call.

## Requirements

- PHP 8.0+ with PDO MySQL.
- MySQL or MariaDB.
- A web host that allows uploading PHP files, such as Hostinger.

## Installation

1. Create a MySQL database.
2. Import `sql/schema.sql` into the database.
3. Copy `config/config.sample.php` to `config/config.php`.
4. Fill in your database settings in `config/config.php`.
5. Upload all files to your web directory, for example:

```text
public_html/word/
```

6. Open:

```text
https://your-domain.com/word/
```

## Configuration

Example `config/config.php`:

```php
<?php
return [
    'db_host' => 'localhost',
    'db_name' => 'your_database_name',
    'db_user' => 'your_database_user',
    'db_pass' => 'your_database_password',
    'admin_token' => 'optional-not-used-by-default'
];
```

`admin_token` is kept for compatibility with older API helpers, but the current pages do not require login by default. If you deploy this publicly, consider adding your own access protection through your host, `.htaccess`, Cloudflare Access, or PHP auth.

## Bulk Import Format

Open `/vocabbase/` and paste multiple lines:

```text
policy 政策；方针 n. This policy helps every student.
politician 政治家 n. The politician visits our school.
politics 政治 n. Politics can change people's lives.
```

Tab-separated lines are also supported:

```text
policy    政策；方针    n.    This policy helps every student.
```

Existing words are updated. New words are inserted.

Example sentences are optional. For student-friendly practice, keep examples short and simple, ideally 6-12 words.

## Database Tables

- `wm_words`: vocabulary base and mastery metadata.
- `wm_reviews`: spaced review scheduling.
- `wm_daily_answers`: one row per word per day, preserving daily completion status.
- `wm_answer_attempts`: every answer attempt, used for historical statistics.

## API Overview

- `api/study.php`: fetch daily practice words and submit answers.
- `api/words.php`: word CRUD API.
- `api/wordbase.php`: Word Base progress API.
- `api/report.php`: daily report API.
- `api/dashboard.php`: admin dashboard API.
- `api/analysis.php`: weekly/monthly learning analysis, optionally using the bound AI API.
- `api/import.php`: text import API.
- `api/prompt.php`: prompt storage API.
- `api/settings.php`: save and read masked AI API settings.
- `api/ai.php`: OpenAI-compatible AI proxy that records token usage.
- `api/usage.php`: token usage summary and recent records.

## Apps

The `apps/` folder contains installable app projects:

- `apps/android-webview/`: Native Android WordApp. It downloads word banks from `/wordapp/api`, then stores words and study progress locally with Room/SQLite.
- `apps/desktop-electron/`: Electron desktop app that opens the hosted VocabApp URL.

The Android app does not upload learning progress. Each phone keeps its own local study data.

## Security Note

This project is intentionally simple for personal/family use. By default, admin and vocab management pages are not password-protected. Protect them before deploying for a public audience.
