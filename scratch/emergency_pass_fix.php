<?php
require_once __DIR__ . '/config/database.php';
try {
    $pdo = getPdo();
    $newPassword = password_hash('123456', PASSWORD_DEFAULT);
    
    $pdo->prepare("UPDATE llw_users SET password = ?, status = 'active' WHERE role = 'att_teacher'")->execute([$newPassword]);
    
    echo "Successfully restored all teacher passwords to: 123456";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
