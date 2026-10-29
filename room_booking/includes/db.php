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

        // เพิ่มข้อมูลห้องเริ่มต้นของโรงเรียนหากตารางยังว่างอยู่
        $roomCount = (int)$pdo->query("SELECT COUNT(*) FROM rb_rooms")->fetchColumn();
        if ($roomCount === 0) {
            $defaultRooms = [
                ['R01', 'ห้องประชุมเกียรติยศ (อาคาร 1)', 'อาคาร 1', 'ชั้น 2', 80, 'เครื่องปรับอากาศ, โปรเจกเตอร์, จอ LED, เครื่องเสียง, ไมค์ลอย 4 ตัว, โพเดียม', '#3b82f6', 'bi-award-fill'],
                ['R02', 'ห้องโสตทัศนศึกษา', 'อาคาร 2', 'ชั้น 1', 120, 'ระบบเสียงสเตอริโอ, โปรเจกเตอร์คู่, ระบบบันทึกภาพ/สตรีมมิ่ง, แอร์ 4 ทิศทาง', '#10b981', 'bi-camera-video-fill'],
                ['R03', 'ห้องประชุมวิทยพัฒน์ (วิชาการ)', 'อาคาร 3', 'ชั้น 2', 40, 'Smart TV 75 นิ้ว, โต๊ะประชุม U-Shape, ไวท์บอร์ดแก้ว, ระบบประชุมทางไกล', '#8b5cf6', 'bi-mortarboard-fill'],
                ['R04', 'ห้องประชุมอาคารเรียนรวม (ชั้นล่าง)', 'อาคารเรียนรวม', 'ชั้น 1', 150, 'พัดลมระบายอากาศ, เวที, เครื่องเสียงกลางแจ้ง, ไมค์สาย/ไร้สาย', '#f59e0b', 'bi-people-fill'],
                ['R05', 'ห้องปฏิบัติการคอมพิวเตอร์ 1', 'อาคาร 2', 'ชั้น 2', 45, 'คอมพิวเตอร์ 40 เครื่อง, จอฉายโปรเจกเตอร์, เครือข่าย Fiber Optic, แอร์', '#06b6d4', 'bi-laptop-fill']
            ];
            $stmt = $pdo->prepare("
                INSERT INTO rb_rooms (room_code, room_name, building, floor, capacity, facilities, color_code, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            foreach ($defaultRooms as $r) {
                $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]]);
            }
        }

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
