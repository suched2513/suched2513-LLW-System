<?php
require_once __DIR__ . '/config.php';
$pdo = getPdo();

echo "<h2>llw_users Table Structure</h2>";
$stmt = $pdo->query("DESCRIBE llw_users");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";

echo "<h2>Latest 10 Users</h2>";
$stmt = $pdo->query("SELECT user_id, username, role, status, last_login FROM llw_users ORDER BY user_id DESC LIMIT 10");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";

echo "<h2>Users with Bus Roles</h2>";
$stmt = $pdo->query("SELECT user_id, username, role, status FROM llw_users WHERE role LIKE 'bus%'");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";

echo "<h2>Pending Migrations</h2>";
// Assuming there's a migrations table
try {
    $stmt = $pdo->query("SELECT migration FROM migrations");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
    echo "</pre>";
} catch (Exception $e) {
    echo "Migrations table not found or error: " . $e->getMessage();
}
