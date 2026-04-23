<?php
/**
 * force_delete.php — Emergency Standalone Cleanup
 */
$host = '127.0.0.1'; // Force use IP to avoid localhost socket issues in CLI
$user = 'root';
$pass = '';
$db   = 'llw_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "--- LLW EMERGENCY CLEANUP ---\n";
    
    // 1. กวาดล้างตารางนักเรียน
    $pdo->exec("TRUNCATE TABLE att_students");
    echo "✓ Student table cleared (TRUNCATED).\n";

    // 2. ตั้งค่า Default Year เป็น 2569
    $pdo->exec("ALTER TABLE att_students MODIFY COLUMN academic_year INT DEFAULT 2569");
    echo "✓ System default set to 2569.\n";

    echo "--- DONE ---\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
