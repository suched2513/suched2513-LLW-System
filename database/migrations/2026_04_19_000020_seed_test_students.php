<?php
/**
 * Migration: Seed Test Students
 */
return [
    'up' => function (PDO $pdo) {
        $students = [
            ['10001', 'นายสมชาย สายเสมอ', '1/1', '1', '1'],
            ['10002', 'นางสาวสมหญิง ยิ่งรวย', '1/1', '1', '1'],
            ['40001', 'นายมานะ อดทน', '4/1', '4', '1'],
            ['60001', 'นางสาวชูใจ ใจดี', '6/1', '6', '1'],
        ];

        foreach ($students as $s) {
            // Insert into Attendance System
            $stmt1 = $pdo->prepare("INSERT IGNORE INTO att_students (student_id, name, classroom) VALUES (?, ?, ?)");
            $stmt1->execute([$s[0], $s[1], $s[2]]);

            // Insert into Behavior System
            $stmt2 = $pdo->prepare("INSERT IGNORE INTO beh_students (student_id, name, level, room) VALUES (?, ?, ?, ?)");
            $stmt2->execute([$s[0], $s[1], $s[3], $s[4]]);
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DELETE FROM att_students WHERE student_id IN ('10001', '10002', '40001', '60001')");
        $pdo->exec("DELETE FROM beh_students WHERE student_id IN ('10001', '10002', '40001', '60001')");
    }
];
