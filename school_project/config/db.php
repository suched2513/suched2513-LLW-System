<?php
$parentConfig = __DIR__ . '/../../config/database.php';
if (file_exists($parentConfig) && !function_exists('getPdo')) {
    require_once $parentConfig;
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (function_exists('getPdo')) {
        $pdo = getPdo();
        return $pdo;
    }
    return $pdo;
}
