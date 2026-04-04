<?php
// config/db_project.php
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

$db_project_name = 'exit_permit_db';

try {
    $pdo_project = new PDO("mysql:host=" . DB_HOST . ";dbname=$db_project_name;charset=utf8mb4", DB_USER, DB_PASS);
    $pdo_project->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_project->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed (Project): " . $e->getMessage());
}

// Telegram Config (Placeholder)
if (!defined('TELEGRAM_TOKEN')) define('TELEGRAM_TOKEN', 'YOUR_BOT_TOKEN_HERE');
if (!defined('BOSS1_CHAT_ID')) define('BOSS1_CHAT_ID', 'BOSS1_TELEGRAM_ID_HERE'); // ผอ.
?>
