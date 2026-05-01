<?php
require_once __DIR__ . '/config.php';
$pdo = getPdo();

header('Content-Type: text/html; charset=utf-8');
echo "<h1>System Authentication Analysis</h1>";

// 1. Check Table Structure
echo "<h3>llw_users Structure</h3>";
try {
    $stmt = $pdo->query("DESCRIBE llw_users");
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach($stmt->fetchAll() as $row) {
        echo "<tr>";
        foreach($row as $v) echo "<td>$v</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// 2. Check User Counts by Role
echo "<h3>User Counts by Role</h3>";
try {
    $stmt = $pdo->query("SELECT role, status, COUNT(*) as count FROM llw_users GROUP BY role, status");
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Role</th><th>Status</th><th>Count</th></tr>";
    foreach($stmt->fetchAll() as $row) {
        echo "<tr><td>{$row['role']}</td><td>{$row['status']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// 3. Check for specific bus users
echo "<h3>Bus System Users</h3>";
try {
    $stmt = $pdo->query("SELECT user_id, username, role, status, last_login FROM llw_users WHERE role LIKE 'bus%' OR role = ''");
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th></tr>";
    foreach($stmt->fetchAll() as $row) {
        echo "<tr><td>{$row['user_id']}</td><td>{$row['username']}</td><td>'{$row['role']}'</td><td>{$row['status']}</td><td>{$row['last_login']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// 3.5 Sample Users
echo "<h3>Sample Users (First 20)</h3>";
try {
    $stmt = $pdo->query("SELECT username, role, status FROM llw_users LIMIT 20");
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Username</th><th>Role</th><th>Status</th></tr>";
    foreach($stmt->fetchAll() as $row) {
        echo "<tr><td>[{$row['username']}]</td><td>{$row['role']}</td><td>{$row['status']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// 4. Check migrations table
echo "<h3>Migration History</h3>";
try {
    $stmt = $pdo->query("SELECT migration, executed_at FROM _migrations ORDER BY executed_at DESC");
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Migration</th><th>Executed At</th></tr>";
    foreach($stmt->fetchAll() as $row) {
        echo "<tr><td>{$row['migration']}</td><td>{$row['executed_at']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Migrations table error: " . $e->getMessage();
}
