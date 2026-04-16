<?php
/**
 * Migration: ออกแบบระบบนิเทศ (Active Learning Supervision)
 * 1. เพิ่มฟิลด์โปรไฟล์ครูใน llw_users
 * 2. สร้างตารางเก็บผลการนิเทศ
 */
return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่ม profiling fields ใน llw_users
        $pdo->exec("
            ALTER TABLE llw_users 
            ADD COLUMN position VARCHAR(200) DEFAULT '' AFTER lastname,
            ADD COLUMN academic_status VARCHAR(200) DEFAULT '' AFTER position,
            ADD COLUMN subject_group VARCHAR(200) DEFAULT '' AFTER academic_status
        ");

        // 2. ตารางเก็บหัวข้อการนิเทศ (Record)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sup_records (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id      INT NOT NULL,
                course_name     VARCHAR(255) NOT NULL,
                course_code     VARCHAR(100) NOT NULL,
                class_level     VARCHAR(100) NOT NULL,
                observation_date DATE NOT NULL,
                total_score     INT NOT NULL DEFAULT 0,
                average_score   DECIMAL(3,2) NOT NULL DEFAULT 0.00,
                interpretation  VARCHAR(200) DEFAULT '',
                findings        TEXT,
                impressions     TEXT,
                improvements    TEXT,
                observer_name   VARCHAR(200) NOT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (teacher_id),
                INDEX (observation_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. ตารางเก็บคะแนนรายข้อ (27 ข้อ)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sup_scores (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                record_id INT NOT NULL,
                item_idx  INT NOT NULL, -- 0-26
                score     INT NOT NULL, -- 1-5
                FOREIGN KEY (record_id) REFERENCES sup_records(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS sup_scores");
        $pdo->exec("DROP TABLE IF EXISTS sup_records");
        $pdo->exec("ALTER TABLE llw_users DROP COLUMN subject_group, DROP COLUMN academic_status, DROP COLUMN position");
    },
];
