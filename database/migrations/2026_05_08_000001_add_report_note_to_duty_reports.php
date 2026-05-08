<?php
/**
 * Migration: add_report_note_to_duty_reports
 * Created: 2026-05-08
 */
return [
    'up' => function (PDO $pdo) {
        // เพิ่มคอลัมน์ report_note สำหรับเก็บรายละเอียด ใคร ทำอะไร ที่ไหน อย่างไร
        try {
            $pdo->exec("ALTER TABLE duty_reports ADD COLUMN report_note TEXT NULL AFTER teacher_id");
        } catch (Exception $e) {
            // ถ้ามีอยู่แล้วให้ข้าม
        }
    },
    'down' => function (PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE duty_reports DROP COLUMN report_note");
        } catch (Exception $e) {
        }
    },
];
