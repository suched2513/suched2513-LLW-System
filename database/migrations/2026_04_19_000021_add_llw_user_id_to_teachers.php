<?php
/**
 * Migration: Add llw_user_id to att_teachers
 * เพื่อรองรับระบบ Unified Login
 */
return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่ม column llw_user_id ถ้ายังไม่มี
        try {
            $pdo->exec("ALTER TABLE att_teachers ADD COLUMN llw_user_id INT NULL AFTER password");
            $pdo->exec("CREATE INDEX idx_att_teachers_llw_user_id ON att_teachers(llw_user_id)");
        } catch (Exception $e) {
            // อาจจะมีอยู่แล้ว ข้ามไป
        }

        // 2. พยายามแมพ user_id จาก llw_users โดยใช้ username ที่ตรงกัน
        $pdo->exec("
            UPDATE att_teachers t
            JOIN llw_users lu ON lu.username = t.username
            SET t.llw_user_id = lu.user_id
            WHERE t.llw_user_id IS NULL
        ");
    },

    'down' => function (PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE att_teachers DROP COLUMN llw_user_id");
        } catch (Exception $e) { }
    }
];
