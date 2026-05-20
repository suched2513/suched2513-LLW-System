<?php
return [
    'up' => function (PDO $pdo) {
        // ลบ FK เก่าที่ผูก wfh_timelogs.user_id → wfh_users(user_id)
        // ระบบ unified auth ใช้ llw_users.user_id แทนแล้ว
        try {
            $pdo->exec("ALTER TABLE wfh_timelogs DROP FOREIGN KEY fk_wfh_user_timelog");
        } catch (Exception $e) {
            // FK อาจไม่มีในบาง environment — ข้ามไป
        }

        // ผูก FK ใหม่กับ llw_users(user_id)
        $fks = $pdo->query("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'wfh_timelogs'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME = 'llw_users'
        ")->fetchAll();

        if (empty($fks)) {
            // ลบ orphan records ที่ user_id ไม่มีใน llw_users ก่อนเพิ่ม FK
            $pdo->exec("
                DELETE FROM wfh_timelogs
                WHERE user_id NOT IN (SELECT user_id FROM llw_users)
            ");

            $pdo->exec("
                ALTER TABLE wfh_timelogs
                ADD CONSTRAINT fk_wfh_timelog_llw_user
                FOREIGN KEY (user_id) REFERENCES llw_users(user_id)
                ON DELETE CASCADE ON UPDATE CASCADE
            ");
        }
    },
    'down' => function (PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE wfh_timelogs DROP FOREIGN KEY fk_wfh_timelog_llw_user");
        } catch (Exception $e) {}

        // คืน FK เดิมกลับ (เฉพาะถ้าตาราง wfh_users ยังมีอยู่)
        $tables = $pdo->query("SHOW TABLES LIKE 'wfh_users'")->fetchAll();
        if (!empty($tables)) {
            try {
                $pdo->exec("
                    ALTER TABLE wfh_timelogs
                    ADD CONSTRAINT fk_wfh_user_timelog
                    FOREIGN KEY (user_id) REFERENCES wfh_users(user_id)
                    ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (Exception $e) {}
        }
    },
];
