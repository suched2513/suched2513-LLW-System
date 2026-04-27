<?php
/**
 * Seed: school_project default settings
 * ค่าเริ่มต้นของระบบขอดำเนินโครงการ
 */
return [
    'run' => function (PDO $pdo) {
        $exists = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
        if (!$exists) return;

        $defaults = [
            'school_name'         => 'โรงเรียนละลมวิทยา',
            'school_district'     => 'อำเภอภูสิงห์',
            'school_province'     => 'จังหวัดศรีสะเกษ',
            'fiscal_year'         => '2569',
            'overdue_days'        => '30',
            'budget_warning_pct'  => '80',
            'line_notify_token'   => '',
            'smtp_host'           => '',
            'smtp_port'           => '587',
            'smtp_user'           => '',
            'smtp_from'           => '',
        ];

        $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`)
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    },
];
