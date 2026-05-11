<?php
/**
 * Migration: Reset att_teacher passwords + activate accounts
 * เหตุผล: migration 22 (import_teachers_from_gsheet) ล้มเหลวกลางทาง
 * บางบัญชีครูไม่มี password ที่ถูกต้อง หรือ status = inactive
 * แก้ไขโดย: set password = 'llw1234' และ status = 'active' สำหรับทุก att_teacher
 */
return [
    'up' => function (PDO $pdo) {
        $newPassword = password_hash('llw1234', PASSWORD_DEFAULT);

        // 1. สร้าง/อัปเดต llw_users สำหรับครูทุกคนใน att_teachers
        $teachers = $pdo->query("
            SELECT t.id, t.name, t.username, t.llw_user_id
            FROM att_teachers t
            WHERE t.username IS NOT NULL AND TRIM(t.username) != ''
        ")->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0; $created = 0;

        foreach ($teachers as $t) {
            $username = trim($t['username']);

            if ($t['llw_user_id'] > 0) {
                // มีบัญชีอยู่แล้ว — reset password + activate
                $pdo->prepare("
                    UPDATE llw_users
                    SET password = ?, status = 'active', force_password_change = 0
                    WHERE user_id = ?
                ")->execute([$newPassword, $t['llw_user_id']]);
                $updated++;
            } else {
                // ยังไม่มีบัญชี — สร้างใหม่
                $nameParts = explode(' ', trim($t['name']), 2);
                $firstname = $nameParts[0] ?? $t['name'];
                $lastname  = $nameParts[1] ?? '';

                // ตรวจว่า username ซ้ำไหม
                $exists = $pdo->prepare("SELECT user_id FROM llw_users WHERE username = ?");
                $exists->execute([$username]);
                $row = $exists->fetch();

                if ($row) {
                    // username ซ้ำ — reset password + activate + link
                    $pdo->prepare("
                        UPDATE llw_users
                        SET password = ?, status = 'active', force_password_change = 0, role = 'att_teacher'
                        WHERE user_id = ?
                    ")->execute([$newPassword, $row['user_id']]);
                    $pdo->prepare("UPDATE att_teachers SET llw_user_id = ? WHERE id = ?")
                        ->execute([$row['user_id'], $t['id']]);
                    $updated++;
                } else {
                    $pdo->prepare("
                        INSERT INTO llw_users (username, password, firstname, lastname, role, status, force_password_change)
                        VALUES (?, ?, ?, ?, 'att_teacher', 'active', 0)
                    ")->execute([$username, $newPassword, $firstname, $lastname]);
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE att_teachers SET llw_user_id = ? WHERE id = ?")
                        ->execute([$newId, $t['id']]);
                    $created++;
                }
            }
        }

        echo "✅ อัปเดต: {$updated} บัญชี | สร้างใหม่: {$created} บัญชี\n";
        echo "🔑 Password ใหม่ทุกบัญชีครู: llw1234\n";
    },

    'down' => function (PDO $pdo) {
        // ไม่ revert — ป้องกัน data loss
    },
];
