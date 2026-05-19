<?php
/**
 * Migration: create_llw_user_roles
 * เปลี่ยน role system จาก 1:1 (llw_users.role) → 1:M (llw_user_roles pivot)
 * คงคอลัมน์ llw_users.role ไว้ใช้เป็น "primary role" เพื่อ backward-compat
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS llw_user_roles (
                user_id     INT NOT NULL,
                role        VARCHAR(40) NOT NULL,
                is_primary  TINYINT(1) NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, role),
                KEY idx_role (role),
                KEY idx_primary (user_id, is_primary),
                CONSTRAINT fk_lur_user FOREIGN KEY (user_id)
                    REFERENCES llw_users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Backfill: ผู้ใช้ทุกคนใน llw_users → llw_user_roles (role เดิม = primary)
        $pdo->exec("
            INSERT IGNORE INTO llw_user_roles (user_id, role, is_primary)
            SELECT user_id, role, 1
            FROM llw_users
            WHERE role IS NOT NULL AND role <> ''
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS llw_user_roles");
    },
];
