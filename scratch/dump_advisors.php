<?php
require 'd:/llw692/suched2513-LLW-System/config/database.php';
$pdo = getPdo();
$stmt = $pdo->query('SELECT * FROM llw_class_advisors');
file_put_contents('d:/llw692/suched2513-LLW-System/scratch/advisors_dump.txt', print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true));
