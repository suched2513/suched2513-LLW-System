<?php
/**
 * Populate att_students.national_id_hash so student portal login works.
 *
 * Step 1: sync real NID hashes from bus_students (student_id match, handle 0-padding)
 * Step 2: for any students STILL missing NID, set temp hash = bcrypt('0000000000000')
 *         → students can immediately log in with 0000000000000 as their password
 *         → national_id_masked is set to '[ชั่วคราว]' so admin knows which to replace
 *
 * เหตุผล: att_students เป็น single auth source สำหรับ student portal (/student/login.php)
 *         และ bus portal (/bus/index.php) แต่คอลัมน์ national_id_hash ยังว่างเปล่า
 */
return [
    'up' => function (PDO $pdo) {
        $before = (int)$pdo->query("SELECT COUNT(*) FROM att_students WHERE national_id_hash IS NOT NULL")->fetchColumn();
        $total  = (int)$pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();

        // ── Step 1: sync from bus_students ──────────────────────────────
        $synced = $pdo->exec("
            UPDATE att_students a
            JOIN bus_students b
                ON  b.student_id = a.student_id
                 OR b.student_id = LPAD(a.student_id, 5, '0')
                 OR LPAD(b.student_id, 5, '0') = a.student_id
            SET a.national_id_hash   = b.national_id_hash,
                a.national_id_masked = b.national_id_masked
            WHERE b.national_id_hash IS NOT NULL
              AND a.national_id_hash IS NULL
        ");
        echo "Step 1 — ซิงค์จาก bus_students: {$synced} คน\n";

        // ── Step 2: set temp password for remaining students ─────────────
        $tempHash   = password_hash('0000000000000', PASSWORD_BCRYPT);
        $tempMasked = '[ชั่วคราว]';

        $stmt = $pdo->prepare("
            UPDATE att_students
            SET national_id_hash   = ?,
                national_id_masked = ?
            WHERE national_id_hash IS NULL
        ");
        $stmt->execute([$tempHash, $tempMasked]);
        $remaining = $stmt->rowCount();
        echo "Step 2 — ตั้ง temp password (0000000000000) ให้: {$remaining} คน\n";

        $after = (int)$pdo->query("SELECT COUNT(*) FROM att_students WHERE national_id_hash IS NOT NULL")->fetchColumn();
        echo "\n✅ สรุป: {$after}/{$total} คน พร้อม login แล้ว\n";

        if ($synced > 0) {
            echo "   · {$synced} คน ใช้เลขบัตรจริง (จาก bus_students)\n";
        }
        if ($remaining > 0) {
            echo "   · {$remaining} คน ใช้รหัสผ่านชั่วคราว: 0000000000000\n";
            echo "   ▸ อัพเดทเลขบัตรจริงได้ที่ /student/admin/manage_nid.php\n";
            echo "     (filter: status=missing เพื่อดูเฉพาะที่ยังใช้รหัสชั่วคราว)\n";
        }
    },

    'down' => function (PDO $pdo) {
        // Clear only temp records — leave real NID hashes untouched
        $pdo->exec("UPDATE att_students SET national_id_hash = NULL, national_id_masked = NULL WHERE national_id_masked = '[ชั่วคราว]'");
    },
];
