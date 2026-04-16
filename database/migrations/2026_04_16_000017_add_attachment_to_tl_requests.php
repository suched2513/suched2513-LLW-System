<?php
/**
 * Migration: Add attachment_path to tl_requests
 * Created: 2026-04-16
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            ALTER TABLE tl_requests 
            ADD COLUMN attachment_path VARCHAR(500) NULL AFTER signature_path
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("
            ALTER TABLE tl_requests 
            DROP COLUMN attachment_path
        ");
    },
];
