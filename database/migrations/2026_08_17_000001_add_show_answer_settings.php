<?php
/**
 * Migration: add_show_answer_settings
 * Created: 2026-08-17
 *
 * เพิ่มตัวเลือก "แสดงเฉลยให้นักเรียนเห็นหลังสอบ" — ครูเลือกเปิด/ปิดได้ต่อหน่วย (หลังเรียน)
 * และต่อวิชา (กลางภาค/ปลายภาค) ค่าเริ่มต้นคือ "แสดง" (1) เพื่อไม่เปลี่ยนพฤติกรรมเดิม
 * ข้อสอบก่อนเรียนไม่มีตัวเลือกนี้ — แสดงเฉลยเสมอ เพราะเป็นการวัดพื้นฐานก่อนเรียน ไม่มีผลตก/ผ่าน
 */
return [
    'up' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'post_show_answer'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE lms_exam_settings ADD COLUMN post_show_answer TINYINT(1) NOT NULL DEFAULT 1");
        }

        foreach (['midterm_show_answer', 'final_show_answer'] as $colName) {
            $col = $pdo->query("SHOW COLUMNS FROM lms_subject_settings LIKE '{$colName}'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE lms_subject_settings ADD COLUMN {$colName} TINYINT(1) NOT NULL DEFAULT 1");
            }
        }
    },

    'down' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'post_show_answer'")->fetch();
        if ($col) {
            $pdo->exec("ALTER TABLE lms_exam_settings DROP COLUMN post_show_answer");
        }

        foreach (['midterm_show_answer', 'final_show_answer'] as $colName) {
            $col = $pdo->query("SHOW COLUMNS FROM lms_subject_settings LIKE '{$colName}'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE lms_subject_settings DROP COLUMN {$colName}");
            }
        }
    },
];
