<?php
/**
 * room_booking/includes/db.php
 * ตัวเชื่อมต่อฐานข้อมูล PDO สำหรับระบบจองห้องประชุม โรงเรียนละลมวิทยา
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    @session_start();
}

require_once __DIR__ . '/../../config.php';

function getRoomBookingPdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = getPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        ensureRoomBookingTables($pdo);
        syncSchoolRooms($pdo);
    }
    return $pdo;
}

/**
 * สร้างตารางอัตโนมัติ (Auto-Migration) หากยังไม่มีในฐานข้อมูล
 */
function ensureRoomBookingTables(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) return;

    try {
        // 1. ตารางห้องประชุม rb_rooms
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rb_rooms` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `room_code` VARCHAR(50) NOT NULL UNIQUE,
                `room_name` VARCHAR(150) NOT NULL,
                `building` VARCHAR(100) NULL,
                `floor` VARCHAR(50) NULL,
                `capacity` INT NOT NULL DEFAULT 30,
                `facilities` TEXT NULL,
                `color_code` VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
                `image_url` VARCHAR(255) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_rb_rooms_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. ตารางการจองห้องประชุม rb_bookings
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rb_bookings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `booking_code` VARCHAR(50) NOT NULL UNIQUE,
                `room_id` INT NOT NULL,
                `booking_date` DATE NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `department` VARCHAR(150) NULL,
                `requester_name` VARCHAR(150) NOT NULL,
                `requester_phone` VARCHAR(50) NULL,
                `requester_user_id` INT NULL,
                `attendee_count` INT NOT NULL DEFAULT 10,
                `note` TEXT NULL,
                `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
                `approved_by` INT NULL,
                `approved_at` DATETIME NULL,
                `rejection_reason` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_rb_check` (`room_id`, `booking_date`, `status`, `start_time`, `end_time`),
                INDEX `idx_rb_date` (`booking_date`),
                INDEX `idx_rb_status` (`status`),
                INDEX `idx_rb_user` (`requester_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. ตารางบันทึกประวัติการเปลี่ยนแปลง rb_audit_logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rb_audit_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `username` VARCHAR(100) NULL,
                `action` VARCHAR(50) NOT NULL,
                `table_name` VARCHAR(50) NOT NULL,
                `record_id` INT NULL,
                `details` TEXT NULL,
                `ip_address` VARCHAR(50) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_rb_audit_rec` (`table_name`, `record_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 4. ตารางบันทึกการพยายามเข้าสู่ระบบ rb_login_attempts
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rb_login_attempts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(50) NOT NULL,
                `username` VARCHAR(100) NOT NULL,
                `attempt_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `is_success` TINYINT(1) NOT NULL DEFAULT 0,
                INDEX `idx_rb_ip_time` (`ip_address`, `attempt_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $ensured = true;
    } catch (\Throwable $e) {
        error_log('[RoomBooking ensureRoomBookingTables] ' . $e->getMessage());
    }
}

/**
 * ปรับปรุงและซิงก์ข้อมูล 7 ห้องประชุมหลักของโรงเรียนละลมวิทยา
 */
function syncSchoolRooms(PDO $pdo): void {
    static $synced = false;
    if ($synced) return;

    $targetRooms = [
        [
            'code' => 'R01',
            'name' => 'ห้องประชุม 30 ปี',
            'building' => 'อาคาร 30 ปี',
            'floor' => 'ชั้น 2',
            'capacity' => 120,
            'facilities' => 'เครื่องปรับอากาศ, โปรเจกเตอร์, จอ LED, เครื่องเสียง, ไมค์ลอย 4 ตัว, โพเดียม',
            'color' => '#2563eb'
        ],
        [
            'code' => 'R02',
            'name' => 'ห้องประชุมเจียละออ',
            'building' => 'อาคารเรียน',
            'floor' => 'ชั้น 1',
            'capacity' => 60,
            'facilities' => 'เครื่องปรับอากาศ, Smart TV / จอฉาย, ระบบเครื่องเสียง, ไมค์ลอย',
            'color' => '#059669'
        ],
        [
            'code' => 'R03',
            'name' => 'ห้องประชุมราชพฤกษ์',
            'building' => 'อาคารราชพฤกษ์',
            'floor' => 'ชั้น 2',
            'capacity' => 50,
            'facilities' => 'เครื่องปรับอากาศ, โต๊ะประชุม U-Shape, โปรเจกเตอร์, เครื่องเสียง',
            'color' => '#d97706'
        ],
        [
            'code' => 'R04',
            'name' => 'ห้องทูบี',
            'building' => 'ศูนย์เพื่อนใจ TO BE NUMBER ONE',
            'floor' => 'ชั้น 1',
            'capacity' => 40,
            'facilities' => 'เครื่องปรับอากาศ, เครื่องเสียง, ชุดรับแขก, บอร์ดกิจกรรม, เก้าอี้ประชุม',
            'color' => '#e11d48'
        ],
        [
            'code' => 'R05',
            'name' => 'ห้องคอมพิวเตอร์1',
            'building' => 'อาคารปฏิบัติการ',
            'floor' => 'ชั้น 2',
            'capacity' => 45,
            'facilities' => 'เครื่องคอมพิวเตอร์ 40 เครื่อง, จอฉายโปรเจกเตอร์, เครือข่าย Fiber Optic, แอร์',
            'color' => '#0284c7'
        ],
        [
            'code' => 'R06',
            'name' => 'ห้องคอมพิวเตอร์2',
            'building' => 'อาคารปฏิบัติการ',
            'floor' => 'ชั้น 2',
            'capacity' => 45,
            'facilities' => 'เครื่องคอมพิวเตอร์ 40 เครื่อง, จอฉายโปรเจกเตอร์, เครือข่าย Fiber Optic, แอร์',
            'color' => '#7c3aed'
        ],
        [
            'code' => 'R07',
            'name' => 'โดมอเนกประสงค์',
            'building' => 'ลานโดมกิจกรรม',
            'floor' => 'ชั้นล่าง',
            'capacity' => 500,
            'facilities' => 'เวทีกิจกรรม, ระบบเครื่องเสียงกลางแจ้ง, ไมค์สาย/ไร้สาย, อัฒจันทร์, พัดลมระบายอากาศ',
            'color' => '#ea580c'
        ],
    ];

    try {
        // ตรวจสอบว่ามีประวัติการจองจริงในระบบหรือไม่
        $hasBookings = false;
        try {
            $hasBookings = (int)$pdo->query("SELECT COUNT(*) FROM rb_bookings")->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $hasBookings = false;
        }

        if (!$hasBookings) {
            // หากยังไม่มีประวัติการจองจริง ให้รีเซ็ตตาราง rb_rooms และนำเข้า 7 ห้องหลักที่ถูกต้อง 100%
            $pdo->exec("DELETE FROM rb_rooms");
            try { $pdo->exec("ALTER TABLE rb_rooms AUTO_INCREMENT = 1"); } catch (\Throwable $e) {}

            $stmtIns = $pdo->prepare("
                INSERT INTO rb_rooms (room_code, room_name, building, floor, capacity, facilities, color_code, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            foreach ($targetRooms as $r) {
                $stmtIns->execute([$r['code'], $r['name'], $r['building'], $r['floor'], $r['capacity'], $r['facilities'], $r['color']]);
            }
        } else {
            // กรณีมีประวัติการจองอยู่แล้ว ให้ใช้วิธี UPDATE หรือ INSERT ทีละห้องเพื่อรักษา Foreign Key
            foreach ($targetRooms as $r) {
                $check = $pdo->prepare("SELECT id FROM rb_rooms WHERE room_code = ? OR room_name = ? LIMIT 1");
                $check->execute([$r['code'], $r['name']]);
                $existing = $check->fetch();

                if ($existing) {
                    $pdo->prepare("
                        UPDATE rb_rooms SET 
                            room_code = ?, room_name = ?, building = ?, floor = ?, 
                            capacity = ?, facilities = ?, color_code = ?, is_active = 1 
                        WHERE id = ?
                    ")->execute([$r['code'], $r['name'], $r['building'], $r['floor'], $r['capacity'], $r['facilities'], $r['color'], $existing['id']]);
                } else {
                    $pdo->prepare("
                        INSERT INTO rb_rooms (room_code, room_name, building, floor, capacity, facilities, color_code, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ")->execute([$r['code'], $r['name'], $r['building'], $r['floor'], $r['capacity'], $r['facilities'], $r['color']]);
                }
            }
        }
        $synced = true;
    } catch (\Throwable $e) {
        error_log('[RoomBooking syncSchoolRooms] ' . $e->getMessage());
    }
}
