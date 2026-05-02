<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_transport (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                att_student_id INT NOT NULL,
                semester       VARCHAR(20) NOT NULL,
                transport_type ENUM('school_bus','motorcycle','bicycle','walk','private_car','other') NOT NULL,
                route_id       INT NULL,
                home_village   VARCHAR(200) NULL,
                note           VARCHAR(500) NULL,
                status         ENUM('submitted','confirmed') NOT NULL DEFAULT 'submitted',
                confirmed_by   INT NULL,
                confirmed_at   DATETIME NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_semester (att_student_id, semester),
                INDEX idx_type (transport_type),
                INDEX idx_route (route_id),
                INDEX idx_semester (semester)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS student_transport");
    },
];
