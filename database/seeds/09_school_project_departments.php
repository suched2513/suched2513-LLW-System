<?php
/**
 * Seed: school_project departments
 * ฝ่ายมาตรฐานของโรงเรียน — INSERT IGNORE เพื่อ idempotent
 */
return [
    'run' => function (PDO $pdo) {
        // ตรวจว่าตาราง departments มีอยู่ก่อน
        $exists = $pdo->query("SHOW TABLES LIKE 'departments'")->fetchColumn();
        if (!$exists) return;

        $departments = [
            ['ฝ่ายบริหารวิชาการ',   1],
            ['ฝ่ายบริหารงบประมาณ',  2],
            ['ฝ่ายบริหารงานบุคคล',  3],
            ['ฝ่ายบริหารทั่วไป',    4],
            ['กลุ่มสาระภาษาไทย',    5],
            ['กลุ่มสาระคณิตศาสตร์', 6],
            ['กลุ่มสาระวิทยาศาสตร์และเทคโนโลยี', 7],
            ['กลุ่มสาระสังคมศึกษา ศาสนา และวัฒนธรรม', 8],
            ['กลุ่มสาระสุขศึกษาและพลศึกษา', 9],
            ['กลุ่มสาระศิลปะ',       10],
            ['กลุ่มสาระการงานอาชีพ', 11],
            ['กลุ่มสาระภาษาต่างประเทศ', 12],
            ['กิจกรรมพัฒนาผู้เรียน', 13],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO `departments` (`name`, `order_no`) VALUES (?, ?)");
        foreach ($departments as [$name, $order]) {
            // ตรวจว่ามีชื่อเดียวกันอยู่แล้วหรือไม่ (เพื่อ safe re-run)
            $check = $pdo->prepare("SELECT id FROM `departments` WHERE `name` = ?");
            $check->execute([$name]);
            if (!$check->fetchColumn()) {
                $stmt->execute([$name, $order]);
            }
        }
    },
];
