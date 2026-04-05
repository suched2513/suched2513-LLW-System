<?php
/**
 * Seed: ค่าเริ่มต้นของ WFH System Settings
 */
return [
    'run' => function (PDO $pdo) {
        $check = $pdo->query("SELECT COUNT(*) FROM wfh_system_settings")->fetchColumn();

        if ((int)$check === 0) {
            $pdo->exec("
                INSERT INTO wfh_system_settings (regular_time_in, late_time, geofence_radius)
                VALUES ('08:00:00', '08:30:00', 200)
            ");
            echo "    Created default system settings\n";
        } else {
            echo "    System settings already exist — skipped\n";
        }
    },
];
