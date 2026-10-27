<?php
/**
 * Migration: add_attachments_to_lms_unit_exercises
 * Created: 2026-10-27
 *
 * เพิ่มคอลัมน์ attachment_path, attachment_name, attachment_url ในตาราง lms_unit_exercises
 * เพื่อให้ครูสามารถแนบไฟล์ใบงาน (PDF, Word, รูปภาพ) หรือลิงก์ภายนอกให้นักเรียนดาวน์โหลดได้
 */
return [
    'up' => function (PDO $pdo) {
        $hasTbl = (bool)$pdo->query("SHOW TABLES LIKE 'lms_unit_exercises'")->fetchColumn();
        if (!$hasTbl) return;

        $cols = $pdo->query("SHOW COLUMNS FROM `lms_unit_exercises`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('attachment_path', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_unit_exercises` ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL AFTER `description`");
        }
        if (!in_array('attachment_name', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_unit_exercises` ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_path`");
        }
        if (!in_array('attachment_url', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_unit_exercises` ADD COLUMN `attachment_url` TEXT NULL DEFAULT NULL AFTER `attachment_name`");
        }
    },

    'down' => function (PDO $pdo) {
        $hasTbl = (bool)$pdo->query("SHOW TABLES LIKE 'lms_unit_exercises'")->fetchColumn();
        if (!$hasTbl) return;

        $cols = $pdo->query("SHOW COLUMNS FROM `lms_unit_exercises`")->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('attachment_url', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_unit_exercises` DROP COLUMN `attachment_url`");
        }
        if (in_array('attachment_name', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_unit_exercises` DROP COLUMN `attachment_name`");
        }
        if (in_array('attachment_path', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_unit_exercises` DROP COLUMN `attachment_path`");
        }
    },
];
