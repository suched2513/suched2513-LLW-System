<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_exercise_classrooms (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                exercise_id INT NOT NULL,
                classroom   VARCHAR(100) NOT NULL,
                UNIQUE KEY uq_ex_class (exercise_id, classroom),
                INDEX idx_exercise (exercise_id),
                INDEX idx_classroom (classroom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS lms_exercise_classrooms");
    },
];
