<?php
require_once 'config/database.php';

try {
    $pdo = getPdo();
    echo "<h1>Database Hub Debug</h1>";
    echo "<p>Connected to: " . DB_NAME . "</p>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables:</h3><ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

    if (in_array('llw_users', $tables)) {
        echo "<h3>Users in llw_users:</h3>";
        $users = $pdo->query("SELECT user_id, username, role, status FROM llw_users")->fetchAll();
        echo "<pre>" . print_r($users, true) . "</pre>";
    } else {
        echo "<p style='color:red;'>Table 'llw_users' not found!</p>";
    }

} catch (Exception $e) {
    echo "<h1 style='color:red;'>Error: " . $e->getMessage() . "</h1>";
}
?>
