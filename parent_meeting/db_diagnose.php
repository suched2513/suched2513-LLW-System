<?php
require_once __DIR__ . '/config.php';

// Simple security check
if (($_GET['token'] ?? '') !== 'llw_secure_diag_123') {
    die('Unauthorized');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPmPdo();
    echo "Connected successfully to: " . PM_DB_NAME . "\n\n";
    
    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    foreach ($tables as $t) {
        echo " - $t\n";
    }
    echo "\n";
    
    // Describe users table
    if (in_array('users', $tables)) {
        echo "Columns in 'users' table:\n";
        $cols = $pdo->query("DESCRIBE users")->fetchAll();
        foreach ($cols as $c) {
            echo " - {$c['Field']} ({$c['Type']})\n";
        }
        echo "\n";
        
        // Count users
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Total users count: $count\n\n";
        
        // Show usernames (first 10)
        echo "Usernames in 'users' table:\n";
        $users = $pdo->query("SELECT id, fullname, username, role FROM users LIMIT 10")->fetchAll();
        foreach ($users as $u) {
            echo " - ID: {$u['id']}, Name: {$u['fullname']}, Username: {$u['username']}, Role: {$u['role']}\n";
        }
    } else {
        echo "'users' table does not exist!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
