<?php
/**
 * Migration: add_approver_signature_to_tl_approvals
 * เพิ่ม column signature_path ใน tl_approvals เพื่อเก็บลายเซ็นผู้อนุมัติ (ผู้อำนวยการ)
 */
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM tl_approvals LIKE 'signature_path'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE tl_approvals ADD COLUMN signature_path VARCHAR(500) NULL AFTER comment");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM tl_approvals LIKE 'signature_path'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE tl_approvals DROP COLUMN signature_path");
        }
    },
];
