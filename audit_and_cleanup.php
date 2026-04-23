<?php
require_once 'config/database.php';
$pdo = getPdo();

echo "--- DATABASE AUDIT ---\n";

// 1. ตรวจสอบจำนวนทั้งหมดในปี 2569
$total = $pdo->query("SELECT COUNT(*) FROM att_students WHERE academic_year = 2569")->fetchColumn();
echo "Total Records (2569): $total\n";

// 2. หายอด Unique
$uniqueCount = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM att_students WHERE academic_year = 2569")->fetchColumn();
echo "Unique Student IDs: $uniqueCount\n";

if ($total > $uniqueCount) {
    echo "(!) Found duplicates. Cleaning up...\n";
    
    // ลบตัวที่ซ้ำ (เก็บตัวที่มี ID น้อยที่สุดไว้)
    $sql = "DELETE s1 FROM att_students s1
            INNER JOIN att_students s2 
            WHERE s1.id > s2.id 
            AND s1.student_id = s2.student_id 
            AND s1.academic_year = s2.academic_year";
    
    $deleted = $pdo->exec($sql);
    echo "✓ Deleted $deleted duplicate records.\n";
}

// 3. ตรวจสอบข้อมูล "ขยะ" (ปีการศึกษาอื่น หรือปีการศึกษาเป็นค่าว่าง)
$others = $pdo->query("SELECT COUNT(*) FROM att_students WHERE academic_year != 2569 OR academic_year IS NULL")->fetchColumn();
if ($others > 0) {
    echo "(!) Found $others records from other years or legacy data. Deleting...\n";
    $deletedOthers = $pdo->exec("DELETE FROM att_students WHERE academic_year != 2569 OR academic_year IS NULL");
    echo "✓ Deleted $deletedOthers legacy records.\n";
}

$finalTotal = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();
echo "FINAL TOTAL IN DATABASE: $finalTotal\n";
echo "----------------------\n";
