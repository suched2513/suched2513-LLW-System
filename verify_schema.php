<?php
require_once 'config/database.php';
$pdo = getPdo();
$sql = "SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = 'krusuche_llw' 
        AND table_name = 'proj_requests'";

try {
    $count = $pdo->query($sql)->fetchColumn();
    echo "Table Count: " . $count;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
