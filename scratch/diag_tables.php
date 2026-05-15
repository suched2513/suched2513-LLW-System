<?php
require 'd:/llw692/suched2513-LLW-System/config/database.php';
$pdo = getPdo();
$stmt = $pdo->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
