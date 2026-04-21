<?php
/**
 * Migration: sync_students_full_update
 * Created: 2026-04-22
 *
 * Full sync (ON DUPLICATE KEY UPDATE) from att_students →
 *   assembly_students, beh_students, cb_students
 *
 * Triggered after bulk import of att_students to keep all modules in sync.
 */
return [
    'up' => function (PDO $pdo) {

        // ── Fetch master list from att_students ──
        $students = $pdo->query("
            SELECT student_id, name, classroom
            FROM att_students
            WHERE student_id IS NOT NULL AND TRIM(student_id) != ''
            GROUP BY student_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            return true;
        }

        $pdo->beginTransaction();

        // ── 1. Assembly Students ──
        $stmtAsm = $pdo->prepare("
            INSERT INTO assembly_students (student_id, name, classroom)
            VALUES (:sid, :name, :cls)
            ON DUPLICATE KEY UPDATE
                name      = VALUES(name),
                classroom = VALUES(classroom)
        ");

        // ── 2. Behavior Students ──
        $stmtBeh = $pdo->prepare("
            INSERT INTO beh_students (student_id, name, level, room, status)
            VALUES (:sid, :name, :level, :room, 'active')
            ON DUPLICATE KEY UPDATE
                name   = VALUES(name),
                level  = VALUES(level),
                room   = VALUES(room),
                status = 'active'
        ");

        // ── 3. Chromebook Students ──
        $stmtCb = $pdo->prepare("
            INSERT INTO cb_students (student_id, name, class_name)
            VALUES (:sid, :name, :cls)
            ON DUPLICATE KEY UPDATE
                name       = VALUES(name),
                class_name = VALUES(class_name)
        ");

        $count = ['assembly' => 0, 'behavior' => 0, 'chromebook' => 0];

        foreach ($students as $s) {
            $sid  = trim($s['student_id']);
            $name = trim($s['name']);
            $cls  = trim($s['classroom']);

            // Parse level/room from classroom e.g. "ม.2/3" → level="ม.2", room="3"
            $level = $cls;
            $room  = '';
            if (strpos($cls, '/') !== false) {
                [$level, $room] = array_map('trim', explode('/', $cls, 2));
            }

            $stmtAsm->execute([':sid' => $sid, ':name' => $name, ':cls' => $cls]);
            $count['assembly']++;

            $stmtBeh->execute([':sid' => $sid, ':name' => $name, ':level' => $level, ':room' => $room]);
            $count['behavior']++;

            $stmtCb->execute([':sid' => $sid, ':name' => $name, ':cls' => $cls]);
            $count['chromebook']++;
        }

        $pdo->commit();

        // Result logged to migration runner
        echo "<p>✅ Synced {$count['assembly']} → assembly_students</p>";
        echo "<p>✅ Synced {$count['behavior']} → beh_students</p>";
        echo "<p>✅ Synced {$count['chromebook']} → cb_students</p>";

        return true;
    },

    'down' => function (PDO $pdo) {
        // Intentionally empty — cannot safely revert data sync
        return true;
    },
];
