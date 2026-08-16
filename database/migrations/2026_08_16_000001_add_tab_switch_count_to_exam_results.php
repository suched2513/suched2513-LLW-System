<?php
/**
 * Migration: add_tab_switch_count_to_exam_results
 * Created: 2026-08-16
 */
return [
    'up' => function (PDO $pdo) {
        foreach (['lms_student_pre_exam', 'lms_student_post_exam', 'lms_student_midterm_exam', 'lms_student_final_exam'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'tab_switch_count'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN tab_switch_count INT NOT NULL DEFAULT 0 COMMENT 'จำนวนครั้งที่ออกนอกหน้าจอระหว่างสอบ'");
            }
        }
    },

    'down' => function (PDO $pdo) {
        foreach (['lms_student_pre_exam', 'lms_student_post_exam', 'lms_student_midterm_exam', 'lms_student_final_exam'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'tab_switch_count'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE `{$tbl}` DROP COLUMN tab_switch_count");
            }
        }
    },
];
