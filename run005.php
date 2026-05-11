<?php
// Temporary migration runner — delete after use
if (($_GET['key'] ?? '') !== 'llw2026run005') {
    http_response_code(403); exit('Forbidden');
}

require_once __DIR__ . '/config/database.php';
$pdo = getPdo();

echo "<pre>\n";
echo "=== Migration 000005: remove_subject_codes_from_att_students ===\n\n";

$count = (int) $pdo->query("
    SELECT COUNT(*) FROM att_students
    WHERE student_id NOT REGEXP '^[0-9]+$'
")->fetchColumn();

if ($count === 0) {
    echo "ℹ️  ไม่พบ subject codes ใน att_students (อาจ run ไปแล้ว)\n";
} else {
    $pdo->exec("
        DELETE FROM att_students
        WHERE student_id NOT REGEXP '^[0-9]+$'
    ");
    echo "✅ ลบ subject codes ออกจาก att_students: $count แถว\n";
    echo "   (รหัสวิชาที่ถูก import ปนมาโดยผิดพลาด เช่น ค22101, พ21101 ฯลฯ)\n";
}

$total = (int) $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();
echo "\nนักเรียนที่เหลือใน att_students: $total คน\n";
echo "</pre>\n";
echo '<p style="color:red;font-weight:bold">⚠️ ลบไฟล์ run005.php ออกจากเซิร์ฟเวอร์หลังใช้งาน</p>';
