<?php
/**
 * Migration: Enhance att_students with demographics and school terms
 * Created: 2026-04-23
 */

return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่มคอลัมน์ gender, academic_year, semester
        $pdo->exec("ALTER TABLE att_students 
            ADD COLUMN gender ENUM('ชาย', 'หญิง') NULL AFTER name,
            ADD COLUMN academic_year INT DEFAULT 2567 AFTER classroom,
            ADD COLUMN semester INT DEFAULT 1 AFTER academic_year
        ");

        // 2. พยายามเดาเพศจากคำนำหน้าชื่อ (ถ้ามี)
        $pdo->exec("UPDATE att_students SET gender = 'ชาย' WHERE name LIKE 'เด็กชาย%' OR name LIKE 'นาย%'");
        $pdo->exec("UPDATE att_students SET gender = 'หญิง' WHERE name LIKE 'เด็กหญิง%' OR name LIKE 'นางสาว%'");
        
        return true;
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE att_students DROP COLUMN gender, DROP COLUMN academic_year, DROP COLUMN semester");
        return true;
    },
];
