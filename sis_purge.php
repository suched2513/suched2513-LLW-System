<?php
/**
 * sis_purge.php — Safely wipe ONLY student data
 */
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') { die('Unauthorized'); }

$pdo = getPdo();

echo "<h2>🧹 SIS Student Data Purge</h2>";
echo "<hr>";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // ลบเฉพาะข้อมูลนักเรียนและที่เกี่ยวข้อง
    $pdo->exec("TRUNCATE TABLE att_students");
    $pdo->exec("TRUNCATE TABLE att_subject_students");
    $pdo->exec("TRUNCATE TABLE att_attendance");
    $pdo->exec("TRUNCATE TABLE assembly_students");
    $pdo->exec("TRUNCATE TABLE assembly_attendance");
    $pdo->exec("TRUNCATE TABLE cb_borrow_logs");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div style='color:green; font-weight:bold;'>✓ ล้างข้อมูลนักเรียนและประวัติที่เกี่ยวข้องทั้งหมดเรียบร้อยแล้ว!</div>";
    echo "<p>ตอนนี้ฐานข้อมูลนักเรียนว่างเปล่า 100% พร้อมสำหรับการนำเข้า 567 คนของคุณครูครับ</p>";
} catch (Exception $e) {
    echo "<div style='color:red;'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
}
