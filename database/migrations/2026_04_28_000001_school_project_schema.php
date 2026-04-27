<?php
/**
 * Migration: school_project_schema
 * Created: 2026-04-28
 *
 * เพิ่มสคีมาที่จำเป็นสำหรับโมดูล school_project (ระบบขอดำเนินโครงการ)
 *  - ปรับ budget_projects: เปลี่ยน PK จาก project_id → id, เพิ่มคอลัมน์งบประมาณ + is_active + owner_name
 *  - ปรับ llw_users: role → VARCHAR(50), เพิ่ม department_id + owner_name
 *  - สร้าง departments, signatories, settings, audit_logs, notifications
 *  - สร้าง project_requests, request_items, request_committee
 *  - สร้าง view `users` เพื่อเข้ากันได้กับ legacy queries (SELECT only)
 */
return [
    'up' => function (PDO $pdo) {

        // =========================================================
        // 1. ปรับ llw_users — เปลี่ยน role เป็น VARCHAR เพื่อรองรับ
        //    role ของ school_project (admin/director/teacher/head/budget_officer)
        //    + เพิ่ม department_id + owner_name
        // =========================================================
        $userCols = $pdo->query("SHOW COLUMNS FROM `llw_users`")->fetchAll(PDO::FETCH_COLUMN);

        // เปลี่ยน role จาก ENUM → VARCHAR(50) เพื่อรองรับ role ใหม่
        $roleCol = $pdo->query("SHOW COLUMNS FROM `llw_users` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        if ($roleCol && stripos($roleCol['Type'], 'enum') !== false) {
            $pdo->exec("ALTER TABLE `llw_users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'wfh_staff'");
        }

        if (!in_array('department_id', $userCols)) {
            $pdo->exec("ALTER TABLE `llw_users` ADD COLUMN `department_id` INT NULL AFTER `role`");
        }
        if (!in_array('owner_name', $userCols)) {
            $pdo->exec("ALTER TABLE `llw_users` ADD COLUMN `owner_name` VARCHAR(200) NULL AFTER `department_id`");
        }

        // =========================================================
        // 2. ปรับ budget_projects — เปลี่ยน PK project_id → id
        //    + เพิ่มคอลัมน์งบประมาณ/สถานะที่ school_project ต้องการ
        // =========================================================
        $bpExists = $pdo->query("SHOW TABLES LIKE 'budget_projects'")->fetchColumn();

        if ($bpExists) {
            $bpCols = $pdo->query("SHOW COLUMNS FROM `budget_projects`")->fetchAll(PDO::FETCH_COLUMN);

            // ก่อน rename ต้องลบ FK ใน budget_transactions และ budget_disbursements ที่ point มา
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $hasProjectId = in_array('project_id', $bpCols);
            $hasIdCol     = in_array('id', $bpCols);

            if ($hasProjectId && !$hasIdCol) {
                // ลบ FK ก่อน rename
                $fkRows = $pdo->query("
                    SELECT TABLE_NAME, CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                      AND REFERENCED_TABLE_NAME = 'budget_projects'
                      AND REFERENCED_COLUMN_NAME = 'project_id'
                ")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($fkRows as $fk) {
                    $pdo->exec("ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                }

                // rename column
                $pdo->exec("ALTER TABLE `budget_projects` CHANGE COLUMN `project_id` `id` INT(11) NOT NULL AUTO_INCREMENT");

                // re-add FKs (ตอนนี้ child column ชื่อ project_id แต่ point ไป budget_projects.id)
                foreach ($fkRows as $fk) {
                    $pdo->exec("ALTER TABLE `{$fk['TABLE_NAME']}`
                        ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}`
                        FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`id`) ON DELETE CASCADE");
                }
            }

            // รีเฟรช column list หลัง rename
            $bpCols = $pdo->query("SHOW COLUMNS FROM `budget_projects`")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('activity', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `activity` TEXT NULL AFTER `description`");
            }
            if (!in_array('owner_name', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `owner_name` VARCHAR(200) NULL AFTER `activity`");
            }
            if (!in_array('project_group', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `project_group` VARCHAR(100) NULL AFTER `owner_name`");
            }
            if (!in_array('budget_subsidy', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `budget_subsidy` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `project_group`");
            }
            if (!in_array('budget_quality', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `budget_quality` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `budget_subsidy`");
            }
            if (!in_array('budget_revenue', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `budget_revenue` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `budget_quality`");
            }
            if (!in_array('budget_operation', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `budget_operation` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `budget_revenue`");
            }
            if (!in_array('budget_reserve', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `budget_reserve` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `budget_operation`");
            }
            if (!in_array('is_active', $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } else {
            // ตารางยังไม่มี — สร้างด้วยสคีมาเต็มของ school_project
            $pdo->exec("CREATE TABLE `budget_projects` (
                `id`               INT(11) NOT NULL AUTO_INCREMENT,
                `project_name`     VARCHAR(200) NOT NULL,
                `department_id`    INT(11) NULL,
                `fiscal_year`      VARCHAR(4) NULL,
                `description`      TEXT NULL,
                `activity`         TEXT NULL,
                `owner_name`       VARCHAR(200) NULL,
                `project_group`    VARCHAR(100) NULL,
                `total_budget`     DECIMAL(15,2) NOT NULL DEFAULT 0,
                `budget_subsidy`   DECIMAL(15,2) NOT NULL DEFAULT 0,
                `budget_quality`   DECIMAL(15,2) NOT NULL DEFAULT 0,
                `budget_revenue`   DECIMAL(15,2) NOT NULL DEFAULT 0,
                `budget_operation` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `budget_reserve`   DECIMAL(15,2) NOT NULL DEFAULT 0,
                `start_date`       DATE NULL,
                `end_date`         DATE NULL,
                `status`           ENUM('active','completed','cancelled') DEFAULT 'active',
                `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
                `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `created_by`       INT(11) NULL,
                PRIMARY KEY (`id`),
                KEY `idx_bp_status` (`status`),
                KEY `idx_bp_fy` (`fiscal_year`),
                KEY `idx_bp_dept` (`department_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // =========================================================
        // 3. departments — ฝ่าย/กลุ่มสาระ
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
            `id`        INT(11) NOT NULL AUTO_INCREMENT,
            `name`      VARCHAR(200) NOT NULL,
            `order_no`  INT(11) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dept_order` (`order_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 4. signatories — รายชื่อผู้ลงนามในเอกสาร
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `signatories` (
            `id`         INT(11) NOT NULL AUTO_INCREMENT,
            `role_label` VARCHAR(200) NOT NULL,
            `full_name`  VARCHAR(200) NOT NULL,
            `position`   VARCHAR(200) NULL,
            `order_no`   INT(11) NOT NULL DEFAULT 0,
            `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sig_active` (`is_active`, `order_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 5. settings — key/value config ของ school_project
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key`   VARCHAR(100) NOT NULL,
            `setting_value` TEXT NULL,
            `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 6. audit_logs — บันทึกการกระทำต่างๆ
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`     INT(11) NULL,
            `action`      VARCHAR(100) NOT NULL,
            `target_type` VARCHAR(100) NULL,
            `target_id`   INT(11) NULL,
            `old_value`   TEXT NULL,
            `new_value`   TEXT NULL,
            `ip_address`  VARCHAR(50) NULL,
            `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_audit_user` (`user_id`),
            KEY `idx_audit_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 7. notifications — แจ้งเตือนผู้ใช้
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id`           INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`      INT(11) NOT NULL,
            `type`         VARCHAR(100) NOT NULL,
            `title`        VARCHAR(255) NOT NULL,
            `message`      TEXT NULL,
            `related_id`   INT(11) NULL,
            `related_type` VARCHAR(50) NULL,
            `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
            `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_notif_user` (`user_id`, `is_read`),
            KEY `idx_notif_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 8. project_requests — คำขอดำเนินโครงการ
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `project_requests` (
            `id`                 INT(11) NOT NULL AUTO_INCREMENT,
            `budget_project_id`  INT(11) NOT NULL,
            `user_id`            INT(11) NOT NULL,
            `project_no`         VARCHAR(50) NULL,
            `request_date`       DATE NULL,
            `proc_type`          ENUM('hire','buy') NOT NULL DEFAULT 'hire',
            `reason`             TEXT NULL,
            `activity_detail`    TEXT NULL,
            `inspector_name`     VARCHAR(200) NULL,
            `inspector_position` VARCHAR(200) NULL,
            `fund_type_used`     VARCHAR(100) NULL,
            `amount_requested`   DECIMAL(15,2) NOT NULL DEFAULT 0,
            `status`             ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
            `director_note`      TEXT NULL,
            `approved_at`        DATETIME NULL,
            `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pr_status` (`status`),
            KEY `idx_pr_user` (`user_id`),
            KEY `idx_pr_project` (`budget_project_id`),
            KEY `idx_pr_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 9. request_items — รายการสิ่งของในคำขอ
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `request_items` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `request_id`  INT(11) NOT NULL,
            `item_order`  INT(11) NOT NULL DEFAULT 1,
            `item_name`   VARCHAR(255) NOT NULL,
            `quantity`    DECIMAL(10,2) NOT NULL DEFAULT 1,
            `unit`        VARCHAR(50) NULL,
            `unit_price`  DECIMAL(15,2) NOT NULL DEFAULT 0,
            `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_ri_request` (`request_id`, `item_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 10. request_committee — คณะกรรมการในคำขอ
        // =========================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `request_committee` (
            `id`           INT(11) NOT NULL AUTO_INCREMENT,
            `request_id`   INT(11) NOT NULL,
            `member_order` INT(11) NOT NULL DEFAULT 1,
            `member_name`  VARCHAR(200) NOT NULL,
            `position`     VARCHAR(200) NULL,
            `role`         VARCHAR(100) NOT NULL DEFAULT 'กรรมการ',
            PRIMARY KEY (`id`),
            KEY `idx_rc_request` (`request_id`, `member_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // =========================================================
        // 11. View `users` — บริดจ์ legacy queries → llw_users
        //     (read-only view สำหรับ SELECT/JOIN; write ต้องไป llw_users ตรง)
        // =========================================================
        $pdo->exec("DROP VIEW IF EXISTS `users`");
        $pdo->exec("CREATE VIEW `users` AS
            SELECT
                `user_id` AS `id`,
                `user_id`,
                `username`,
                `password`,
                `firstname`,
                `lastname`,
                CONCAT(`firstname`, ' ', `lastname`) AS `full_name`,
                `role`,
                `status`,
                `department_id`,
                `owner_name`,
                CASE WHEN `status` = 'active' THEN 1 ELSE 0 END AS `is_active`,
                `last_login`,
                `created_at`
            FROM `llw_users`");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $pdo->exec("DROP VIEW IF EXISTS `users`");
        $pdo->exec("DROP TABLE IF EXISTS `request_committee`");
        $pdo->exec("DROP TABLE IF EXISTS `request_items`");
        $pdo->exec("DROP TABLE IF EXISTS `project_requests`");
        $pdo->exec("DROP TABLE IF EXISTS `notifications`");
        $pdo->exec("DROP TABLE IF EXISTS `audit_logs`");
        $pdo->exec("DROP TABLE IF EXISTS `settings`");
        $pdo->exec("DROP TABLE IF EXISTS `signatories`");
        $pdo->exec("DROP TABLE IF EXISTS `departments`");

        // ลบคอลัมน์ที่เพิ่มเข้า budget_projects
        $bpCols = $pdo->query("SHOW COLUMNS FROM `budget_projects`")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['is_active','budget_reserve','budget_operation','budget_revenue','budget_quality','budget_subsidy','project_group','owner_name','activity'] as $c) {
            if (in_array($c, $bpCols)) {
                $pdo->exec("ALTER TABLE `budget_projects` DROP COLUMN `$c`");
            }
        }

        // คืน column id → project_id (drop FKs ก่อน)
        $bpCols = $pdo->query("SHOW COLUMNS FROM `budget_projects`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('id', $bpCols) && !in_array('project_id', $bpCols)) {
            $fkRows = $pdo->query("
                SELECT TABLE_NAME, CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME = 'budget_projects'
                  AND REFERENCED_COLUMN_NAME = 'id'
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fkRows as $fk) {
                $pdo->exec("ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
            }
            $pdo->exec("ALTER TABLE `budget_projects` CHANGE COLUMN `id` `project_id` INT(11) NOT NULL AUTO_INCREMENT");
            foreach ($fkRows as $fk) {
                $pdo->exec("ALTER TABLE `{$fk['TABLE_NAME']}`
                    ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}`
                    FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`project_id`)");
            }
        }

        // คืน llw_users
        $userCols = $pdo->query("SHOW COLUMNS FROM `llw_users`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('owner_name', $userCols)) {
            $pdo->exec("ALTER TABLE `llw_users` DROP COLUMN `owner_name`");
        }
        if (in_array('department_id', $userCols)) {
            $pdo->exec("ALTER TABLE `llw_users` DROP COLUMN `department_id`");
        }
        $pdo->exec("ALTER TABLE `llw_users` MODIFY COLUMN `role` ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher') NOT NULL DEFAULT 'wfh_staff'");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
