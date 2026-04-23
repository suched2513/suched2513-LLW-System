<?php
/**
 * cleanup_data.php — Quick Cleanup for Student Data
 */
session_start();
require_once 'config/database.php';


$pdo = getPdo();

try {
    // 1. ลบนักเรียนทดสอบที่เจาะจง
    $stmt = $pdo->prepare("DELETE FROM att_students WHERE name LIKE ? OR name LIKE ?");
    $stmt->execute(['%ชินกฤต%', '%สุกฤษฎ์%']);
    $deletedCount = $stmt->rowCount();

    // 2. แก้ปีการศึกษาที่ผิดพลาด (2569 -> 2567)
    $stmt2 = $pdo->prepare("UPDATE att_students SET academic_year = 2567 WHERE academic_year = 2569 OR academic_year IS NULL");
    $stmt2->execute();
    $updatedCount = $stmt2->rowCount();

    echo "✓ Deleted $deletedCount test records.\n";
    echo "✓ Updated $updatedCount records to year 2567.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
