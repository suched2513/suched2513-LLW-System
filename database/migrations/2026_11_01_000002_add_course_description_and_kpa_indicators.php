<?php
/**
 * Migration: 2026_11_01_000002_add_course_description_and_kpa_indicators
 * Created: 2026-11-01
 *
 * เพิ่มคอลัมน์คำอธิบายรายวิชา (description) และตารางแจงน้ำหนักคะแนนตัวชี้วัด (K-P-A, กลางภาค, ปลายภาค)
 * ตามแบบฟอร์ม ปพ.5 ฉบับมาตรฐานโรงเรียนละลมวิทยา (ชีต "คำอธิบาย" และชีต "ตัวชี้วัด" แผนการวัดและประเมินผล)
 */

return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่ม description ใน pp5_subjects
        try {
            $colsSubj = $pdo->query("SHOW COLUMNS FROM `pp5_subjects`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('description', $colsSubj, true)) {
                $pdo->exec("ALTER TABLE `pp5_subjects` ADD COLUMN `description` TEXT NULL COMMENT 'คำอธิบายรายวิชาตามหลักสูตร' AFTER `hours`");
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000002] pp5_subjects: ' . $e->getMessage());
        }

        // 2. เพิ่ม description ใน pp5_courses (รองรับการปรับปรุงเฉพาะห้อง/ครูผู้สอน)
        try {
            $colsCourse = $pdo->query("SHOW COLUMNS FROM `pp5_courses`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('description', $colsCourse, true)) {
                $pdo->exec("ALTER TABLE `pp5_courses` ADD COLUMN `description` TEXT NULL COMMENT 'คำอธิบายรายวิชาฉบับปรับปรุงเฉพาะห้อง (ถ้าว่างให้ใช้ของ pp5_subjects)' AFTER `ratio_final`");
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000002] pp5_courses: ' . $e->getMessage());
        }

        // 3. เพิ่มคอลัมน์คะแนนแจงตัวชี้วัด K, P, A, กลางภาค, ปลายภาค ใน pp5_indicators
        try {
            $colsInd = $pdo->query("SHOW COLUMNS FROM `pp5_indicators`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('score_k', $colsInd, true)) {
                $pdo->exec("ALTER TABLE `pp5_indicators` ADD COLUMN `score_k` DECIMAL(5,1) NOT NULL DEFAULT 0.0 COMMENT 'คะแนนระหว่างเรียน ด้านความรู้ (K)' AFTER `sort_order`");
            }
            if (!in_array('score_p', $colsInd, true)) {
                $pdo->exec("ALTER TABLE `pp5_indicators` ADD COLUMN `score_p` DECIMAL(5,1) NOT NULL DEFAULT 0.0 COMMENT 'คะแนนระหว่างเรียน ด้านทักษะ/กระบวนการ (P)' AFTER `score_k`");
            }
            if (!in_array('score_a', $colsInd, true)) {
                $pdo->exec("ALTER TABLE `pp5_indicators` ADD COLUMN `score_a` DECIMAL(5,1) NOT NULL DEFAULT 0.0 COMMENT 'คะแนนระหว่างเรียน ด้านคุณลักษณะ/เจตคติ (A)' AFTER `score_p`");
            }
            if (!in_array('score_midterm', $colsInd, true)) {
                $pdo->exec("ALTER TABLE `pp5_indicators` ADD COLUMN `score_midterm` DECIMAL(5,1) NOT NULL DEFAULT 0.0 COMMENT 'คะแนนประเมินกลางภาค' AFTER `score_a`");
            }
            if (!in_array('score_final', $colsInd, true)) {
                $pdo->exec("ALTER TABLE `pp5_indicators` ADD COLUMN `score_final` DECIMAL(5,1) NOT NULL DEFAULT 0.0 COMMENT 'คะแนนประเมินปลายภาค' AFTER `score_midterm`");
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000002] pp5_indicators: ' . $e->getMessage());
        }
    },

    'down' => function (PDO $pdo) {
        // Rollback columns if needed
        try {
            $pdo->exec("ALTER TABLE `pp5_indicators` DROP COLUMN `score_final`, DROP COLUMN `score_midterm`, DROP COLUMN `score_a`, DROP COLUMN `score_p`, DROP COLUMN `score_k`");
            $pdo->exec("ALTER TABLE `pp5_courses` DROP COLUMN `description`");
            $pdo->exec("ALTER TABLE `pp5_subjects` DROP COLUMN `description`");
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000002 rollback] ' . $e->getMessage());
        }
    }
];
