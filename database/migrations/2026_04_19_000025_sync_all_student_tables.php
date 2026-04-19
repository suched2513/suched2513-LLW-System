<?php
/**
 * Migration: sync_all_student_tables
 * Created: 2026-04-19
 *
 * Synchronizes 361 students from `att_students` to other module tables:
 * - assembly_students
 * - beh_students
 * - cb_students
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Fetch Master Students from att_students
        $master_students = $pdo->query("SELECT student_id, name, classroom FROM att_students")->fetchAll();

        if (empty($master_students)) {
            // Nothing to sync
            return true;
        }

        // 2. Prepare statements for targets
        $stmt_assembly = $pdo->prepare("
            INSERT INTO assembly_students (student_id, name, classroom) 
            VALUES (:sid, :name, :cls)
            ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom)
        ");

        $stmt_behavior = $pdo->prepare("
            INSERT INTO beh_students (student_id, name, level, room, status) 
            VALUES (:sid, :name, :level, :room, 'active')
            ON DUPLICATE KEY UPDATE name=VALUES(name), level=VALUES(level), room=VALUES(room)
        ");

        $stmt_chromebook = $pdo->prepare("
            INSERT INTO cb_students (name, class_name) 
            VALUES (:name, :cls)
        ");

        // Note: The Migration Runner handled transactions automatically.
        foreach ($master_students as $s) {
            // Assembly Sync
            $stmt_assembly->execute([
                ':sid'  => $s['student_id'],
                ':name' => $s['name'],
                ':cls'  => $s['classroom']
            ]);

            // Behavior Sync (Split ม.2/1 -> level=ม.2, room=1)
            $cls = $s['classroom'];
            $level = $cls;
            $room  = '';
            if (strpos($cls, '/') !== false) {
                $parts = explode('/', $cls);
                $level = trim($parts[0]);
                $room  = trim($parts[1]);
            }
            $stmt_behavior->execute([
                ':sid'   => $s['student_id'],
                ':name'  => $s['name'],
                ':level' => $level,
                ':room'  => $room
            ]);

            // Chromebook Sync (Simple copy)
            $chk_cb = $pdo->prepare("SELECT student_id FROM cb_students WHERE name = ? AND class_name = ?");
            $chk_cb->execute([$s['name'], $s['classroom']]);
            if (!$chk_cb->fetch()) {
                $stmt_chromebook->execute([
                    ':name' => $s['name'],
                    ':cls'  => $s['classroom']
                ]);
            }
        }
        return true;
    },

    'down' => function (PDO $pdo) {
        // We don't delete data on rollback to be safe, just return true
        return true;
    },
];
