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
        // เพิ่ม column att_teacher_id (ถ้ายังไม่มี)
        $pdo->exec("
            ALTER TABLE duty_teachers
            ADD COLUMN IF NOT EXISTS att_teacher_id INT NULL
                COMMENT 'FK → att_teachers.id (NULL = เพิ่มด้วยตนเอง)'
                AFTER updated_at,
            ADD UNIQUE KEY IF NOT EXISTS uk_att_teacher_id (att_teacher_id)
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("
            ALTER TABLE duty_teachers
            DROP INDEX IF EXISTS uk_att_teacher_id,
            DROP COLUMN IF EXISTS att_teacher_id
        ");
    },
];
