<?php
/**
 * Migration: Fix Teacher Names (EXACT LIST FROM USER)
 * Date: 2026-04-19
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ล้างข้อมูลรหัส llw ชุดเดิมก่อน (เพื่อความสะอาด)
        $pdo->exec("DELETE FROM llw_users WHERE username LIKE 'llw%'");
        $pdo->exec("DELETE FROM att_teachers WHERE username LIKE 'llw%'");

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
            'นางสาวปรียานุช ศรีเพ็ชร'
        ];

        $defaultPassword = password_hash('llw123456', PASSWORD_DEFAULT);

        foreach ($exactTeachers as $index => $fullName) {
            $username = 'llw' . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            
            // กำหนด Role ตามลำดับ (ผอ. และ รองฯ)
            $role = 'att_teacher';
            if ($index === 0) $role = 'super_admin'; // ผอ.
            if ($index === 1) $role = 'wfh_admin';  // รองฯ

            // แยกชื่อ-นามสกุล
            $nameParts = explode(' ', trim($fullName), 2);
            $firstname = $nameParts[0];
            $lastname = $nameParts[1] ?? '';

            // 1. Insert into llw_users
            $stmt = $pdo->prepare("INSERT INTO llw_users (username, password, firstname, lastname, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $defaultPassword, $firstname, $lastname, $role]);
            $userId = $pdo->lastInsertId();

            // 2. Insert into att_teachers
            $stmtT = $pdo->prepare("INSERT INTO att_teachers (name, username, password, llw_user_id) VALUES (?, ?, ?, ?)");
            $stmtT->execute([$fullName, $username, $defaultPassword, $userId]);
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DELETE FROM llw_users WHERE username LIKE 'llw%'");
        $pdo->exec("DELETE FROM att_teachers WHERE username LIKE 'llw%'");
    }
];
