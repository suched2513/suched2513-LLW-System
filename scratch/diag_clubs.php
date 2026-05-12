<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

echo "--- Active Settings ---\n";
$cfg = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($cfg);

echo "\n--- Open Clubs ---\n";
$stmt = $pdo->query("SELECT id, name, semester, year, status FROM club_groups WHERE status = 'open' LIMIT 10");
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($clubs);

echo "\n--- All Club Statuses ---\n";
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM club_groups GROUP BY status");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
