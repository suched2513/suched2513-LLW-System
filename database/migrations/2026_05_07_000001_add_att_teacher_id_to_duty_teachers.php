<?php
/**
 * Migration: add_att_teacher_id_to_duty_teachers
 * Created: 2026-05-07
 *
 * เพิ่ม att_teacher_id เพื่อ track ที่มาจาก att_teachers
 * แก้ปัญหา import ครูชื่อซ้ำถูก block ด้วย full_name UNIQUE
 */
return [
    'up' => function (PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE duty_teachers ADD COLUMN att_teacher_id INT NULL COMMENT 'FK → att_teachers.id (NULL = เพิ่มด้วยตนเอง)' AFTER updated_at");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE duty_teachers ADD UNIQUE KEY uk_att_teacher_id (att_teacher_id)");
        } catch (Exception $e) {}
    },

    'down' => function (PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE duty_teachers DROP INDEX uk_att_teacher_id");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE duty_teachers DROP COLUMN att_teacher_id");
        } catch (Exception $e) {}
    },
];
