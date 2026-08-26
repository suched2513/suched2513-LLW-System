<?php
/**
 * Migration: add_random_count_settings
 * Created: 2026-08-20
 *
 * เพิ่ม "จำนวนข้อที่จะสุ่มให้ทำ" ต่อข้อสอบแต่ละประเภท — ครูมีคลังข้อสอบ N ข้อ
 * แต่กำหนดให้ระบบสุ่มมาแค่ M ข้อต่อครั้งที่นักเรียนเข้าสอบ (M < N)
 * ค่า 0 หรือ NULL = ปิดการสุ่ม แสดงข้อสอบทั้งหมดในคลังเหมือนเดิม (ค่าเริ่มต้น)
 */
return [
    'up' => function (PDO $pdo) {
        foreach (['pre_random_count', 'post_random_count'] as $colName) {
            $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE '{$colName}'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE lms_exam_settings ADD COLUMN {$colName} INT NOT NULL DEFAULT 0");
            }
        }

        foreach (['midterm_random_count', 'final_random_count'] as $colName) {
            $col = $pdo->query("SHOW COLUMNS FROM lms_subject_settings LIKE '{$colName}'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE lms_subject_settings ADD COLUMN {$colName} INT NOT NULL DEFAULT 0");
            }
        }
    },

    'down' => function (PDO $pdo) {
        foreach (['pre_random_count', 'post_random_count'] as $colName) {
            $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE '{$colName}'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE lms_exam_settings DROP COLUMN {$colName}");
            }
        }

        foreach (['midterm_random_count', 'final_random_count'] as $colName) {
            $col = $pdo->query("SHOW COLUMNS FROM lms_subject_settings LIKE '{$colName}'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE lms_subject_settings DROP COLUMN {$colName}");
            }
        }
    },
];
