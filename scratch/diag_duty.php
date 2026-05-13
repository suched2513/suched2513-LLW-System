<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

echo "--- Active Duty Teachers ---\n";
$teachers = $pdo->query("SELECT id, prefix, full_name, status FROM duty_teachers WHERE status='active' AND TRIM(full_name) != '' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
print_r($teachers);

echo "\n--- Duty Groups ---\n";
$groups = $pdo->query("SELECT * FROM duty_groups WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
print_r($groups);
