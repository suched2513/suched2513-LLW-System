<?php
/**
 * run_lms_migration.php — One-time LMS table setup for production
 * DELETE THIS FILE AFTER RUNNING!
 */

// Simple secret check to prevent unauthorized access
if (!isset($_GET['key']) || $_GET['key'] !== 'lms2026setup') {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 0. Alter llw_users to add 'student' role if not exists
    $row = $pdo->query("SHOW COLUMNS FROM llw_users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    if ($row && strpos($row['Type'], 'student') === false) {
        $pdo->exec("
            ALTER TABLE llw_users MODIFY COLUMN role
            ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher','edoc_admin','student','club_admin','bus_admin','bus_finance')
            NOT NULL DEFAULT 'wfh_staff'
        ");
        echo "✓ Altered llw_users.role ENUM to include 'student'\n";
    } else {
        echo "→ llw_users.role already has 'student'\n";
    }

    // 1. lms_units
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_units (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            subject_id   INT NOT NULL,
            unit_number  INT NOT NULL,
            unit_name    VARCHAR(255) NOT NULL,
            description  TEXT NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_lms_unit_subject FOREIGN KEY (subject_id) REFERENCES att_subjects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ lms_units ready\n";

    // 2. lms_quizzes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_quizzes (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            unit_id    INT NOT NULL,
            quiz_type  ENUM('pre', 'post') NOT NULL,
            title      VARCHAR(255) NOT NULL,
            time_limit INT DEFAULT 0,
            is_active  TINYINT DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_lms_quiz_unit FOREIGN KEY (unit_id) REFERENCES lms_units (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ lms_quizzes ready\n";

    // 3. lms_questions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_questions (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id       INT NOT NULL,
            question_text TEXT NOT NULL,
            points        INT DEFAULT 1,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_lms_question_quiz FOREIGN KEY (quiz_id) REFERENCES lms_quizzes (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ lms_questions ready\n";

    // 4. lms_choices
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_choices (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            choice_text TEXT NOT NULL,
            is_correct  TINYINT NOT NULL DEFAULT 0,
            CONSTRAINT fk_lms_choice_question FOREIGN KEY (question_id) REFERENCES lms_questions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ lms_choices ready\n";

    // 5. lms_quiz_attempts
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_quiz_attempts (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id      INT NOT NULL,
            student_id   INT NOT NULL,
            score        FLOAT DEFAULT 0,
            total_points INT DEFAULT 0,
            status       ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress',
            started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            CONSTRAINT fk_lms_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES lms_quizzes (id) ON DELETE CASCADE,
            CONSTRAINT fk_lms_attempt_student FOREIGN KEY (student_id) REFERENCES att_students (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ lms_quiz_attempts ready\n";

    // 6. lms_quiz_answers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lms_quiz_answers (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id  INT NOT NULL,
            question_id INT NOT NULL,
            choice_id   INT NULL,
            is_correct  TINYINT NOT NULL DEFAULT 0,
            points_earned FLOAT NOT NULL DEFAULT 0,
            CONSTRAINT fk_lms_ans_attempt FOREIGN KEY (attempt_id) REFERENCES lms_quiz_attempts (id) ON DELETE CASCADE,
            CONSTRAINT fk_lms_ans_question FOREIGN KEY (question_id) REFERENCES lms_questions (id) ON DELETE CASCADE,
            CONSTRAINT fk_lms_ans_choice FOREIGN KEY (choice_id) REFERENCES lms_choices (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ lms_quiz_answers ready\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n========================================\n";
    echo "✅ LMS Migration สำเร็จแล้ว!\n";
    echo "⚠️  กรุณาลบไฟล์นี้ออกจาก server ทันที!\n";
    echo "    DELETE: run_lms_migration.php\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
