<?php
require_once __DIR__ . '/config/database.php';
try {
    $pdo = getPdo();
    $stmt = $pdo->query("SELECT user_id, username FROM llw_users WHERE role = 'att_teacher'");
    $users = $stmt->fetchAll();
    
    $update = $pdo->prepare("UPDATE llw_users SET password = ? WHERE user_id = ?");
    $count = 0;
    foreach ($users as $u) {
        $hash = password_hash($u['username'], PASSWORD_DEFAULT);
        $update->execute([$hash, $u['user_id']]);
        $count++;
    }
    echo "Successfully restored $count teacher passwords to match their usernames.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
