<?php
/**
 * Migration: fix_sbms_projects_status
 * ตรวจสอบและเพิ่มคอลัมน์ status ในตาราง sbms_projects หากขาดหายไป
 */
return [
    'up' => function (PDO $pdo) {
        // ตรวจสอบว่ามีคอลัมน์ status หรือยัง
        $stmt = $pdo->query("SHOW COLUMNS FROM sbms_projects LIKE 'status'");
        $column = $stmt->fetch();

        if (!$column) {
            $pdo->exec("ALTER TABLE sbms_projects ADD COLUMN status ENUM('draft','pending','approved','rejected','in_progress','completed','cancelled') DEFAULT 'draft' AFTER used_amount");
        }
    },
    'down' => function (PDO $pdo) {
        // ไม่ต้องทำอะไรใน down สำหรับการแก้บั๊กโครงสร้าง
    }
];
