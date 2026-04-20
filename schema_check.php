<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    $s = $pdo->query("SHOW CREATE TABLE beh_students");
    print_r($s->fetch());
} catch (Exception $e) { echo $e->getMessage(); }
