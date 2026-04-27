<?php
/**
 * project_request/config/db.php — Database Connection using Parent Config
 */

// Include the main LLW database configuration
// Path relative to project_request/config/db.php -> ../../config/database.php
$mainConfig = __DIR__ . '/../../config/database.php';

if (file_exists($mainConfig)) {
    require_once $mainConfig;
} else {
    // Fallback for local development if main config not found
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'llw_db');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');
    
    if (!function_exists('getPdo')) {
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
    }
}

// Ensure the local getPdo() returns the parent's getPdo() if it exists
// and we can override settings here if needed (e.g. if we use a different DB name)
// But according to LLW rules, we use a single DB: llw_db.
