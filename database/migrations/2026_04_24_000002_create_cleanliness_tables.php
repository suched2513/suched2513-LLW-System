<?php
/**
 * Migration: create_cleanliness_tables
 * สร้างตารางสำหรับระบบบันทึกความสะอาด (Cleanliness Recording System)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. พื้นที่รับผิดชอบ (Areas)
        $pdo->exec("CREATE TABLE IF NOT EXISTS clean_areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            assigned_class VARCHAR(50) NULL COMMENT 'ห้องเรียนที่รับผิดชอบ เช่น ม.1/1',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. บันทึกคะแนน (Scores)
        $pdo->exec("CREATE TABLE IF NOT EXISTS clean_scores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            score_date DATE NOT NULL,
            cleanliness_score TINYINT(1) NOT NULL DEFAULT 3 COMMENT 'คะแนนความสะอาด 1-5',
            orderliness_score TINYINT(1) NOT NULL DEFAULT 3 COMMENT 'คะแนนความเรียบร้อย 1-5',
            area_id INT NOT NULL,
            class_name VARCHAR(50) NOT NULL COMMENT 'ชื่อห้องที่ถูกประเมิน ณ ขณะนั้น',
            score INT NOT NULL DEFAULT 0 COMMENT 'คะแนนรวม (คำนวณจากเกณฑ์)',
            notes TEXT NULL,
            recorded_by_user_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (area_id) REFERENCES clean_areas(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by_user_id) REFERENCES llw_users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. ตั้งค่าระบบ/เกียรติบัตร (Settings)
        $pdo->exec("CREATE TABLE IF NOT EXISTS clean_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Insert Default Settings (Inspired by user's SQL)
        $defaults = [
            ['cert_title', 'โรงเรียนละลมวิทยา'],
            ['cert_subtitle', 'ฉบับนี้ให้ไว้เพื่อแสดงว่า'],
            ['cert_reason', 'เป็นห้องเรียนที่มีผลการประเมินความสะอาดและความเป็นระเบียบเรียบร้อย ในระดับยอดเยี่ยม'],
            ['cert_org_name', 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร'],
            ['cert_signature_name', '( นาย................. )'],
            ['cert_signature_title', 'ผู้อำนวยการโรงเรียนละลมวิทยา']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO clean_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaults as $row) {
            $stmt->execute($row);
        }

        // Insert Sample Areas if none exist
        $count = $pdo->query("SELECT COUNT(*) FROM clean_areas")->fetchColumn();
        if ($count == 0) {
            $areas = [
                ['หน้าอาคาร 1 (โซน A)', 'บริเวณเสาธงและสวนหย่อมด้านหน้า', 'ม.6/1'],
                ['โรงอาหาร', 'พื้นที่รับประทานอาหารและจุดทิ้งขยะ', 'ม.5/1'],
                ['สนามบาสเกตบอล', 'พื้นสนามและบริเวณที่นั่งรอบๆ', 'ม.4/1'],
                ['ห้องน้ำชาย ชั้น 2', 'ดูแลความสะอาดภายในห้องน้ำทั้งหมด', 'ม.3/1'],
                ['ลานกิจกรรมอเนกประสงค์', 'พื้นที่ลานกลางแจ้ง', 'ม.2/1']
            ];
            $stmtArea = $pdo->prepare("INSERT INTO clean_areas (name, description, assigned_class) VALUES (?, ?, ?)");
            foreach ($areas as $a) {
                $stmtArea->execute($a);
            }
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DROP TABLE IF EXISTS clean_scores;");
        $pdo->exec("DROP TABLE IF EXISTS clean_areas;");
        $pdo->exec("DROP TABLE IF EXISTS clean_settings;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    },
];
