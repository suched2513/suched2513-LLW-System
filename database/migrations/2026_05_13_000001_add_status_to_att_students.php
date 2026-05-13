<?php
/**
 * Migration: Add status and status_note to att_students
 * Created: 2026-05-13
 */

return new class {
    public function up($pdo) {
        // 1. Add status column if not exists
        $chk = $pdo->query("SHOW COLUMNS FROM att_students LIKE 'status'")->fetch();
        if (!$chk) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN status ENUM('active', 'moved', 'resigned', 'suspended') DEFAULT 'active' AFTER classroom");
            $pdo->exec("ALTER TABLE att_students ADD COLUMN status_note VARCHAR(255) NULL AFTER status");
            $pdo->exec("CREATE INDEX idx_student_status ON att_students(status)");
        }
    }

    public function down($pdo) {
        $pdo->exec("ALTER TABLE att_students DROP COLUMN status");
        $pdo->exec("ALTER TABLE att_students DROP COLUMN status_note");
    }
};
