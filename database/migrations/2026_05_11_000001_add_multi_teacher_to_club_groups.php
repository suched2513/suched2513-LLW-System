<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM club_groups LIKE 'teacher_id_2'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE club_groups ADD COLUMN teacher_id_2 INT NULL AFTER teacher_id");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM club_groups LIKE 'teacher_id_3'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE club_groups ADD COLUMN teacher_id_3 INT NULL AFTER teacher_id_2");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM club_groups LIKE 'teacher_id_3'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE club_groups DROP COLUMN teacher_id_3");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM club_groups LIKE 'teacher_id_2'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE club_groups DROP COLUMN teacher_id_2");
        }
    },
];
