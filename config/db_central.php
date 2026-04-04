<?php
// config/db_central.php
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

$db_central_name = 'school_central_db';

try {
    $pdo_central = new PDO("mysql:host=" . DB_HOST . ";dbname=$db_central_name;charset=utf8mb4", DB_USER, DB_PASS);
    $pdo_central->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_central->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed (Central): " . $e->getMessage());
}
?>
