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

        // 2. พยายามเดาเพศจากคำนำหน้าชื่อ (ครอบคลุม ด.ช., ด.ญ., ฯลฯ)
        $pdo->exec("UPDATE att_students SET gender = 'ชาย' WHERE gender IS NULL AND (name LIKE 'เด็กชาย%' OR name LIKE 'นาย%' OR name LIKE 'ด.ช.%' OR name LIKE 'ดช.%')");
        $pdo->exec("UPDATE att_students SET gender = 'หญิง' WHERE gender IS NULL AND (name LIKE 'เด็กหญิง%' OR name LIKE 'นางสาว%' OR name LIKE 'นาง%' OR name LIKE 'ด.ญ.%' OR name LIKE 'ดญ.%')");
        
        return true;
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE att_students DROP COLUMN gender, DROP COLUMN academic_year, DROP COLUMN semester");
        return true;
    },
];
