<?php
/**
 * Migration: Add 'inactive' to att_students.status ENUM
 * Needed for CSV sync mode (replace-all) in attendance_system/import_students.php
 */
return [
    'up' => function (PDO $pdo) {
        $chk = $pdo->query("SHOW COLUMNS FROM att_students LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if (!$chk) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN status ENUM('active','moved','resigned','suspended','inactive') DEFAULT 'active' AFTER classroom");
            $pdo->exec("ALTER TABLE att_students ADD COLUMN status_note VARCHAR(255) NULL AFTER status");
            $pdo->exec("CREATE INDEX idx_student_status ON att_students(status)");
        } else {
            $type = strtolower($chk['Type'] ?? '');
            if (strpos($type, "'inactive'") === false) {
                $pdo->exec("ALTER TABLE att_students MODIFY COLUMN status ENUM('active','moved','resigned','suspended','inactive') DEFAULT 'active'");
            }
            // Repair any rows that were truncated to empty string before ENUM update
            $pdo->exec("UPDATE att_students SET status = 'inactive' WHERE status = ''");
            $pdo->exec("UPDATE att_students SET status = 'active' WHERE status IS NULL");
        }
    },

    'down' => function (PDO $pdo) {
        $chk = $pdo->query("SHOW COLUMNS FROM att_students LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($chk) {
            $pdo->exec("UPDATE att_students SET status = 'moved' WHERE status = 'inactive'");
            $pdo->exec("ALTER TABLE att_students MODIFY COLUMN status ENUM('active','moved','resigned','suspended') DEFAULT 'active'");
        }
    },
];
