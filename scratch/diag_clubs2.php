<?php
require 'd:/llw692/suched2513-LLW-System/config/database.php';
$pdo = getPdo();
$s = $pdo->query('SELECT * FROM club_settings WHERE is_active = 1')->fetch(PDO::FETCH_ASSOC);
echo "ACTIVE SETTING:\n";
print_r($s);
echo "\nCLUBS:\n";
$c = $pdo->query('SELECT id, name, semester, year, status FROM club_groups')->fetchAll(PDO::FETCH_ASSOC);
print_r($c);
