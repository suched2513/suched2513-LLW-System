<?php
/**
 * Migration: Fix Teacher Names (Correcting previous mistake)
 * Date: 2026-04-19
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ล้างข้อมูลที่ผิดพลาดจาก Migration ก่อนหน้า
        $pdo->exec("DELETE FROM llw_users WHERE username LIKE 'llw%'");
        $pdo->exec("DELETE FROM att_teachers WHERE username LIKE 'llw%'");

        $correctTeachers = [
            'นายสถาน ปรางมาศ',
            'นางสาววรรณธนา วงศ์พิทักษ์',
            'นางอัมพร แพงมา',
            'นางสุภาพร ชิลภักดิ์',
            'นางรัตนา หงษ์โสภา',
            'นางบานเย็น ภารักษา',
            'นางระงับ ไชยรบ',
            'นางดาราวรรณ ดีระพัฒน์',
            'นางกัญจนรัชต์ ธาตุอุดม',
            'นายภัทธิยุทธ วงศ์ธรรม',
            'นายบุญเลิศ เขิมขันธ์',
            'นางวิมลวรรณ โสดาเพชร',
            'นางรุ่งนภา พิมพ์โคตร',
            'นายกานต์ เขิมขันธ์',
            'นางสาวเกื้อกูล พิมูลชาติ',
            'นายทศพร ดาบโคตร',
            'นางสาวศศิภา ย่านา',
            'นางสาวสุวคนธ์ ศรศิลป์',
            'นางสุกัญญา คำเขื่อนดำ',
            'นายศิลา คำเขื่อนดำ',
            'นายอธิราช ดาบโคตร',
            'นางระวีวรรณ หงษ์โสภา',
            'นายสุชาติ ปรือปรึ๋ง',
            'นายวันเฉลิม มลิพันธ์',
            'นายพิชิต เสนคำสอน',
            'นางสาวอรอุมา ขันทอง',
            'นางสาวศิริลักษณ์ สะสาง',
            'นายณัฐพงศ์ วงศ์จอม',
            'นางสาวยุวรินทร์ พิธาธนาศิริภัทร',
            'นางสาวอาทิตยา ศรีหาบุตร',
            'นางสาวสิริมาภรณ์ ทาทอง',
            'นายธนากร แก้วคำไสย์',
            'นางสาวจิตติยากร อ่อนสนิท',
            'นายภัทรพงศ์ ดาสันเทัด',
            'นางสาวรักนิตา คำขาว',
            'นายวิศิษฐ์ จันทร์ทรงกลิ่น',
            'นางสาวขวัญฤดี พงเมือง',
            'นายประหยัด บุตะเคียน',
            'นางสาวปรียานุช ศรีเพ็ชร'
        ];

        $defaultPassword = password_hash('llw123456', PASSWORD_DEFAULT);

        foreach ($correctTeachers as $index => $fullName) {
            $username = 'llw' . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            
            // กำหนด Role ตามลำดับ (ผอ. และ รองฯ)
            $role = 'att_teacher';
            if ($index === 0) $role = 'super_admin'; // ผอ.
            if ($index === 1) $role = 'wfh_admin';  // รองฯ

            // แยกชื่อ-นามสกุล
            $nameParts = explode(' ', $fullName, 2);
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
