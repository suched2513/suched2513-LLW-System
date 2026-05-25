<?php
return [
    'up' => function (PDO $pdo) {
        // Growth standards from กรมอนามัย (DOH Thailand)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS health_growth_standards (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                gender      ENUM('male','female') NOT NULL,
                age_month   SMALLINT UNSIGNED NOT NULL,
                -- Height-for-age (cm) SD scores
                hfa_neg3    DECIMAL(5,2) NULL,
                hfa_neg2    DECIMAL(5,2) NULL,
                hfa_neg1    DECIMAL(5,2) NULL,
                hfa_median  DECIMAL(5,2) NULL,
                hfa_pos1    DECIMAL(5,2) NULL,
                hfa_pos2    DECIMAL(5,2) NULL,
                hfa_pos3    DECIMAL(5,2) NULL,
                -- Weight-for-age (kg) SD scores
                wfa_neg3    DECIMAL(5,2) NULL,
                wfa_neg2    DECIMAL(5,2) NULL,
                wfa_neg1    DECIMAL(5,2) NULL,
                wfa_median  DECIMAL(5,2) NULL,
                wfa_pos1    DECIMAL(5,2) NULL,
                wfa_pos2    DECIMAL(5,2) NULL,
                wfa_pos3    DECIMAL(5,2) NULL,
                -- BMI-for-age SD scores
                bfa_neg3    DECIMAL(5,2) NULL,
                bfa_neg2    DECIMAL(5,2) NULL,
                bfa_neg1    DECIMAL(5,2) NULL,
                bfa_median  DECIMAL(5,2) NULL,
                bfa_pos1    DECIMAL(5,2) NULL,
                bfa_pos2    DECIMAL(5,2) NULL,
                bfa_pos3    DECIMAL(5,2) NULL,
                UNIQUE KEY uq_gender_age (gender, age_month)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Health records
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS health_records (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                student_id      INT NOT NULL,
                weight_kg       DECIMAL(5,2) NOT NULL,
                height_cm       DECIMAL(5,2) NOT NULL,
                waist_cm        DECIMAL(5,2) NULL,
                bmi             DECIMAL(5,2) GENERATED ALWAYS AS (weight_kg / ((height_cm/100) * (height_cm/100))) STORED,
                record_date     DATE NOT NULL,
                semester        TINYINT NOT NULL DEFAULT 1 COMMENT '1 or 2',
                academic_year   SMALLINT NOT NULL,
                -- Computed status (filled on save)
                hfa_status      VARCHAR(30) NULL COMMENT 'เตี้ยมาก/เตี้ย/ปกติ/สูง',
                wfa_status      VARCHAR(30) NULL COMMENT 'น้ำหนักน้อยมาก/น้อย/ปกติ/เกิน',
                bfa_status      VARCHAR(30) NULL COMMENT 'ผอมมาก/ผอม/สมส่วน/น้ำหนักเกิน/อ้วน',
                note            TEXT NULL,
                recorded_by     INT NOT NULL COMMENT 'llw_users.user_id',
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_student   (student_id),
                INDEX idx_date      (record_date),
                INDEX idx_year_sem  (academic_year, semester)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS health_records");
        $pdo->exec("DROP TABLE IF EXISTS health_growth_standards");
    },
];
