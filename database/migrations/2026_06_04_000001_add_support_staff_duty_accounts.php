<?php
return [
    'up' => function (PDO $pdo) {
        $staff = [
            ['username' => 'wiseat',  'firstname' => 'วิเศษ',  'lastname' => 'เจือจันทร์', 'att_id' => 131],
            ['username' => 'samruay', 'firstname' => 'สำรวย', 'lastname' => 'วงษ์เพ็ง',   'att_id' => 132],
        ];

        foreach ($staff as $s) {
            $exists = $pdo->prepare("SELECT user_id FROM llw_users WHERE username = ?");
            $exists->execute([$s['username']]);
            $userId = $exists->fetchColumn();

            if (!$userId) {
                $hash = password_hash('llw1234', PASSWORD_DEFAULT);
                $ins = $pdo->prepare("
                    INSERT INTO llw_users (username, password, firstname, lastname, role, status)
                    VALUES (?, ?, ?, ?, 'wfh_staff', 'active')
                ");
                $ins->execute([$s['username'], $hash, $s['firstname'], $s['lastname']]);
                $userId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare("UPDATE att_teachers SET llw_user_id = ? WHERE id = ?")
                ->execute([$userId, $s['att_id']]);
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['wiseat', 'samruay'] as $u) {
            $row = $pdo->prepare("SELECT user_id FROM llw_users WHERE username = ?");
            $row->execute([$u]);
            $uid = $row->fetchColumn();
            if ($uid) {
                $pdo->prepare("UPDATE att_teachers SET llw_user_id = NULL WHERE llw_user_id = ?")
                    ->execute([$uid]);
                $pdo->prepare("DELETE FROM llw_users WHERE user_id = ?")->execute([$uid]);
            }
        }
    },
];
