<?php
return [
    'up' => function (PDO $pdo) {
        // Extend role ENUM in llw_users to support e-document roles
        try {
            $pdo->exec("ALTER TABLE llw_users MODIFY COLUMN role 
                ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher','edoc_admin') 
                NOT NULL DEFAULT 'wfh_staff'");
        } catch (PDOException $e) {
            error_log('[edoc migration] role ENUM alter: ' . $e->getMessage());
        }

        // 1. Incoming Documents
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_incoming_documents (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            doc_number   VARCHAR(100) NOT NULL,
            doc_date     DATE NOT NULL,
            subject      VARCHAR(255) NOT NULL,
            status       ENUM('ประกาศใช้แล้ว', 'รออนุมัติ', 'ยกเลิก') NOT NULL DEFAULT 'รออนุมัติ',
            school_id    VARCHAR(50) DEFAULT NULL,
            year_be      INT NOT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by   INT NOT NULL,
            INDEX idx_doc_number (doc_number),
            INDEX idx_year_be (year_be),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Outgoing Documents
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_outgoing_documents (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            doc_number   VARCHAR(100) NOT NULL,
            doc_date     DATE NOT NULL,
            subject      VARCHAR(255) NOT NULL,
            status       ENUM('ประกาศใช้แล้ว', 'รออนุมัติ', 'ยกเลิก') NOT NULL DEFAULT 'รออนุมัติ',
            school_id    VARCHAR(50) DEFAULT NULL,
            year_be      INT NOT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by   INT NOT NULL,
            INDEX idx_doc_number (doc_number),
            INDEX idx_year_be (year_be),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Orders
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_orders (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            doc_number   VARCHAR(100) NOT NULL,
            doc_date     DATE NOT NULL,
            subject      VARCHAR(255) NOT NULL,
            status       ENUM('ประกาศใช้แล้ว', 'รออนุมัติ', 'ยกเลิก') NOT NULL DEFAULT 'รออนุมัติ',
            school_id    VARCHAR(50) DEFAULT NULL,
            year_be      INT NOT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by   INT NOT NULL,
            INDEX idx_doc_number (doc_number),
            INDEX idx_year_be (year_be),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. Memos
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_memos (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            doc_number   VARCHAR(100) NOT NULL,
            doc_date     DATE NOT NULL,
            subject      VARCHAR(255) NOT NULL,
            status       ENUM('ประกาศใช้แล้ว', 'รออนุมัติ', 'ยกเลิก') NOT NULL DEFAULT 'รออนุมัติ',
            school_id    VARCHAR(50) DEFAULT NULL,
            year_be      INT NOT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by   INT NOT NULL,
            INDEX idx_doc_number (doc_number),
            INDEX idx_year_be (year_be),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Attachments
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_attachments (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            ref_table    VARCHAR(50) NOT NULL,
            ref_id       INT NOT NULL,
            file_path    VARCHAR(255) NOT NULL,
            file_name    VARCHAR(255) NOT NULL,
            file_type    VARCHAR(50) NOT NULL,
            file_size    BIGINT NOT NULL,
            sort_order   INT DEFAULT 0,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ref (ref_table, ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Document Links
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_document_links (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            ref_table    VARCHAR(50) NOT NULL,
            ref_id       INT NOT NULL,
            url          TEXT NOT NULL,
            label        VARCHAR(255) DEFAULT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ref (ref_table, ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. Document Involved Users
        $pdo->exec("CREATE TABLE IF NOT EXISTS edoc_involved_users (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            ref_table       VARCHAR(50) NOT NULL,
            ref_id          INT NOT NULL,
            user_id         INT NOT NULL,
            acknowledged_at DATETIME DEFAULT NULL,
            assigned_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            assigner_id     INT NOT NULL,
            INDEX idx_ref (ref_table, ref_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS edoc_involved_users");
        $pdo->exec("DROP TABLE IF EXISTS edoc_document_links");
        $pdo->exec("DROP TABLE IF EXISTS edoc_attachments");
        $pdo->exec("DROP TABLE IF EXISTS edoc_memos");
        $pdo->exec("DROP TABLE IF EXISTS edoc_orders");
        $pdo->exec("DROP TABLE IF EXISTS edoc_outgoing_documents");
        $pdo->exec("DROP TABLE IF EXISTS edoc_incoming_documents");
    },
];
