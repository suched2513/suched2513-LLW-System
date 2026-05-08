<?php
/**
 * Migration: create_duty_system_tables
 * Created: 2026-05-06
 *
 * สร้างตารางทั้งหมดสำหรับระบบแจ้งเตือนเวรประจำวัน
 * + รายงานเวรด้วยรูปถ่ายผ่าน Telegram Bot
 */
return [
    'up' => function (PDO $pdo) {

        // ──────────────────────────────────────────────────────────────
        // 1. duty_teachers — ครูเวร (แยกออกจาก att_teachers)
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_teachers (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                prefix              VARCHAR(20)  NULL COMMENT 'คำนำหน้า เช่น นาย/นาง/นางสาว',
                full_name           VARCHAR(150) NOT NULL COMMENT 'ชื่อ-สกุลเต็ม',
                phone               VARCHAR(20)  NULL,
                telegram_user_id    BIGINT       NULL UNIQUE COMMENT 'Telegram numeric user ID',
                telegram_username   VARCHAR(50)  NULL COMMENT 'Telegram @username (ไม่มี @)',
                link_token          VARCHAR(40)  NULL COMMENT 'One-time token สำหรับเชื่อมบัญชี',
                linked_at           TIMESTAMP    NULL COMMENT 'เวลาที่ผูกบัญชีสำเร็จ',
                status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_telegram_user_id (telegram_user_id),
                INDEX idx_link_token (link_token),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='ครูเวรประจำวัน — เชื่อมต่อ Telegram Bot'
        ");

        // ──────────────────────────────────────────────────────────────
        // 2. duty_schedule — ตารางเวรประจำวัน
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_schedule (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                duty_date   DATE         NOT NULL COMMENT 'วันที่รับเวร',
                shift       ENUM('day','night') NOT NULL COMMENT 'กะ: day=กลางวัน, night=กลางคืน',
                point_no    TINYINT      NOT NULL COMMENT 'หมายเลขจุดตรวจ',
                role        VARCHAR(100) NULL     COMMENT 'บทบาท/หน้าที่ในจุดนี้',
                teacher_id  INT          NULL     COMMENT 'FK → duty_teachers.id',
                teacher_seq TINYINT      NOT NULL DEFAULT 1 COMMENT 'ลำดับครูในจุดเดียวกัน (กรณีมีหลายคน)',
                note        VARCHAR(500) NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES duty_teachers(id) ON DELETE SET NULL,
                INDEX idx_duty_date (duty_date),
                INDEX idx_shift (shift),
                INDEX idx_point_no (point_no),
                INDEX idx_teacher_id (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='ตารางเวรครูประจำวัน'
        ");

        // ──────────────────────────────────────────────────────────────
        // 3. duty_reports — บันทึกสถานะการรายงานของแต่ละจุด
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_reports (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                duty_date           DATE         NOT NULL,
                shift               ENUM('day','night') NOT NULL,
                point_no            INT          NOT NULL,
                teacher_id          INT          NULL COMMENT 'FK → duty_teachers.id',
                report_round        TINYINT      NOT NULL DEFAULT 1 COMMENT 'รอบที่ (เผื่ออนาคต)',
                status              ENUM('pending','partial','complete') NOT NULL DEFAULT 'pending',
                completed_at        TIMESTAMP    NULL COMMENT 'เวลาที่รายงานครบ',
                report_note         TEXT         NULL COMMENT 'รายละเอียด: ใคร ทำอะไร ที่ไหน เมื่อไหร่ อย่างไร',
                reminder_sent_at    TIMESTAMP    NULL COMMENT 'เวลาที่ส่งการเตือนล่าสุด',
                created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES duty_teachers(id) ON DELETE SET NULL,
                UNIQUE KEY uk_report (duty_date, shift, point_no, report_round),
                INDEX idx_duty_date_shift (duty_date, shift, point_no),
                INDEX idx_status (status),
                INDEX idx_teacher_id (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='บันทึกสถานะการรายงานเวร'
        ");

        // ──────────────────────────────────────────────────────────────
        // 4. duty_report_photos — รูปภาพที่ครูส่งมา
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_report_photos (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                report_id           INT          NOT NULL COMMENT 'FK → duty_reports.id',
                file_id             VARCHAR(255) NULL COMMENT 'Telegram file_id (ใช้ refetch)',
                file_path           VARCHAR(500) NULL COMMENT 'path จริงในเซิร์ฟเวอร์',
                thumbnail_path      VARCHAR(500) NULL COMMENT 'path thumbnail (max 400px)',
                file_size           INT          NULL COMMENT 'ขนาดไฟล์ (bytes)',
                width               INT          NULL COMMENT 'ความกว้างรูป (px)',
                height              INT          NULL COMMENT 'ความสูงรูป (px)',
                caption             VARCHAR(500) NULL COMMENT 'คำอธิบายรูป (ถ้ามี)',
                sent_from_user_id   BIGINT       NULL COMMENT 'Telegram user_id ผู้ส่ง',
                exif_gps_lat        DECIMAL(10,7) NULL COMMENT 'GPS lat จาก EXIF (ไม่แสดงใน UI)',
                exif_gps_lng        DECIMAL(10,7) NULL COMMENT 'GPS lng จาก EXIF (ไม่แสดงใน UI)',
                is_deleted          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'soft delete',
                deleted_by          INT          NULL COMMENT 'user_id ที่ลบ',
                deleted_at          TIMESTAMP    NULL,
                received_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (report_id) REFERENCES duty_reports(id) ON DELETE CASCADE,
                INDEX idx_report_id (report_id),
                INDEX idx_received_at (received_at),
                INDEX idx_sent_from (sent_from_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='รูปภาพที่ครูส่งรายงานเวรผ่าน Telegram'
        ");

        // ──────────────────────────────────────────────────────────────
        // 5. duty_rate_limits — Rate limit สำหรับ webhook
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_rate_limits (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     BIGINT       NOT NULL COMMENT 'Telegram user_id',
                action      VARCHAR(50)  NOT NULL DEFAULT 'photo',
                window_start TIMESTAMP   NOT NULL COMMENT 'ต้นหน้าต่าง 1 นาที',
                count       INT          NOT NULL DEFAULT 1,
                UNIQUE KEY uk_rate (user_id, action, window_start),
                INDEX idx_user_action (user_id, action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Rate limit สำหรับ Telegram Webhook'
        ");

        // ──────────────────────────────────────────────────────────────
        // 6. duty_pending_photos — รูปที่รอเลือกจุด (callback_query)
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_pending_photos (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                telegram_user_id BIGINT      NOT NULL,
                file_id         VARCHAR(255) NOT NULL COMMENT 'Telegram file_id ที่รอผูก',
                file_size       INT          NULL,
                width           INT          NULL,
                height          INT          NULL,
                caption         VARCHAR(500) NULL,
                expires_at      TIMESTAMP    NOT NULL COMMENT 'หมดอายุใน 10 นาที',
                created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (telegram_user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='รูปที่รอผู้ใช้เลือกจุด (มีหลายจุด)'
        ");

        // ──────────────────────────────────────────────────────────────
        // 7. เพิ่ม Settings keys สำหรับระบบเวร
        //    ใช้ wfh_system_settings row ที่มีอยู่ (setting_id=1)
        //    แต่ระบบ duty ต้องการ key-value store แยก → ใช้ duty_settings
        // ──────────────────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_settings (
                skey        VARCHAR(100) NOT NULL PRIMARY KEY,
                svalue      TEXT         NULL,
                description VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='ตั้งค่าระบบเวร'
        ");

        // Insert default settings (ใช้ INSERT IGNORE เผื่อ run ซ้ำ)
        $defaults = [
            ['photos_required_per_point', '3',    'จำนวนรูปต่อจุด'],
            ['report_deadline_day',       '10:00', 'เวรกลางวันต้องรายงานก่อนกี่โมง'],
            ['report_deadline_night',     '22:00', 'เวรกลางคืนต้องรายงานก่อนกี่โมง'],
            ['reminder_minutes_before',   '60',    'เตือนล่วงหน้ากี่นาที'],
            ['daily_summary_time',        '18:30', 'เวลาส่งสรุปเข้ากลุ่ม'],
            ['upload_dir',                'uploads/reports', 'โฟลเดอร์เก็บรูป'],
            ['telegram_webhook_secret',   '',      'Secret token สำหรับ Telegram Webhook'],
            ['duty_bot_username',         '',      'Bot username สำหรับ สร้างลิงก์ start'],
            ['summary_attach_preview',    '0',     '1=แนบรูป preview ในสรุปรายวัน'],
            ['max_photos_per_minute',     '30',    'Rate limit: รูปสูงสุดต่อนาทีต่อ user'],
        ];

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO duty_settings (skey, svalue, description) VALUES (?, ?, ?)"
        );
        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    },

    'down' => function (PDO $pdo) {
        // ลบตามลำดับ FK
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS duty_pending_photos");
        $pdo->exec("DROP TABLE IF EXISTS duty_rate_limits");
        $pdo->exec("DROP TABLE IF EXISTS duty_report_photos");
        $pdo->exec("DROP TABLE IF EXISTS duty_reports");
        $pdo->exec("DROP TABLE IF EXISTS duty_schedule");
        $pdo->exec("DROP TABLE IF EXISTS duty_teachers");
        $pdo->exec("DROP TABLE IF EXISTS duty_settings");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
