<?php
/**
 * Migration: sync_all_student_tables
 * Created: 2026-04-19
 *
 * Robustly synchronizes students from `att_students` to other module tables.
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Fetch Unique Master Students from att_students
        $master_students = $pdo->query("
            SELECT student_id, name, classroom 
            FROM att_students 
            WHERE student_id IS NOT NULL 
              AND TRIM(student_id) != ''
            GROUP BY student_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($master_students)) {
            return true;
        }

        // 2. Prepare statements with explicit student_id to prevent default '' errors 
        $stmt_assembly = $pdo->prepare("
            INSERT IGNORE INTO assembly_students (student_id, name, classroom) 
            VALUES (:sid, :name, :cls)
        ");

        $stmt_behavior = $pdo->prepare("
            INSERT IGNORE INTO beh_students (student_id, name, level, room, status) 
            VALUES (:sid, :name, :level, :room, 'active')
        ");

        $stmt_chromebook = $pdo->prepare("
            INSERT IGNORE INTO cb_students (student_id, name, class_name) 
            VALUES (:sid, :name, :cls)
        ");

        foreach ($master_students as $s) {
            $sid = trim($s['student_id']);
            $name = $s['name'];
            $cls = $s['classroom'];

            // Assembly Sync
            $stmt_assembly->execute([
                ':sid'  => $sid,
                ':name' => $name,
                ':cls'  => $cls
            ]);

            // Behavior Sync
            $level = $cls;
            $room  = '';
            if (strpos($cls, '/') !== false) {
                $parts = explode('/', $cls);
                $level = trim($parts[0]);
                $room  = trim($parts[1]);
            }
            $stmt_behavior->execute([
                ':sid'   => $sid,
                ':name'  => $name,
                ':level' => $level,
                ':room'  => $room
            ]);

            // Chromebook Sync (Fixed: explicit student_id)
            $stmt_chromebook->execute([
                ':sid'  => $sid,
                ':name' => $name,
                ':cls'  => $cls
            ]);
        }
        return true;
    },

    'down' => function (PDO $pdo) {
        return true;
    },
];
