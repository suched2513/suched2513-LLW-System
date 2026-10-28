<?php
/**
 * Migration: verify_llw20_and_relink_advisors_by_name
 * Created: 2026-10-29
 *
 * 1. ตรวจสอบชื่อ-สกุลของ llw01..llw39 (โดยเฉพาะ llw20 = นายสุเชษฐ์ ไพรบึง) ให้ตรงตามบัญชีรายชื่อครูจริง
 *    แบบอัปเดต in-place (ไม่ลบแถว เพื่อรักษา user_id เดิมไม่ให้ FK ขาด)
 * 2. เชื่อมโยง assembly_classrooms.llw_user_id จาก teacher_name เข้ากับ llw_users.user_id ปัจจุบัน
 *    และซิงค์เข้า llw_class_advisors เพื่อให้ครูที่ปรึกษาทุกคน (รวมถึง llw20 นายสุเชษฐ์ ไพรบึง) เห็นห้องที่ปรึกษาครบถ้วน
 */
return [
    'up' => function (PDO $pdo) {
        $exactTeachers = [
            'นายสถาน ปรางมาศ',
            'นางสาววรรณธนา วงศ์พิทักษ์',
            'นางอัมพร แพงมา',
            'นางสุภาพร ชิตภักดิ์',
            'นางรัตนา หงษ์โสภา',
            'นางบานเย็น ภูรักษา',
            'นางสาวชนกนันท์ พลภักดี',
            'นายเฉลิมชัย ศรีชัย',
            'นางพิมพ์พิลาส รัตนพันธ์',
            'นางณัฏฐนันท์ สินธุพงษ์',
            'นางวิภาพร แก้วรักษา',
            'นางสาวผ่องศรี บุญกู่',
            'นางสุนีย์ เสนคราม',
            'นางชลลัดดา มากนวล',
            'นายวรวุฒิ โพธิ์ทิพย์',
            'นางสาวผ่องพรรณ คำโสภา',
            'นายสิทธิโชค แว่นแก้ว',
            'นายจักรกริซ บุญมา',
            'นายธฤต ชำนิกุล',
            'นายสุเชษฐ์ ไพรบึง',
            'นางสาวอรนุช วันคำ',
            'นางสาวลำพู ศรีลาชัย',
            'นางสาวสุรัตน์ ศรีลาชัย',
            'นายวันเฉลิม มลิพันธ์',
            'นายพิชิต เสนคำสอน',
            'นางสาวอรอุมา ขันทอง',
            'นางสาวศิริลักษณ์ สะสาง',
            'นายณัฐพงศ์ วงศ์จอม',
            'นางสาวยุวรินทร์ พิชาธนาศิริภัทร์',
            'นางสาวอาทิตยา ศรีหาบุตร',
            'นางสาวสิริมาภรณ์ ทาทอง',
            'นายธนากร แก้วคำใสย์',
            'นางสาวจิตติยากร อ่อนสนิท',
            'นายภัทรพงศ์ ดาสันทัด',
            'นางสาวรักษิตา คำขาว',
            'นายวิศิษฎ์ จันทร์ทรงกลิ่น',
            'นางสาวขวัญฤดี พงเมือง',
            'นายประหยัด บุตะเคียน',
            'นางสาวปรียานุช ศรีเพ็ชร',
        ];

        try {
            $upUser = $pdo->prepare("UPDATE `llw_users` SET `firstname` = ?, `lastname` = ?, `status` = 'active' WHERE `username` = ?");
            $upTch  = $pdo->prepare("UPDATE `att_teachers` SET `name` = ?, `llw_user_id` = ? WHERE `username` = ?");
            $getUid = $pdo->prepare("SELECT `user_id` FROM `llw_users` WHERE `username` = ? LIMIT 1");

            foreach ($exactTeachers as $idx => $fullName) {
                $uname = 'llw' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                $parts = explode(' ', trim($fullName), 2);
                $fn    = $parts[0];
                $ln    = $parts[1] ?? '';

                $upUser->execute([$fn, $ln, $uname]);
                $getUid->execute([$uname]);
                $uid = (int)$getUid->fetchColumn();
                if ($uid > 0) {
                    $upTch->execute([$fullName, $uid, $uname]);
                }
            }
        } catch (Throwable $e) {
            error_log("[Migration 2026_10_29_000002] sync teacher names: " . $e->getMessage());
        }

        // 2. เชื่อมโยง assembly_classrooms.llw_user_id จาก teacher_name กรณี user_id เดิมไม่ตรงกับ llw_users ปัจจุบัน
        try {
            $hasAsmCls = !empty($pdo->query("SHOW TABLES LIKE 'assembly_classrooms'")->fetchAll());
            if ($hasAsmCls) {
                $acCols = $pdo->query("SHOW COLUMNS FROM `assembly_classrooms`")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('teacher_name', $acCols, true) && in_array('llw_user_id', $acCols, true)) {
                    $pdo->exec("
                        UPDATE `assembly_classrooms` ac
                        INNER JOIN `llw_users` u
                            ON TRIM(ac.teacher_name) = TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')))
                        SET ac.llw_user_id = u.user_id
                        WHERE ac.teacher_name IS NOT NULL AND TRIM(ac.teacher_name) != ''
                    ");

                    // ซิงค์เข้า llw_class_advisors
                    $caCols      = $pdo->query("SHOW COLUMNS FROM `llw_class_advisors`")->fetchAll(PDO::FETCH_COLUMN);
                    $hasRoleType = in_array('role_type', $caCols, true);
                    $insAdv      = $hasRoleType
                        ? $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, 'primary')")
                        : $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id) VALUES (?, ?)");

                    $acRoomCol = in_array('classroom', $acCols, true) ? 'classroom' : (in_array('classroom_name', $acCols, true) ? 'classroom_name' : '');
                    if ($acRoomCol !== '') {
                        $rows = $pdo->query("SELECT `{$acRoomCol}` AS rm, `llw_user_id` FROM `assembly_classrooms` WHERE `llw_user_id` IS NOT NULL AND `llw_user_id` > 0")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $r) {
                            $rm  = preg_replace('/^ม\.+/', 'ม.', trim((string)$r['rm']));
                            $uid = (int)$r['llw_user_id'];
                            if ($rm !== '' && $uid > 0) {
                                $insAdv->execute([$rm, $uid]);
                            }
                        }
                    }
                }
            }

            // ลบแถวใน llw_class_advisors ที่ชี้ไปยัง user_id ที่ไม่มีอยู่จริงใน llw_users (จากตอนที่ 000023 ลบและสร้างใหม่)
            $pdo->exec("
                DELETE ca FROM `llw_class_advisors` ca
                LEFT JOIN `llw_users` u ON u.user_id = ca.user_id
                WHERE u.user_id IS NULL
            ");
        } catch (Throwable $e) {
            error_log("[Migration 2026_10_29_000002] relink advisors: " . $e->getMessage());
        }
    },

    'down' => function (PDO $pdo) {
        // Non-destructive repair migration
    },
];
