<?php
return [
    'up' => function (PDO $pdo) {
        // ── Add unit_id to exam-related tables ──────────────────────
        foreach ([
            'lms_pre_questions', 'lms_post_questions',
            'lms_student_pre_exam', 'lms_student_post_exam',
        ] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'unit_id'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN unit_id INT NULL DEFAULT NULL AFTER subject_id, ADD INDEX idx_unit (unit_id)");
            }
        }

        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'unit_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE lms_exam_settings ADD COLUMN unit_id INT NULL DEFAULT NULL AFTER subject_id");
        }

        // ── New table: subject-wide settings (cross-unit sequencing) ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_subject_settings (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                subject_id  INT NOT NULL,
                unlock_mode ENUM('open_all','sequential') NOT NULL DEFAULT 'open_all',
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_subject (subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migrate unlock_mode out of lms_exam_settings into lms_subject_settings
        $hasUnlockCol = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'unlock_mode'")->fetch();
        if ($hasUnlockCol) {
            $rows = $pdo->query("SELECT DISTINCT subject_id, unlock_mode FROM lms_exam_settings")->fetchAll();
            $ins = $pdo->prepare("INSERT IGNORE INTO lms_subject_settings (subject_id, unlock_mode) VALUES (?,?)");
            foreach ($rows as $r) {
                $ins->execute([$r['subject_id'], $r['unlock_mode'] ?: 'open_all']);
            }
            $pdo->exec("ALTER TABLE lms_exam_settings DROP COLUMN unlock_mode");
        }

        // ── Backfill: assign existing subject-level rows to that subject's first unit ──
        $subjectFirstUnit = $pdo->query("
            SELECT subject_id, MIN(order_no) AS min_order
            FROM lms_units WHERE deleted_at IS NULL GROUP BY subject_id
        ")->fetchAll(PDO::FETCH_ASSOC);
        $firstUnitOf = [];
        foreach ($subjectFirstUnit as $row) {
            $u = $pdo->prepare("SELECT id FROM lms_units WHERE subject_id=? AND order_no=? AND deleted_at IS NULL LIMIT 1");
            $u->execute([$row['subject_id'], $row['min_order']]);
            $firstUnitOf[$row['subject_id']] = (int)$u->fetchColumn();
        }

        foreach (['lms_pre_questions', 'lms_post_questions', 'lms_student_pre_exam', 'lms_student_post_exam', 'lms_exam_settings'] as $tbl) {
            $rows = $pdo->query("SELECT DISTINCT subject_id FROM `{$tbl}` WHERE unit_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            $upd = $pdo->prepare("UPDATE `{$tbl}` SET unit_id=? WHERE subject_id=? AND unit_id IS NULL");
            foreach ($rows as $sid) {
                if (!empty($firstUnitOf[$sid])) {
                    $upd->execute([$firstUnitOf[$sid], $sid]);
                }
            }
        }

        // lms_exam_settings can now have multiple rows per subject (one per unit) —
        // drop the old subject-level unique key and add a unit-level one instead.
        $idx = $pdo->query("SHOW INDEX FROM lms_exam_settings WHERE Key_name='uq_subj_settings'")->fetch();
        if ($idx) {
            $pdo->exec("ALTER TABLE lms_exam_settings DROP INDEX uq_subj_settings");
        }
        $idx2 = $pdo->query("SHOW INDEX FROM lms_exam_settings WHERE Key_name='uq_unit_settings'")->fetch();
        if (!$idx2) {
            $pdo->exec("ALTER TABLE lms_exam_settings ADD UNIQUE KEY uq_unit_settings (unit_id)");
        }
    },
    'down' => function (PDO $pdo) {
        $idx = $pdo->query("SHOW INDEX FROM lms_exam_settings WHERE Key_name='uq_unit_settings'")->fetch();
        if ($idx) $pdo->exec("ALTER TABLE lms_exam_settings DROP INDEX uq_unit_settings");

        $hasUnlockCol = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'unlock_mode'")->fetch();
        if (!$hasUnlockCol) {
            $pdo->exec("ALTER TABLE lms_exam_settings ADD COLUMN unlock_mode ENUM('open_all','sequential') NOT NULL DEFAULT 'open_all'");
            $rows = $pdo->query("SELECT subject_id, unlock_mode FROM lms_subject_settings")->fetchAll();
            $upd = $pdo->prepare("UPDATE lms_exam_settings SET unlock_mode=? WHERE subject_id=?");
            foreach ($rows as $r) $upd->execute([$r['unlock_mode'], $r['subject_id']]);
        }
        $pdo->exec("DROP TABLE IF EXISTS lms_subject_settings");

        foreach ([
            'lms_pre_questions', 'lms_post_questions',
            'lms_student_pre_exam', 'lms_student_post_exam', 'lms_exam_settings',
        ] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'unit_id'")->fetch();
            if ($col) {
                try { $pdo->exec("ALTER TABLE `{$tbl}` DROP INDEX idx_unit"); } catch (Exception $e) {}
                $pdo->exec("ALTER TABLE `{$tbl}` DROP COLUMN unit_id");
            }
        }
        $pdo->exec("ALTER TABLE lms_exam_settings ADD UNIQUE KEY uq_subj_settings (subject_id)");
    },
];
