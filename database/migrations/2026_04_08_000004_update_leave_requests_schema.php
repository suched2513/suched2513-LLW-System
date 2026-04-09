<?php
/**
 * Migration: อัปเดต leave_requests schema ให้ครบ
 *
 * เพิ่ม column ที่ขาดอยู่:
 *   - req_date  (DATE) — วันที่ขอออกนอก
 *   - detail    (TEXT) — รายละเอียดเพิ่มเติม
 *   - total_hr  (DECIMAL) — จำนวนชั่วโมงที่ขอ
 */
return [
    'up' => function (PDO $pdo): void {
        // เพิ่ม req_date ถ้ายังไม่มี
        try {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN req_date DATE NULL AFTER teacher_id");
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate column')) throw $e;
        }

        // เพิ่ม detail ถ้ายังไม่มี
        try {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN detail TEXT NULL AFTER reason");
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate column')) throw $e;
        }

        // เพิ่ม total_hr ถ้ายังไม่มี
        try {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN total_hr DECIMAL(4,1) NOT NULL DEFAULT 0 AFTER has_class");
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate column')) throw $e;
        }

        // backfill req_date ที่ยัง NULL ให้เป็นวันที่ created_at
        $pdo->exec("UPDATE leave_requests SET req_date = DATE(created_at) WHERE req_date IS NULL");
    },

    'down' => function (PDO $pdo): void {
        // ลบ column ที่เพิ่มไป
        try { $pdo->exec("ALTER TABLE leave_requests DROP COLUMN req_date"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE leave_requests DROP COLUMN detail"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE leave_requests DROP COLUMN total_hr"); } catch (PDOException $e) {}
    },
];
