<?php
/**
 * Migration: เพิ่ม indexes สำหรับ performance
 * Created: 2026-04-05
 */
return [
    'up' => function (PDO $pdo) {
        // ── Attendance: ค้นหาตาม date + teacher_id บ่อย ──
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_att_date_teacher ON att_attendance (date, teacher_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_att_student ON att_attendance (student_id)");

        // ── WFH: ค้นหาตาม log_date บ่อย ──
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wfh_logdate ON wfh_timelogs (log_date)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wfh_user_date ON wfh_timelogs (user_id, log_date)");

        // ── Chromebook: ค้นหาตาม status บ่อย ──
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cb_status ON cb_borrow_logs (status)");

        // ── Leave: ค้นหาตาม status + teacher ──
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leave_status ON leave_requests (status_boss1)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leave_teacher ON leave_requests (teacher_id)");

        // ── Users: ค้นหาตาม role + status ──
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON llw_users (role, status)");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP INDEX IF EXISTS idx_att_date_teacher ON att_attendance");
        $pdo->exec("DROP INDEX IF EXISTS idx_att_student ON att_attendance");
        $pdo->exec("DROP INDEX IF EXISTS idx_wfh_logdate ON wfh_timelogs");
        $pdo->exec("DROP INDEX IF EXISTS idx_wfh_user_date ON wfh_timelogs");
        $pdo->exec("DROP INDEX IF EXISTS idx_cb_status ON cb_borrow_logs");
        $pdo->exec("DROP INDEX IF EXISTS idx_leave_status ON leave_requests");
        $pdo->exec("DROP INDEX IF EXISTS idx_leave_teacher ON leave_requests");
        $pdo->exec("DROP INDEX IF EXISTS idx_users_role ON llw_users");
    },
];
