<?php
/**
 * Seed: สร้าง Super Admin user เริ่มต้น
 *
 * Username: admin_llw
 * Password: 123456
 * Role: super_admin
 */
return [
    'run' => function (PDO $pdo) {
        $check = $pdo->prepare("SELECT user_id FROM llw_users WHERE username = ?");
        $check->execute(['admin_llw']);

        if ($check->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO llw_users (username, password, firstname, lastname, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'admin_llw',
                password_hash('123456', PASSWORD_DEFAULT),
                'Admin',
                'LLW',
                'super_admin',
                'active',
            ]);
            echo "    Created admin_llw (pass: 123456)\n";
        } else {
            echo "    admin_llw already exists — skipped\n";
        }
    },
];
