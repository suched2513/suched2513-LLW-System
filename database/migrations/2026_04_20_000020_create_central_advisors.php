<?php
/**
 * Migration: Create central advisor mapping table (llw_class_advisors)
 * เชื่อมโยงห้องเรียนกับครูที่ปรึกษา (Homeroom Advisors)
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `llw_class_advisors` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `classroom`   VARCHAR(50) NOT NULL COMMENT 'รูปแบบ ม.1/1',
                `user_id`     INT NOT NULL COMMENT 'FK -> llw_users.user_id',
                `role_type`   ENUM('primary','secondary') DEFAULT 'primary',
                `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_class_teacher` (`classroom`, `user_id`),
                INDEX `idx_classroom` (`classroom`),
                INDEX `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migration from legacy tables if they exist
        try {
            // From beh_advisors (Behavior)
            // Note: beh_advisors uses level/room, we need to map to ม.X/Y
            $check = $pdo->query("SHOW TABLES LIKE 'beh_advisors'");
            if ($check && $check->rowCount() > 0) {
                $pdo->exec("
                    INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type)
                    SELECT CONCAT('ม.', level, '/', room), user_id, 'primary' FROM beh_advisors
                ");
            }

            // From assembly_classrooms (Assembly)
            $check = $pdo->query("SHOW TABLES LIKE 'assembly_classrooms'");
            if ($check && $check->rowCount() > 0) {
                $pdo->exec("
                    INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type)
                    SELECT classroom, llw_user_id, 'primary' FROM assembly_classrooms
                    WHERE llw_user_id IS NOT NULL AND llw_user_id > 0
                ");
            }
        } catch (Exception $e) {
            error_log('[Migration] Advisor data import failed: ' . $e->getMessage());
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `llw_class_advisors` ");
    },
];
