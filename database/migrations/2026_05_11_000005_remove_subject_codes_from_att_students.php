<?php
return [
    'up' => function (PDO $pdo) {
        // นับก่อนลบ เพื่อ log
        $count = (int) $pdo->query("
            SELECT COUNT(*) FROM att_students
            WHERE student_id NOT REGEXP '^[0-9]+$'
        ")->fetchColumn();

        if ($count === 0) {
            echo "ℹ️  ไม่พบ subject codes ใน att_students\n";
            return;
        }

        $pdo->exec("
            DELETE FROM att_students
            WHERE student_id NOT REGEXP '^[0-9]+$'
        ");

        echo "✅ ลบ subject codes ออกจาก att_students: $count แถว\n";
        echo "   (รหัสวิชาที่ถูก import ปนมาโดยผิดพลาด เช่น ค22101, พ21101 ฯลฯ)\n";
    },

    'down' => function (PDO $pdo) {
        // ไม่ revert — ข้อมูลที่ลบคือ subject codes ไม่ใช่นักเรียนจริง
        echo "ℹ️  down() ไม่คืนข้อมูล subject codes\n";
    },
];
