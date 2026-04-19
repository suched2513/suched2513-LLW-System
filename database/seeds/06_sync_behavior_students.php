<?php
/**
 * Seed: Sync Students to Behavior System (Enhanced Padding)
 * คัดลอกรายชื่อนักเรียนจาก att_students ไปยัง beh_students พร้อมเติม 0 ให้ครบ 5 หลัก
 */
return [
    'run' => function (PDO $pdo) {
        // เคลียร์ข้อมูลเก่าก่อนเพื่อป้องกันความสับสน
        $pdo->exec("DELETE FROM beh_students");
        
        // ดึงข้อมูลจาก att_students
        $stmt = $pdo->query("SELECT student_id, name, classroom FROM att_students");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            echo "    No students found in att_students - skipping sync.\n";
            return;
        }

        $insert = $pdo->prepare("
            INSERT IGNORE INTO beh_students (student_id, name, level, room)
            VALUES (?, ?, ?, ?)
        ");

        $count = 0;
        foreach ($students as $s) {
            // Normalize ID to 5 digits
            $sid = trim($s['student_id']);
            if (is_numeric($sid)) {
                $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
            }
            
            $parts = explode('/', $s['classroom']);
            $level = $parts[0] ?? '';
            $room = $parts[1] ?? '';
            
            if ($insert->execute([$sid, $s['name'], $level, $room])) {
                $count++;
            }
        }
        echo "    Synced $count students to beh_students (with 5-digit padding).\n";
    }
];
