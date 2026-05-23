<?php
return [
    'up' => function (PDO $pdo) {
        // Only run if old column name still exists
        $cols = $pdo->query("SHOW COLUMNS FROM lms_student_exercises LIKE 'file_path'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE lms_student_exercises CHANGE COLUMN file_path file_paths TEXT NULL DEFAULT NULL");
            // Wrap existing single-path strings in a JSON array
            $rows = $pdo->query("SELECT id, file_paths FROM lms_student_exercises WHERE file_paths IS NOT NULL")->fetchAll();
            $stmt = $pdo->prepare("UPDATE lms_student_exercises SET file_paths=? WHERE id=?");
            foreach ($rows as $row) {
                if (substr(trim($row['file_paths']), 0, 1) !== '[') {
                    $stmt->execute([json_encode([$row['file_paths']]), $row['id']]);
                }
            }
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM lms_student_exercises LIKE 'file_paths'")->fetchAll();
        if (!empty($cols)) {
            // Unwrap first path from JSON array
            $rows = $pdo->query("SELECT id, file_paths FROM lms_student_exercises WHERE file_paths IS NOT NULL")->fetchAll();
            $stmt = $pdo->prepare("UPDATE lms_student_exercises SET file_paths=? WHERE id=?");
            foreach ($rows as $row) {
                $arr = json_decode($row['file_paths'], true);
                if (is_array($arr) && !empty($arr)) {
                    $stmt->execute([substr($arr[0], 0, 255), $row['id']]);
                }
            }
            $pdo->exec("ALTER TABLE lms_student_exercises CHANGE COLUMN file_paths file_path VARCHAR(255) NULL DEFAULT NULL");
        }
    },
];
