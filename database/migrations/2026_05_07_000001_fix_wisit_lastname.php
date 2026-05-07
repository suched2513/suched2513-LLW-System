<?php
/**
 * Migration: แก้นามสกุล นายวิศิษฎ์ จันทร์ทรงกลิ่น (llw36) ที่ซ้ำสองครั้ง
 */
return [
    'up' => function (PDO $pdo) {
        // แก้ llw_users: นามสกุลซ้ำ → เหลือแค่ครั้งเดียว
        $pdo->exec("
            UPDATE llw_users
            SET lastname = 'จันทร์ทรงกลิ่น'
            WHERE username = 'llw36'
              AND firstname = 'นายวิศิษฎ์'
        ");

        // แก้ att_teachers: ชื่อเต็มต้องถูกต้อง
        $pdo->exec("
            UPDATE att_teachers
            SET name = 'นายวิศิษฎ์ จันทร์ทรงกลิ่น'
            WHERE username = 'llw36'
        ");
    },

    'down' => function (PDO $pdo) {
        // ไม่มี rollback สำหรับ data fix นี้
    },
];
