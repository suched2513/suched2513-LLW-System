<?php
/**
 * Migration: add_doc_fields_to_pm_meetings
 * เพิ่ม columns สำหรับ ชล.๐๑ (บันทึกข้อความ + วาระ + บรรยากาศ)
 * ที่ขาดหายไปจากตาราง pm_meetings ที่สร้างไว้ก่อนหน้า
 * Created: 2026-05-21
 */
return [
    'up' => function (PDO $pdo) {
        // Helper: เพิ่ม column เฉพาะเมื่อยังไม่มี (ป้องกัน error ถ้ารันซ้ำ)
        $addIfMissing = function (PDO $pdo, string $table, string $column, string $definition): void {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
                if (!$stmt->fetch()) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                }
            } catch (Exception $e) {
                error_log("[Migration] addIfMissing({$table}.{$column}): " . $e->getMessage());
            }
        };

        // ── เพิ่ม columns สำหรับบันทึกข้อความ (ชล.๐๑ ส่วนบน) ──
        $addIfMissing($pdo, 'pm_meetings', 'doc_no',         'VARCHAR(100) NULL');
        $addIfMissing($pdo, 'pm_meetings', 'doc_date',       'DATE NULL');
        $addIfMissing($pdo, 'pm_meetings', 'command_no',     'VARCHAR(100) NULL');
        $addIfMissing($pdo, 'pm_meetings', 'command_date',   'DATE NULL');

        // ── เพิ่ม columns สำหรับวาระและมติการประชุม (ชล.๐๑ ส่วนกลาง) ──
        $addIfMissing($pdo, 'pm_meetings', 'agenda_1',       'TEXT NULL');
        $addIfMissing($pdo, 'pm_meetings', 'agenda_2',       'TEXT NULL');
        $addIfMissing($pdo, 'pm_meetings', 'agenda_3',       'TEXT NULL');
        $addIfMissing($pdo, 'pm_meetings', 'consensus',      'TEXT NULL');

        // ── เพิ่ม columns สำหรับบรรยากาศการประชุม (ชล.๐๑ ส่วนล่าง) ──
        $addIfMissing($pdo, 'pm_meetings', 'cooperation_rating',  'TEXT NULL');
        $addIfMissing($pdo, 'pm_meetings', 'useful_suggestions',  'TEXT NULL');
        $addIfMissing($pdo, 'pm_meetings', 'support_received',    'TEXT NULL');
        $addIfMissing($pdo, 'pm_meetings', 'other_observations',  'TEXT NULL');
    },

    'down' => function (PDO $pdo) {
        // ไม่ DROP columns ใน rollback เพื่อป้องกันการสูญหายของข้อมูล
        // หากต้องการ rollback จริง ให้ทำด้วยมือ
    },
];
