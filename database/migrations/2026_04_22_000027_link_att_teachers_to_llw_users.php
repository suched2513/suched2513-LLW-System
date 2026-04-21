<?php
/**
 * Migration: link_att_teachers_to_llw_users
 * Created: 2026-04-22
 *
 * สร้าง llw_users account สำหรับครูทุกคนใน att_teachers ที่ยังไม่มี llw_user_id
 * เพื่อให้ manage_advisors.php แสดงครูในดรอปดาวน์ได้
 */
return [
    'up' => function (PDO $pdo) {

        // ดึงครูที่ยังไม่มี llw_user_id
        $teachers = $pdo->query("
            SELECT id, name, username, password
            FROM att_teachers
            WHERE llw_user_id IS NULL OR llw_user_id = 0
              AND username IS NOT NULL AND TRIM(username) != ''
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($teachers)) {
            echo "<p>✅ ครูทุกคนมี llw_users account แล้ว</p>";
            return true;
        }

        $stmtCheck  = $pdo->prepare("SELECT user_id FROM llw_users WHERE username = ?");
        $stmtInsert = $pdo->prepare("
            INSERT INTO llw_users (username, password, firstname, lastname, role, status)
            VALUES (?, ?, ?, '', 'att_teacher', 'active')
        ");
        $stmtLink = $pdo->prepare("
            UPDATE att_teachers SET llw_user_id = ? WHERE id = ?
        ");

        $created = 0; $linked = 0;

        foreach ($teachers as $t) {
            $username = trim($t['username']);
            $name     = trim($t['name']);
            $password = !empty($t['password']) ? $t['password'] : password_hash(uniqid(), PASSWORD_DEFAULT);

            // ตรวจว่ามี account อยู่แล้วไหม
            $stmtCheck->execute([$username]);
            $existing = $stmtCheck->fetchColumn();

            if ($existing) {
                // มีอยู่แล้ว — แค่ link
                $stmtLink->execute([$existing, $t['id']]);
                $linked++;
            } else {
                // สร้างใหม่
                $stmtInsert->execute([$username, $password, $name]);
                $newUserId = (int)$pdo->lastInsertId();
                $stmtLink->execute([$newUserId, $t['id']]);
                $created++;
            }
        }

        echo "<p>✅ สร้าง llw_users ใหม่: {$created} account</p>";
        echo "<p>✅ เชื่อม account เดิม: {$linked} คน</p>";

        return true;
    },

    'down' => function (PDO $pdo) {
        // ไม่ revert — ป้องกัน data loss
        return true;
    },
];
