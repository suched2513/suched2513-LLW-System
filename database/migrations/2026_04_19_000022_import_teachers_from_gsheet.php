<?php
/**
 * Migration: Import Teacher List from Google Sheets
 * Date: 2026-04-19
 */
return [
    'up' => function (PDO $pdo) {
        $teachers = [
            ['นายสถาน ปรางมาศ', 'ผู้อำนวยการ', 'super_admin'],
            ['นางสาววรรณธนา วงศ์พิทักษ์', 'รองผู้อำนวยการ', 'wfh_admin'],
            ['นางอัมพร แพงมา', 'ครู', 'att_teacher'],
            ['นายมานิตย์ เพ็ชรดี', 'ครู', 'att_teacher'],
            ['นางวิพา ยิ่งมาศ', 'ครู', 'att_teacher'],
            ['นางรจนา ปรางมาศ', 'ครู', 'att_teacher'],
            ['นางสาวนริศรา ประสานธนศก', 'ครู', 'att_teacher'],
            ['นางขวัญนภา จรุงกลิ่น', 'ครู', 'att_teacher'],
            ['นางวิลาวรรณ แพงมา', 'ครู', 'att_teacher'],
            ['นางบุณิกา บุญเสือ', 'ครู', 'att_teacher'],
            ['นายสมศักดิ์ แดนไทยสง', 'ครู', 'att_teacher'],
            ['นายรุ่งเพชร นันทเกษ', 'ครู', 'att_teacher'],
            ['นายจีรศักดิ์ สีเลา', 'ครู', 'att_teacher'],
            ['นายสุรเชษฐ์ ดอกหอม', 'ครู', 'att_teacher'],
            ['นางสาวสุชารัตน์ เทวบุตร', 'ครู', 'att_teacher'],
            ['นางสาวเสาวลักษณ์ อัมเพาบุตร', 'ครู', 'att_teacher'],
            ['นายวิวัฒน์ โพธิ์สวัสดิ์', 'ครู', 'att_teacher'],
            ['นางสาวขวัญชนก รัตนบุรี', 'ครู', 'att_teacher'],
            ['นายทินกร ทองคำ', 'ครู', 'att_teacher'],
            ['นางธรรศมนณ์ หงษ์สมดี', 'ครู', 'att_teacher'],
            ['นายพุทธิเกียรติ ยาสมุทร', 'ครู', 'att_teacher'],
            ['นายวรุตม์ แสนปวน', 'ครู', 'att_teacher'],
            ['นางจินต์จุฑา พอกเพิ่ม', 'ครู', 'att_teacher'],
            ['นายสุรสิทธิ์ เวรุวนารักษ์', 'ครู', 'att_teacher'],
            ['นายวัชรพล พินิจสกุลนาวา', 'ครู', 'att_teacher'],
            ['นายอภิเชษฐ์ กัญญาคำ', 'ครู', 'att_teacher'],
            ['นางสาวสุดาพร โกษา', 'ครู', 'att_teacher'],
            ['นางสาวกมลนิษฐ์ บัวพันธ์', 'ครู', 'att_teacher'],
            ['นางสาวรวีวรรณ ไชยโกติ', 'ครู', 'att_teacher'],
            ['นางสาวอนุชน ยิ่งคง', 'ครู', 'att_teacher'],
            ['นางนิตนา ศรีบัวลำ', 'ครูอัตราจ้าง', 'att_teacher'],
            ['นางนภสร นามสีลี', 'ครูอัตราจ้าง', 'att_teacher'],
            ['นางสาวจริยา แย้มประเสริฐ', 'ครูอัตราจ้าง', 'att_teacher'],
            ['นางสาววิภาวดี บุญรอด', 'ครูอัตราจ้าง', 'att_teacher'],
            ['นางวิไลลักษณ์ สุทาวัน', 'พนักงานธุรการ', 'wfh_staff'],
            ['นางสมร แพงมา', 'พนักงานบริการ', 'wfh_staff'],
            ['นายสมหวัง แดนไธสง', 'พนักงานบริการ', 'wfh_staff'],
            ['นายชัยบัญชา แดนไธสง', 'พนักงานบริการ', 'wfh_staff'],
            ['นายวราคิว แดนไธสง', 'พนักงานบริการ', 'wfh_staff'],
        ];

        $defaultPassword = password_hash('llw123456', PASSWORD_DEFAULT);

        foreach ($teachers as $index => $t) {
            $fullName = $t[0];
            $position = $t[1];
            $role = $t[2];
            $username = 'llw' . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            
            // แยกชื่อ-นามสกุล
            $nameParts = explode(' ', $fullName, 2);
            $firstname = $nameParts[0];
            $lastname = $nameParts[1] ?? '';

            // 1. Insert into llw_users (Central Auth)
            $checkUser = $pdo->prepare("SELECT user_id FROM llw_users WHERE firstname = ? AND lastname = ?");
            $checkUser->execute([$firstname, $lastname]);
            $existingUser = $checkUser->fetch();

            if (!$existingUser) {
                $stmt = $pdo->prepare("INSERT INTO llw_users (username, password, firstname, lastname, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$username, $defaultPassword, $firstname, $lastname, $role]);
                $userId = $pdo->lastInsertId();
            } else {
                $userId = $existingUser['user_id'];
                // Update role if needed
                $pdo->prepare("UPDATE llw_users SET role = ? WHERE user_id = ?")->execute([$role, $userId]);
            }

            // 2. Insert into att_teachers (Attendance) - Only for teaching roles
            if (in_array($role, ['super_admin', 'wfh_admin', 'att_teacher'])) {
                $checkTeacher = $pdo->prepare("SELECT id FROM att_teachers WHERE name = ?");
                $checkTeacher->execute([$fullName]);
                $existingTeacher = $checkTeacher->fetch();

                if (!$existingTeacher) {
                    $stmtT = $pdo->prepare("INSERT INTO att_teachers (name, username, password, llw_user_id) VALUES (?, ?, ?, ?)");
                    $stmtT->execute([$fullName, $username, $defaultPassword, $userId]);
                } else {
                    $pdo->prepare("UPDATE att_teachers SET llw_user_id = ?, username = ? WHERE id = ?")->execute([$userId, $username, $existingTeacher['id']]);
                }
            }
        }
    },

    'down' => function (PDO $pdo) {
        // Delete imported users (be careful not to delete manual users)
        $pdo->exec("DELETE FROM llw_users WHERE username LIKE 'llw%'");
        $pdo->exec("DELETE FROM att_teachers WHERE username LIKE 'llw%'");
    }
];
