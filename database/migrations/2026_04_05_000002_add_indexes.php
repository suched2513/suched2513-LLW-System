<?php
/**
 * Migration: เพิ่ม indexes สำหรับ performance
 * Created: 2026-04-05
 */
return [
    'up' => function (PDO $pdo) {
        $createIndex = function($pdo, $table, $index, $columns) {
            try {
                $pdo->exec("CREATE INDEX `$index` ON `$table` ($columns)");
            } catch (Exception $e) {
                // Ignore if index already exists
            }
        };

        // ── Attendance: ค้นหาตาม date + teacher_id บ่อย ──
        $createIndex($pdo, 'att_attendance', 'idx_att_date_teacher', 'date, teacher_id');
        $createIndex($pdo, 'att_attendance', 'idx_att_student', 'student_id');

        // ── WFH: ค้นหาตาม log_date บ่อย ──
        $createIndex($pdo, 'wfh_timelogs', 'idx_wfh_logdate', 'log_date');
        $createIndex($pdo, 'wfh_timelogs', 'idx_wfh_user_date', 'user_id, log_date');

        // ── Chromebook: ค้นหาตาม status บ่อย ──
        $createIndex($pdo, 'cb_borrow_logs', 'idx_cb_status', 'status');

        // ── Leave: ค้นหาตาม status + teacher ──
        $createIndex($pdo, 'leave_requests', 'idx_leave_status', 'status_boss1');
        $createIndex($pdo, 'leave_requests', 'idx_leave_teacher', 'teacher_id');

        // ── Users: ค้นหาตาม role + status ──
        $createIndex($pdo, 'llw_users', 'idx_users_role', 'role, status');
    },

    'down' => function (PDO $pdo) {
        $dropIndex = function($pdo, $table, $index) {
            try {
                $pdo->exec("DROP INDEX `$index` ON `$table`");
            } catch (Exception $e) {
                // Ignore if index doesn't exist
            }
        };

        $dropIndex($pdo, 'att_attendance', 'idx_att_date_teacher');
        $dropIndex($pdo, 'att_attendance', 'idx_att_student');
        $dropIndex($pdo, 'wfh_timelogs', 'idx_wfh_logdate');
        $dropIndex($pdo, 'wfh_timelogs', 'idx_wfh_user_date');
        $dropIndex($pdo, 'cb_borrow_logs', 'idx_cb_status');
        $dropIndex($pdo, 'leave_requests', 'idx_leave_status');
        $dropIndex($pdo, 'leave_requests', 'idx_leave_teacher');
        $dropIndex($pdo, 'llw_users', 'idx_users_role');
    },
];
