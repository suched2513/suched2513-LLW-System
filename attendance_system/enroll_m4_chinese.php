<?php
session_start();
require_once 'functions.php';
checkLogin();

try {
    $pdo->beginTransaction();
    
    // 1. Get subject ID for จ31201
    $stmt = $pdo->prepare("SELECT id FROM att_subjects WHERE subject_code = 'จ31201'");
    $stmt->execute();
    $subj = $stmt->fetch();
    
    if (!$subj) {
        throw new Exception("ไม่พบวิชา จ31201 ในระบบ");
    }
    
    $sid = $subj['id'];
    
    // 2. Set to elective
    $pdo->exec("UPDATE att_subjects SET is_elective = 1 WHERE id = $sid");
    
    // 3. Clear old students
    $pdo->exec("DELETE FROM att_subject_students WHERE subject_id = $sid");
    
    // 4. Enroll all active M.4 students
    $count = $pdo->exec("INSERT INTO att_subject_students (subject_id, student_id) 
                SELECT $sid, id FROM att_students 
                WHERE (classroom LIKE 'ม.4/%' OR classroom = 'ม.4') AND status = 'active'");
                
    $pdo->commit();
    
    echo "<div style='font-family: sans-serif; padding: 50px; text-align: center;'>";
    echo "<h2 style='color: #4f46e5;'>✨ ลงทะเบียนนักเรียน ม.4 สำเร็จ!</h2>";
    echo "<p>ระบบได้ดึงนักเรียน ม.4 ทุกห้องเข้าวิชาจีนให้แล้วทั้งหมด $count คนครับ</p>";
    echo "<br><a href='attendance.php?subject_id=$sid' style='text-decoration: none; background: #4f46e5; color: white; padding: 12px 25px; border-radius: 12px; font-weight: bold;'>ไปหน้าเช็คชื่อวิชานี้ทันที</a>";
    echo "</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
