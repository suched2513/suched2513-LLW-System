<?php
require_once 'config/database.php';
$pdo = getPdo();
$stmt = $pdo->prepare("SELECT * FROM beh_records WHERE student_id = ? AND record_date = ?");
$stmt->execute(['04105', '2026-04-18']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
