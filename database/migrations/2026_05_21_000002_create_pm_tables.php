<?php
return [
    'up' => function (PDO $pdo) {
        // 1. pm_users
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(150) NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'executive', 'teacher') NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. pm_classrooms
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_classrooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(50) NOT NULL,
            room_name VARCHAR(50) NOT NULL,
            teacher_name VARCHAR(150) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed classrooms if empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM pm_classrooms");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO pm_classrooms (level, room_name, teacher_name) VALUES 
            ('ม.1', '1', 'สมชาย ใจดี'),
            ('ม.1', '2', 'สมศรี รักษ์ดี'),
            ('ม.2', '1', 'ประยุทธ์ สู้ๆ'),
            ('ม.2', '2', 'ประวิตร วงษ์สวย'),
            ('ม.3', '1', 'อนุทิน กัญชาดี'),
            ('ม.3', '2', 'พิธา ก้าวหน้า')");
        }

        // Seed users if empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM pm_users");
        if ($stmt->fetchColumn() == 0) {
            $users = [
                ['fullname' => 'แอดมิน ระบบ', 'username' => 'admin_user', 'password' => password_hash('admin1234', PASSWORD_DEFAULT), 'role' => 'admin'],
                ['fullname' => 'ผู้อำนวยการ สมศักดิ์', 'username' => 'director_user', 'password' => password_hash('director1234', PASSWORD_DEFAULT), 'role' => 'executive'],
                ['fullname' => 'สมชาย ใจดี', 'username' => 'teacher_user', 'password' => password_hash('teacher1234', PASSWORD_DEFAULT), 'role' => 'teacher']
            ];
            $insert = $pdo->prepare("INSERT INTO pm_users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
            foreach ($users as $u) {
                $insert->execute([$u['fullname'], $u['username'], $u['password'], $u['role']]);
            }
        }

        // 3. pm_meetings
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meetings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_date DATE NOT NULL,
            semester VARCHAR(10) NOT NULL,
            academic_year INT NOT NULL,
            classroom_id INT NOT NULL,
            total_students INT NOT NULL,
            total_parents INT NOT NULL,
            attend_count INT NOT NULL,
            absent_count INT NOT NULL,
            summary TEXT NULL,
            problems TEXT NULL,
            suggestions TEXT NULL,
            doc_no VARCHAR(100) NULL,
            doc_date DATE NULL,
            command_no VARCHAR(100) NULL,
            command_date DATE NULL,
            agenda_1 TEXT NULL,
            agenda_2 TEXT NULL,
            agenda_3 TEXT NULL,
            consensus TEXT NULL,
            cooperation_rating TEXT NULL,
            useful_suggestions TEXT NULL,
            support_received TEXT NULL,
            other_observations TEXT NULL,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (classroom_id) REFERENCES pm_classrooms(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES pm_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. pm_meeting_attendants
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_attendants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            parent_name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            relationship VARCHAR(100) NOT NULL,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. pm_meeting_absents
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_absents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            parent_name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            relationship VARCHAR(100) NOT NULL,
            absent_reason VARCHAR(255) NULL,
            follow_up_status VARCHAR(255) NULL,
            follow_up_date DATE NULL,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. pm_student_relations
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_student_relations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            classroom_no VARCHAR(20) NOT NULL,
            student_no INT NOT NULL,
            parent_name VARCHAR(150) NOT NULL,
            relationship VARCHAR(100) NOT NULL,
            grade_zero_count INT DEFAULT 0,
            grade_r_count INT DEFAULT 0,
            grade_ms_count INT DEFAULT 0,
            grade_mp_count INT DEFAULT 0,
            behavior_score_deducted INT DEFAULT 0,
            praise_teacher_json TEXT NULL,
            praise_teacher_other VARCHAR(255) NULL,
            praise_parent_json TEXT NULL,
            praise_parent_other VARCHAR(255) NULL,
            improve_teacher_json TEXT NULL,
            improve_teacher_other VARCHAR(255) NULL,
            improve_parent_json TEXT NULL,
            improve_parent_other VARCHAR(255) NULL,
            teacher_remedy TEXT NULL,
            parent_remedy TEXT NULL,
            parent_support_request TEXT NULL,
            parent_meeting_impression TEXT NULL,
            parent_teacher_feedback TEXT NULL,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. pm_meet_greet_groups
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meet_greet_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            group_topic VARCHAR(255) NOT NULL,
            attendants_json TEXT NULL,
            discussion_summary TEXT NULL,
            discussion_resolution TEXT NULL,
            school_support_request TEXT NULL,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 8. pm_student_letters
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_student_letters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            classroom_no VARCHAR(20) NOT NULL,
            student_no INT NOT NULL,
            letter_to_whom VARCHAR(150) NOT NULL,
            impressed_story TEXT NULL,
            inner_feelings TEXT NULL,
            proud_story TEXT NULL,
            improvement_plan TEXT NULL,
            parent_response TEXT NULL,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 9. pm_network_parents
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_network_parents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            position_name ENUM('ประธาน', 'รองประธาน', 'กรรมการ', 'เลขานุการ') NOT NULL,
            parent_name VARCHAR(150) NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            student_class VARCHAR(50) NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 10. pm_meeting_images
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 11. pm_comments
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            comment_text TEXT NOT NULL,
            commented_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE,
            FOREIGN KEY (commented_by) REFERENCES pm_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS pm_comments");
        $pdo->exec("DROP TABLE IF EXISTS pm_meeting_images");
        $pdo->exec("DROP TABLE IF EXISTS pm_network_parents");
        $pdo->exec("DROP TABLE IF EXISTS pm_student_letters");
        $pdo->exec("DROP TABLE IF EXISTS pm_meet_greet_groups");
        $pdo->exec("DROP TABLE IF EXISTS pm_student_relations");
        $pdo->exec("DROP TABLE IF EXISTS pm_meeting_absents");
        $pdo->exec("DROP TABLE IF EXISTS pm_meeting_attendants");
        $pdo->exec("DROP TABLE IF EXISTS pm_meetings");
        $pdo->exec("DROP TABLE IF EXISTS pm_classrooms");
        $pdo->exec("DROP TABLE IF EXISTS pm_users");
    }
];
