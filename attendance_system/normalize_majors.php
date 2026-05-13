<?php
session_start();
require_once 'functions.php';
checkLogin();

if ($_SESSION['llw_role'] !== 'super_admin') {
    die("เฉพาะ Super Admin เท่านั้นที่ใช้งานหน้านี้ได้");
}

try {
    // 1. Trim leading/trailing spaces and set status to active if empty
    $pdo->exec("UPDATE att_students SET major = TRIM(major) WHERE major IS NOT NULL");
    $pdo->exec("UPDATE att_students SET status = 'active' WHERE status IS NULL OR status = ''");
    
    // 2. Normalize hyphens and slashes (Remove spaces around them)
    $stmt = $pdo->query("SELECT id, major FROM att_students WHERE major LIKE '%-%' OR major LIKE '%/%' OR major LIKE '%  %'");
    $to_fix = $stmt->fetchAll();
    
    $count = 0;
    $upd = $pdo->prepare("UPDATE att_students SET major = ? WHERE id = ?");
    foreach ($to_fix as $row) {
        $clean = $row['major'];
        // Remove spaces around hyphens
        $clean = preg_replace('/\s*-\s*/', '-', $clean);
        // Remove spaces around slashes
        $clean = preg_replace('/\s*\/\s*/', '/', $clean);
        // Collapse multiple spaces
        $clean = preg_replace('/\s+/', ' ', $clean);
        // Trim again just in case
        $clean = trim($clean);
        
        if ($clean !== $row['major']) {
            if ($upd->execute([$clean, $row['id']])) {
                $count++;
            }
        }
    }

    echo "<div style='font-family: sans-serif; padding: 50px; text-align: center;'>";
    echo "<h2 style='color: #059669;'>✅ ทำความสะอาดข้อมูลสำเร็จ!</h2>";
    echo "<p>ระบบได้จัดระเบียบการเว้นวรรคสายการเรียนให้เด็กทั้งหมด $count รายการเรียบร้อยครับ</p>";
    echo "<br><a href='admin.php' style='text-decoration: none; background: #111827; color: white; padding: 12px 25px; border-radius: 12px; font-weight: bold;'>กลับหน้า Admin</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
