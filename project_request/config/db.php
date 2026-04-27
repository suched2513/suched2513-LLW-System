<?php
/**
 * Database Connection using PDO
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'llw_budget'); // Change as needed
define('DB_USER', 'root');       // Change as needed
define('DB_PASS', '');           // Change as needed

function getPdo() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("ขออภัย เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
        }
    }
    return $pdo;
}
