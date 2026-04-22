<?php
require_once __DIR__ . '/config/database.php';
$pdo = getPdo();
$stmt = $pdo->query("SHOW TABLES");
foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) echo "$t\n";
