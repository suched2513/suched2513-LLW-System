<?php
/**
 * Migration: system_stability_and_performance
 * Created: 2026-10-24
 *
 * 1. สร้างตาราง llw_system_error_logs สำหรับบันทึก Error Telemetry ของระบบ
 * 2. จัดมาตรฐานรหัสนักเรียน (student_id) ให้เป็น 5 หลัก (LPAD 0) ทุกตาราง
 * 3. เพิ่ม Composite Indexes บนตารางที่มีการค้นหาบ่อยเพื่อเพิ่มความเร็ว
 */
return [
    'up' => function (PDO $pdo) {
        // ─── 1. ตารางบันทึกข้อผิดพลาดระบบ (System Error Telemetry) ───────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `llw_system_error_logs` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`       INT          NULL,
                `username`      VARCHAR(100) NULL,
                `role`          VARCHAR(50)  NULL,
                `endpoint`      VARCHAR(255) NULL,
                `method`        VARCHAR(10)  NULL,
                `context`       VARCHAR(100) NULL,
                `error_message` TEXT         NOT NULL,
                `file_line`     VARCHAR(255) NULL,
                `ip_address`    VARCHAR(45)  NULL,
                `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_err_created` (`created_at`),
                INDEX `idx_err_context` (`context`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 2. จัดมาตรฐานรหัสนักเรียน 5 หลัก (5-Digit Student ID) ──────────
        $studentTables = [
            'att_students'           => 'student_id',
            'att_attendance'         => 'student_id',
            'assembly_students'      => 'student_id',
            'assembly_attendance'    => 'student_id',
            'assembly_checkout'      => 'student_id',
            'beh_students'           => 'student_id',
            'beh_records'            => 'student_id',
            'cb_students'            => 'student_id',
            'pm_registrations'       => 'student_id',
            'health_records'         => 'student_id',
            'student_leave_requests' => 'student_id',
            'lms_exam_results'       => 'student_id',
            'lms_student_exercises'  => 'student_id',
            'club_members'           => 'student_id',
            'bus_students'           => 'student_id',
        ];

        foreach ($studentTables as $table => $col) {
            try {
                $exists = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
                if (!$exists) continue;

                $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array($col, $cols, true)) continue;

                // ลบช่องว่างหน้า-หลัง และเติม 0 นำหน้ากรณีเป็นตัวเลขล้วนที่สั้นกว่า 5 หลัก
                $pdo->exec("
                    UPDATE IGNORE `{$table}`
                    SET `{$col}` = LPAD(TRIM(`{$col}`), 5, '0')
                    WHERE TRIM(`{$col}`) REGEXP '^[0-9]+$'
                      AND CHAR_LENGTH(TRIM(`{$col}`)) > 0
                      AND CHAR_LENGTH(TRIM(`{$col}`)) < 5
                ");
            } catch (Exception $e) {
                error_log("[Migration] standardize {$table}.{$col}: " . $e->getMessage());
            }
        }

        // Chromebook borrow logs (เฉพาะผู้ยืมที่เป็นนักเรียน)
        try {
            if ($pdo->query("SHOW TABLES LIKE 'cb_borrow_logs'")->fetchColumn()) {
                $pdo->exec("
                    UPDATE IGNORE `cb_borrow_logs`
                    SET `borrower_id` = LPAD(TRIM(`borrower_id`), 5, '0')
                    WHERE `borrower_type` = 'Student'
                      AND TRIM(`borrower_id`) REGEXP '^[0-9]+$'
                      AND CHAR_LENGTH(TRIM(`borrower_id`)) < 5
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] cb_borrow_logs standardize: " . $e->getMessage());
        }

        // ─── 3. เพิ่ม Composite Indexes สำหรับเร่งความเร็ว Query ────────────
        $addIndexIfNotExists = function (PDO $pdo, string $table, string $indexName, string $columnsSql): void {
            try {
                if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn()) {
                    return;
                }
                $idxExists = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName))->fetch();
                if (!$idxExists) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnsSql})");
                }
            } catch (Exception $e) {
                error_log("[Migration] addIndex {$table}.{$indexName}: " . $e->getMessage());
            }
        };

        $addIndexIfNotExists($pdo, 'assembly_attendance', 'idx_asma_class_date',   '`classroom`, `date`');
        $addIndexIfNotExists($pdo, 'assembly_attendance', 'idx_asma_student_date', '`student_id`, `date`');
        $addIndexIfNotExists($pdo, 'assembly_checkout',   'idx_asmco_class_date',  '`classroom`, `date`');
        $addIndexIfNotExists($pdo, 'beh_records',         'idx_beh_student_date',  '`student_id`, `record_date`');
        $addIndexIfNotExists($pdo, 'beh_records',         'idx_beh_date_type',     '`record_date`, `type`');
        $addIndexIfNotExists($pdo, 'att_attendance',      'idx_att_subj_date',     '`subject_id`, `date`');
        $addIndexIfNotExists($pdo, 'att_attendance',      'idx_att_student_date',  '`student_id`, `date`');
        $addIndexIfNotExists($pdo, 'att_students',        'idx_atts_class_year',   '`classroom`, `academic_year`, `status`');
        $addIndexIfNotExists($pdo, 'homeroom_logs',       'idx_hr_class_date',     '`classroom`, `log_date`');
    },

    'down' => function (PDO $pdo) {
        $dropIndexIfExists = function (PDO $pdo, string $table, string $indexName): void {
            try {
                if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn()) {
                    return;
                }
                $idxExists = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName))->fetch();
                if ($idxExists) {
                    $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
                }
            } catch (Exception $e) {
                // ignore
            }
        };

        $dropIndexIfExists($pdo, 'assembly_attendance', 'idx_asma_class_date');
        $dropIndexIfExists($pdo, 'assembly_attendance', 'idx_asma_student_date');
        $dropIndexIfExists($pdo, 'assembly_checkout',   'idx_asmco_class_date');
        $dropIndexIfExists($pdo, 'beh_records',         'idx_beh_student_date');
        $dropIndexIfExists($pdo, 'beh_records',         'idx_beh_date_type');
        $dropIndexIfExists($pdo, 'att_attendance',      'idx_att_subj_date');
        $dropIndexIfExists($pdo, 'att_attendance',      'idx_att_student_date');
        $dropIndexIfExists($pdo, 'att_students',        'idx_atts_class_year');
        $dropIndexIfExists($pdo, 'homeroom_logs',       'idx_hr_class_date');

        $pdo->exec("DROP TABLE IF EXISTS `llw_system_error_logs`");
    },
];
