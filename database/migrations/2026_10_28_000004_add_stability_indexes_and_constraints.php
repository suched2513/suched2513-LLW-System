<?php
/**
 * Migration: add_stability_indexes_and_constraints
 * Created: 2026-10-28
 *
 * 1. ลบข้อมูลเช็คชื่อที่ซ้ำซ้อนในคาบ/วิชา/วันเดียวกัน (เก็บแถวล่าสุด) และสร้าง Unique Index ป้องกันการบันทึกซ้ำ
 * 2. สร้าง Composite Indexes บน att_students และ att_subjects เพื่อรองรับการขยายตัวของข้อมูลในอนาคต
 * 3. สร้าง Unique Index บน att_students(student_id) หลังจากรวมแถวซ้ำแล้ว
 */
return [
    'up' => function (PDO $pdo) {
        $addIndexSafe = function (PDO $pdo, string $table, string $indexName, string $columnsSql, bool $unique = false): void {
            try {
                $tChk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchAll();
                if (empty($tChk)) {
                    return;
                }
                $idxChk = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName))->fetchAll();
                if (empty($idxChk)) {
                    $uSql = $unique ? 'UNIQUE INDEX' : 'INDEX';
                    $pdo->exec("ALTER TABLE `{$table}` ADD {$uSql} `{$indexName}` ({$columnsSql})");
                }
            } catch (Throwable $e) {
                error_log("[Migration 000004] addIndex {$table}.{$indexName}: " . $e->getMessage());
            }
        };

        // 1. ลบแถวซ้ำใน att_attendance (ถ้ามี) ก่อนสร้าง Unique Index
        try {
            $pdo->exec("
                DELETE a1 FROM att_attendance a1
                INNER JOIN att_attendance a2
                    ON a1.date = a2.date
                   AND a1.period = a2.period
                   AND a1.subject_id = a2.subject_id
                   AND a1.student_id = a2.student_id
                   AND a1.id < a2.id
            ");
        } catch (Throwable $e) {
            error_log("[Migration 000004] dedup att_attendance: " . $e->getMessage());
        }

        // สร้าง Unique Index ป้องกันการกดเช็คชื่อซ้ำในวัน/คาบ/วิชา/นักเรียนเดียวกัน
        $addIndexSafe($pdo, 'att_attendance', 'uq_att_date_period_subj_stu', '`date`, `period`, `subject_id`, `student_id`', true);

        // 2. เพิ่ม Composite Index สำหรับการดึงรายชื่อนักเรียนที่ active ตามห้องเรียน
        $addIndexSafe($pdo, 'att_students', 'idx_atts_status_cls_sid', '`status`, `classroom`, `student_id`', false);
        $addIndexSafe($pdo, 'att_students', 'idx_atts_student_id', '`student_id`', false);

        // 3. เพิ่ม Composite Index สำหรับการดึงรายวิชาตามปีการศึกษา/ภาคเรียน/ครูผู้สอน
        try {
            $subCols = $pdo->query("SHOW COLUMNS FROM `att_subjects`")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('academic_year', $subCols, true) && in_array('semester', $subCols, true)) {
                $addIndexSafe($pdo, 'att_subjects', 'idx_sub_year_sem_teacher', '`academic_year`, `semester`, `teacher_id`', false);
            }
        } catch (Throwable $e) {
            error_log("[Migration 000004] att_subjects index: " . $e->getMessage());
        }
    },

    'down' => function (PDO $pdo) {
        $dropIndexSafe = function (PDO $pdo, string $table, string $indexName): void {
            try {
                $tChk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchAll();
                if (empty($tChk)) {
                    return;
                }
                $idxChk = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName))->fetchAll();
                if (!empty($idxChk)) {
                    $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
                }
            } catch (Throwable $e) {
                // ignore
            }
        };

        $dropIndexSafe($pdo, 'att_attendance', 'uq_att_date_period_subj_stu');
        $dropIndexSafe($pdo, 'att_students',   'idx_atts_status_cls_sid');
        $dropIndexSafe($pdo, 'att_students',   'idx_atts_student_id');
        $dropIndexSafe($pdo, 'att_subjects',   'idx_sub_year_sem_teacher');
    },
];
