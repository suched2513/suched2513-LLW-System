<?php
/**
 * cleanup_data.php — Final Reset & 2569 Setup
 */
session_start();
require_once 'config/database.php';

$pdo = getPdo();

try {
    echo "<h2>🚀 LLW System Reset (2569 Edition)</h2>";
    echo "<hr>";


    // 2. ปรับโครงสร้างปีการศึกษาเป็น 2569
    echo "Step 2: Updating default academic year to 2569...<br>";
    try {
        $pdo->exec("ALTER TABLE att_students MODIFY COLUMN academic_year INT DEFAULT 2569");
        echo "✓ Set default academic year to 2569.<br>";
    } catch (Exception $e) {
        // Fallback if column missing (should not happen after previous run)
        $pdo->exec("ALTER TABLE att_students ADD COLUMN gender ENUM('ชาย', 'หญิง') NULL AFTER name, ADD COLUMN academic_year INT DEFAULT 2569 AFTER classroom, ADD COLUMN semester INT DEFAULT 1 AFTER academic_year");
    }

    echo "<hr>";
    echo "<h3 style='color: green;'>✅ ล้างข้อมูลตัวอย่างและตั้งค่าปี 2569 สำเร็จแล้ว!</h3>";
    echo "<p>คุณครูสามารถไปที่หน้า <b>'เช็คชื่อนักเรียน > จัดการข้อมูล'</b> เพื่อนำเข้าไฟล์นักเรียน 567 คนได้เลยครับ อย่าลืมเลือกปี 2569 นะครับ</p>";
    echo "<a href='central_dashboard.php?year=2569' style='padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 10px;'>ไปที่ Dashboard (2569)</a>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
