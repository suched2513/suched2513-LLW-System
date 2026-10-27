<?php
/**
 * Migration: set_club_semester_2
 * Created: 2026-10-27
 *
 * 1. กำหนดภาคเรียนระบบชุมนุมเป็น ภาคเรียนที่ 2 / 2569 ใน club_settings (is_active = 1)
 * 2. คัดลอกชุมนุมที่ยังเปิดใช้งานจากภาคเรียนที่ 1 / 2569 เข้าสู่ภาคเรียนที่ 2 / 2569 (หากยังไม่มี)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ตั้งค่า club_settings ให้ภาคเรียนที่ 2/2569 เป็น Active
        $pdo->exec("UPDATE `club_settings` SET `is_active` = 0");
        $stmt = $pdo->prepare("
            INSERT INTO `club_settings` (`semester`, `year`, `reg_open`, `reg_close`, `allow_change`, `is_active`)
            VALUES (2, 2569, NULL, NULL, 1, 1)
            ON DUPLICATE KEY UPDATE `is_active` = 1, `allow_change` = 1
        ");
        $stmt->execute();

        // 2. ตรวจสอบว่าในเทอม 2/2569 มีชุมนุมแล้วหรือยัง
        $cntTerm2 = (int)$pdo->query("SELECT COUNT(*) FROM `club_groups` WHERE `semester` = 2 AND `year` = 2569")->fetchColumn();
        if ($cntTerm2 === 0) {
            // คัดลอกรายชื่อชุมนุมเดิมจากเทอม 1/2569 มาเทอม 2 อัตโนมัติ (เปิดรับสมัคร 'open', reset สมาชิกใหม่)
            $pdo->exec("
                INSERT INTO `club_groups` (`name`, `description`, `objectives`, `teacher_id`, `teacher_id_2`, `teacher_id_3`, `room`, `max_capacity`, `semester`, `year`, `status`, `pass_threshold`)
                SELECT `name`, `description`, `objectives`, `teacher_id`, `teacher_id_2`, `teacher_id_3`, `room`, `max_capacity`, 2, 2569, 'open', `pass_threshold`
                FROM `club_groups`
                WHERE `semester` = 1 AND `year` = 2569 AND `status` != 'archived'
            ");
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("UPDATE `club_settings` SET `is_active` = 0 WHERE `semester` = 2 AND `year` = 2569");
        $pdo->exec("UPDATE `club_settings` SET `is_active` = 1 WHERE `semester` = 1 AND `year` = 2569");
    },
];
