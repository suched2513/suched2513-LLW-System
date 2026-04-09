<?php
/**
 * Migration: เพิ่ม is_elective ใน att_subjects + สร้าง att_subject_students
 * วิชาเลือก: นักเรียนต่างคนเลือกต่างวิชาในห้องเดียวกัน
 */
return [
    'up' => function(PDO $pdo) {
        // 1. เพิ่ม is_elective ใน att_subjects
        $pdo->exec("ALTER TABLE att_subjects ADD COLUMN IF NOT EXISTS is_elective TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=บังคับ, 1=วิชาเลือก' AFTER classroom");

        // 2. สร้าง att_subject_students (enrollment สำหรับวิชาเลือก)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS att_subject_students (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                subject_id  INT NOT NULL,
                student_id  INT NOT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_subj_std (subject_id, student_id),
                FOREIGN KEY (subject_id) REFERENCES att_subjects(id)  ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES att_students(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function(PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS att_subject_students");
        $pdo->exec("ALTER TABLE att_subjects DROP COLUMN IF EXISTS is_elective");
    }
];
