<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$stmt = db()->query(
    'SELECT b.code, b.title, b.description, COUNT(w.id) AS word_count
     FROM wm_word_banks b
     LEFT JOIN wm_words w ON w.bank_code = b.code
     GROUP BY b.code, b.title, b.description, b.sort_order
     ORDER BY b.sort_order ASC, b.title ASC'
);

respond(['banks' => $stmt->fetchAll()]);
