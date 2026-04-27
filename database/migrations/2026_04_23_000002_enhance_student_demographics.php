<?php
/**
 * Migration: Enhance att_students with demographics and school terms
 * Created: 2026-04-23
 */

return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM att_students")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('gender', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN gender ENUM('ชาย', 'หญิง') NULL AFTER name");
        }
        if (!in_array('academic_year', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN academic_year INT DEFAULT 2567 AFTER classroom");
        }
        if (!in_array('semester', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN semester INT DEFAULT 1 AFTER academic_year");
        }

        // เดาเพศจากคำนำหน้าชื่อ (ครอบคลุม ด.ช., ด.ญ., ฯลฯ) — เฉพาะแถวที่ยังเป็น NULL
        $pdo->exec("UPDATE att_students SET gender = 'ชาย' WHERE gender IS NULL AND (name LIKE 'เด็กชาย%' OR name LIKE 'นาย%' OR name LIKE 'ด.ช.%' OR name LIKE 'ดช.%')");
        $pdo->exec("UPDATE att_students SET gender = 'หญิง' WHERE gender IS NULL AND (name LIKE 'เด็กหญิง%' OR name LIKE 'นางสาว%' OR name LIKE 'นาง%' OR name LIKE 'ด.ญ.%' OR name LIKE 'ดญ.%')");

        return true;
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE att_students DROP COLUMN gender, DROP COLUMN academic_year, DROP COLUMN semester");
        return true;
    },
];
