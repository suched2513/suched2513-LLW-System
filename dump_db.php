<?php
require_once 'config/database.php';
$pdo = getPdo();

$output = "--- att_teachers DUMP ---\n";
$stmt = $pdo->query("SELECT * FROM att_teachers LIMIT 50");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $output .= json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

$output .= "\n--- llw_users (Admin) DUMP ---\n";
$stmt = $pdo->query("SELECT user_id, username, role FROM llw_users WHERE role IN ('super_admin','wfh_admin')");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $output .= json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

file_put_contents('db_dump_lite.txt', $output);
echo "Dumped to db_dump_lite.txt";
?>
