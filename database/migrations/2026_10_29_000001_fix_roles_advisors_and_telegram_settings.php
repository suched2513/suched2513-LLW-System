<?php
/**
 * Migration: fix_roles_advisors_and_telegram_settings
 * Created: 2026-10-29
 *
 * 1. แก้ไขคอลัมน์ llw_users.role ให้เป็น VARCHAR(50) NOT NULL DEFAULT 'att_teacher'
 *    เพื่อป้องกันปัญหา ENUM ตัดค่า role พิเศษ (เช่น director, deputy_director, club_admin ฯลฯ) กลายเป็นค่าว่าง ''
 *    และซ่อมแซมยูเซอร์ที่ role ว่าง รวมถึงตรวจสอบสถานะของ llw20 และครูทุกคนให้พร้อมใช้งาน
 * 2. ซิงค์ข้อมูลครูที่ปรึกษาระหว่าง llw_class_advisors, assembly_classrooms และ beh_advisors
 *    พร้อมล้างคำนำหน้า 'ม.ม.' ที่ซ้ำซ้อนให้เป็น 'ม.' มาตรฐานเดียวกันทั้งหมด
 * 3. ตรวจสอบตาราง wfh_system_settings ให้มีแถวข้อมูลเริ่มต้น (setting_id = 1) เสมอ
 *    และซิงค์ค่า Telegram Token / Chat ID ของทุกระบบให้พร้อมส่งแจ้งเตือนได้ทันที
 */
return [
    'up' => function (PDO $pdo) {
        // 1. แก้ไข llw_users.role เป็น VARCHAR(50) และซ่อมแซมยูเซอร์ที่ role ถูกตัดเป็นค่าว่าง
        try {
            $pdo->exec("ALTER TABLE `llw_users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'att_teacher'");
        } catch (Throwable $e) {
            error_log("[Migration 2026_10_29_000001] ALTER llw_users.role: " . $e->getMessage());
        }

        try {
            // ถ้ายูเซอร์มี role ว่าง ให้ดึงจาก llw_user_roles ก่อน ถ้าไม่มีให้ตั้งเป็น att_teacher
            $hasUserRoles = !empty($pdo->query("SHOW TABLES LIKE 'llw_user_roles'")->fetchAll());
            if ($hasUserRoles) {
                $urCols = $pdo->query("SHOW COLUMNS FROM `llw_user_roles`")->fetchAll(PDO::FETCH_COLUMN);
                $rCol   = in_array('role', $urCols, true) ? 'role' : (in_array('role_key', $urCols, true) ? 'role_key' : '');
                if ($rCol !== '') {
                    $pdo->exec("
                        UPDATE `llw_users` u
                        LEFT JOIN `llw_user_roles` ur ON ur.user_id = u.user_id AND ur.is_primary = 1
                        SET u.role = COALESCE(NULLIF(TRIM(ur.`{$rCol}`), ''), 'att_teacher')
                        WHERE u.role IS NULL OR TRIM(u.role) = ''
                    ");
                    // ซิงค์ role หลักจาก llw_users เข้า llw_user_roles ให้ครบทุกคน
                    $pdo->exec("
                        INSERT IGNORE INTO `llw_user_roles` (`user_id`, `{$rCol}`, `is_primary`)
                        SELECT `user_id`, `role`, 1
                        FROM `llw_users`
                        WHERE `role` IS NOT NULL AND TRIM(`role`) != ''
                    ");
                } else {
                    $pdo->exec("
                        UPDATE `llw_users`
                        SET `role` = 'att_teacher'
                        WHERE `role` IS NULL OR TRIM(`role`) = ''
                    ");
                }
            } else {
                $pdo->exec("
                    UPDATE `llw_users`
                    SET `role` = 'att_teacher'
                    WHERE `role` IS NULL OR TRIM(`role`) = ''
                ");
            }

            // ตรวจสอบให้ llw20 และบัญชีครูใน att_teachers มีสถานะ active และเชื่อมโยง llw_user_id ครบถ้วน
            $pdo->exec("UPDATE `llw_users` SET `status` = 'active' WHERE `username` = 'llw20' AND (`status` IS NULL OR `status` = '')");
            $pdo->exec("
                UPDATE `att_teachers` t
                INNER JOIN `llw_users` u ON u.username = t.username
                SET t.llw_user_id = u.user_id
                WHERE t.llw_user_id IS NULL OR t.llw_user_id = 0
            ");
        } catch (Throwable $e) {
            error_log("[Migration 2026_10_29_000001] repair llw_users & att_teachers: " . $e->getMessage());
        }

        // 2. ทำความสะอาดชื่อชั้นเรียน ('ม.ม.' -> 'ม.') และซิงค์ครูที่ปรึกษาทั้ง 3 ตาราง
        try {
            $hasCentral = !empty($pdo->query("SHOW TABLES LIKE 'llw_class_advisors'")->fetchAll());
            if (!$hasCentral) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `llw_class_advisors` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `classroom` VARCHAR(20) NOT NULL COMMENT 'เช่น ม.1/1',
                        `user_id` INT NOT NULL COMMENT 'FK -> llw_users.user_id',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY `uq_room_user` (`classroom`, `user_id`),
                        INDEX `idx_classroom` (`classroom`),
                        INDEX `idx_user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // ล้าง 'ม.ม.' ใน llw_class_advisors
            $badRows = $pdo->query("SELECT id, classroom, user_id FROM llw_class_advisors WHERE classroom LIKE 'ม.ม.%' OR classroom != TRIM(classroom)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($badRows as $br) {
                $cleanRoom = preg_replace('/^ม\.+/', 'ม.', trim($br['classroom']));
                try {
                    $up = $pdo->prepare("UPDATE IGNORE llw_class_advisors SET classroom = ? WHERE id = ?");
                    $up->execute([$cleanRoom, $br['id']]);
                    // ถ้าซ้ำกับแถวที่มีอยู่แล้วให้ลบทิ้ง
                    $pdo->prepare("DELETE FROM llw_class_advisors WHERE id = ? AND classroom != ?")->execute([$br['id'], $cleanRoom]);
                } catch (Throwable $ignore) {}
            }

            // 2.1 ซิงค์จาก assembly_classrooms -> llw_class_advisors
            $hasAsmCls = !empty($pdo->query("SHOW TABLES LIKE 'assembly_classrooms'")->fetchAll());
            $caCols    = $pdo->query("SHOW COLUMNS FROM `llw_class_advisors`")->fetchAll(PDO::FETCH_COLUMN);
            $hasRoleType = in_array('role_type', $caCols, true);

            if ($hasAsmCls) {
                $acCols = $pdo->query("SHOW COLUMNS FROM `assembly_classrooms`")->fetchAll(PDO::FETCH_COLUMN);
                $acRoomCol = in_array('classroom', $acCols, true) ? 'classroom' : (in_array('classroom_name', $acCols, true) ? 'classroom_name' : '');
                if ($acRoomCol !== '' && in_array('llw_user_id', $acCols, true)) {
                    $asmRows = $pdo->query("SELECT `{$acRoomCol}` AS room_name, `llw_user_id` FROM `assembly_classrooms` WHERE `llw_user_id` IS NOT NULL AND `llw_user_id` > 0")->fetchAll(PDO::FETCH_ASSOC);
                    $insAdv = $hasRoleType
                        ? $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, 'primary')")
                        : $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id) VALUES (?, ?)");
                    foreach ($asmRows as $ar) {
                        $uid  = (int)$ar['llw_user_id'];
                        $room = preg_replace('/^ม\.+/', 'ม.', trim((string)$ar['room_name']));
                        if ($uid > 0 && $room !== '') {
                            $insAdv->execute([$room, $uid]);
                        }
                    }
                }
            }

            // 2.2 ซิงค์จาก beh_advisors -> llw_class_advisors
            $hasBehAdv = !empty($pdo->query("SHOW TABLES LIKE 'beh_advisors'")->fetchAll());
            if ($hasBehAdv) {
                $bhCols = $pdo->query("SHOW COLUMNS FROM `beh_advisors`")->fetchAll(PDO::FETCH_COLUMN);
                $bhUidCol = in_array('user_id', $bhCols, true) ? 'user_id' : (in_array('teacher_id', $bhCols, true) ? 'teacher_id' : '');
                if ($bhUidCol !== '') {
                    $behRows = $pdo->query("SELECT `level`, `room`, `{$bhUidCol}` AS uid FROM `beh_advisors` WHERE `{$bhUidCol}` IS NOT NULL AND `{$bhUidCol}` > 0")->fetchAll(PDO::FETCH_ASSOC);
                    $insAdv = $hasRoleType
                        ? $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, 'primary')")
                        : $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id) VALUES (?, ?)");
                    foreach ($behRows as $bhr) {
                        $lvl = trim((string)$bhr['level']);
                        $rm  = trim((string)$bhr['room']);
                        if ($lvl === '' || $rm === '') continue;
                        $prefix   = (strpos($lvl, 'ม.') === 0) ? $lvl : ('ม.' . $lvl);
                        $roomName = preg_replace('/^ม\.+/', 'ม.', $prefix . '/' . $rm);
                        $insAdv->execute([$roomName, (int)$bhr['uid']]);
                    }
                }
            }

            // 2.3 ซิงค์ย้อนกลับจาก llw_class_advisors -> assembly_classrooms และ beh_advisors
            $allCentral = $pdo->query("
                SELECT ca.classroom, ca.user_id, TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))) AS full_name
                FROM llw_class_advisors ca
                INNER JOIN llw_users u ON u.user_id = ca.user_id
                ORDER BY ca.id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $primaryByRoom = [];
            foreach ($allCentral as $cr) {
                $rm = trim((string)$cr['classroom']);
                if ($rm === '') continue;
                if (!isset($primaryByRoom[$rm])) {
                    $primaryByRoom[$rm] = $cr;
                }
            }

            if ($hasAsmCls) {
                $acCols = $pdo->query("SHOW COLUMNS FROM `assembly_classrooms`")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('classroom', $acCols, true) && in_array('llw_user_id', $acCols, true)) {
                    $upAsm = $pdo->prepare("
                        INSERT INTO assembly_classrooms (classroom, llw_user_id, teacher_name)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            llw_user_id  = COALESCE(assembly_classrooms.llw_user_id, VALUES(llw_user_id)),
                            teacher_name = COALESCE(assembly_classrooms.teacher_name, VALUES(teacher_name))
                    ");
                    foreach ($primaryByRoom as $rm => $cr) {
                        $upAsm->execute([$rm, (int)$cr['user_id'], $cr['full_name']]);
                    }
                }
            }

            if ($hasBehAdv) {
                $bhCols = $pdo->query("SHOW COLUMNS FROM `beh_advisors`")->fetchAll(PDO::FETCH_COLUMN);
                $bhUidCol = in_array('user_id', $bhCols, true) ? 'user_id' : (in_array('teacher_id', $bhCols, true) ? 'teacher_id' : '');
                if ($bhUidCol !== '') {
                    $upBeh = $pdo->prepare("INSERT IGNORE INTO beh_advisors (`{$bhUidCol}`, `level`, `room`) VALUES (?, ?, ?)");
                    foreach ($allCentral as $cr) {
                        $rm = trim((string)$cr['classroom']);
                        if (strpos($rm, '/') !== false) {
                            [$lvl, $subRm] = array_map('trim', explode('/', $rm, 2));
                            $upBeh->execute([(int)$cr['user_id'], $lvl, $subRm]);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("[Migration 2026_10_29_000001] advisor sync: " . $e->getMessage());
        }

        // 3. ตรวจสอบตาราง wfh_system_settings และค่า Telegram Token / Chat ID
        try {
            $hasSettings = !empty($pdo->query("SHOW TABLES LIKE 'wfh_system_settings'")->fetchAll());
            if ($hasSettings) {
                $existingCols = $pdo->query("SHOW COLUMNS FROM `wfh_system_settings`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('leave_chat_id',   $existingCols, true)) $pdo->exec("ALTER TABLE `wfh_system_settings` ADD COLUMN `leave_chat_id` VARCHAR(100) NULL AFTER `admin_chat_id`");
                if (!in_array('leave_bot_token', $existingCols, true)) $pdo->exec("ALTER TABLE `wfh_system_settings` ADD COLUMN `leave_bot_token` VARCHAR(200) NULL AFTER `leave_chat_id`");
                if (!in_array('att_chat_id',     $existingCols, true)) $pdo->exec("ALTER TABLE `wfh_system_settings` ADD COLUMN `att_chat_id` VARCHAR(100) NULL AFTER `leave_bot_token`");
                if (!in_array('att_bot_token',   $existingCols, true)) $pdo->exec("ALTER TABLE `wfh_system_settings` ADD COLUMN `att_bot_token` VARCHAR(200) NULL AFTER `att_chat_id`");
                if (!in_array('duty_chat_id',    $existingCols, true)) $pdo->exec("ALTER TABLE `wfh_system_settings` ADD COLUMN `duty_chat_id` VARCHAR(100) NULL AFTER `att_bot_token`");
                if (!in_array('duty_bot_token',  $existingCols, true)) $pdo->exec("ALTER TABLE `wfh_system_settings` ADD COLUMN `duty_bot_token` VARCHAR(200) NULL AFTER `duty_chat_id`");

                $row = $pdo->query("SELECT * FROM wfh_system_settings ORDER BY setting_id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $pdo->exec("
                        INSERT INTO `wfh_system_settings`
                            (`setting_id`, `regular_time_in`, `late_time`, `school_lat`, `school_lng`, `geofence_radius`,
                             `telegram_token`, `admin_chat_id`, `att_bot_token`, `att_chat_id`)
                        VALUES
                            (1, '07:30:00', '08:01:00', 14.77685000, 104.65120000, 300,
                             '8615155532:AAGa-t_vW6gK5pHNGqd6_jy1rXup-v2cG-A', '-5136154574',
                             '8353988340:AAHTn2-tR5wB6X6k_tF2-V5uN6H1fX9X_x0', '-5234548486')
                    ");
                } else {
                    // Trim ช่องว่างในทุกคอลัมน์ Token และ Chat ID
                    $pdo->exec("
                        UPDATE `wfh_system_settings`
                        SET `telegram_token`  = TRIM(COALESCE(`telegram_token`, '')),
                            `admin_chat_id`   = TRIM(COALESCE(`admin_chat_id`, '')),
                            `att_bot_token`   = TRIM(COALESCE(`att_bot_token`, '')),
                            `att_chat_id`     = TRIM(COALESCE(`att_chat_id`, '')),
                            `leave_bot_token` = TRIM(COALESCE(`leave_bot_token`, '')),
                            `leave_chat_id`   = TRIM(COALESCE(`leave_chat_id`, '')),
                            `duty_bot_token`  = TRIM(COALESCE(`duty_bot_token`, '')),
                            `duty_chat_id`    = TRIM(COALESCE(`duty_chat_id`, ''))
                    ");
                }

                // ซิงค์ duty_group_chat_id ระหว่าง duty_settings กับ wfh_system_settings.duty_chat_id
                $hasDutySettings = !empty($pdo->query("SHOW TABLES LIKE 'duty_settings'")->fetchAll());
                if ($hasDutySettings) {
                    $dsCols = $pdo->query("SHOW COLUMNS FROM `duty_settings`")->fetchAll(PDO::FETCH_COLUMN);
                    $kCol   = in_array('skey', $dsCols, true) ? 'skey' : (in_array('setting_key', $dsCols, true) ? 'setting_key' : '');
                    $vCol   = in_array('svalue', $dsCols, true) ? 'svalue' : (in_array('setting_value', $dsCols, true) ? 'setting_value' : '');
                    if ($kCol !== '' && $vCol !== '') {
                        $dsChat = trim((string)($pdo->query("SELECT `{$vCol}` FROM `duty_settings` WHERE `{$kCol}` IN ('duty_group_chat_id','group_chat_id','chat_id') AND `{$vCol}` != '' LIMIT 1")->fetchColumn() ?: ''));
                        $wfhRow = $pdo->query("SELECT setting_id, duty_chat_id FROM wfh_system_settings ORDER BY setting_id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                        $wfhDutyChat = trim((string)($wfhRow['duty_chat_id'] ?? ''));
                        if ($wfhDutyChat === '' && $dsChat !== '' && $wfhRow) {
                            $pdo->prepare("UPDATE wfh_system_settings SET duty_chat_id = ? WHERE setting_id = ?")->execute([$dsChat, $wfhRow['setting_id']]);
                        }
                    }
                }
            }

            // ตรวจสอบคอลัมน์ใน duty_reports
            $hasDutyReports = !empty($pdo->query("SHOW TABLES LIKE 'duty_reports'")->fetchAll());
            if ($hasDutyReports) {
                $drCols = $pdo->query("SHOW COLUMNS FROM `duty_reports`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('report_note', $drCols, true)) {
                    $pdo->exec("ALTER TABLE `duty_reports` ADD COLUMN `report_note` TEXT NULL AFTER `status`");
                }
                if (!in_array('completed_at', $drCols, true)) {
                    $pdo->exec("ALTER TABLE `duty_reports` ADD COLUMN `completed_at` DATETIME NULL AFTER `report_note`");
                }
            }
        } catch (Throwable $e) {
            error_log("[Migration 2026_10_29_000001] telegram settings sync: " . $e->getMessage());
        }
    },

    'down' => function (PDO $pdo) {
        // Non-destructive repair migration
    },
];
