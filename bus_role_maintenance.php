<?php
/**
 * fix_bus_roles.php
 * Ensure llw_users table supports bus_admin and bus_finance roles.
 * Activate any inactive users if requested.
 */
session_start();
require_once 'config.php';

// Auth guard: Only super_admin can run this
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    die("Access denied. Only super_admin can run this script.");
}

$pdo = getPdo();
$msg = [];

try {
    // 1. Update role ENUM
    // Note: We include all current roles to be safe
    $sql = "ALTER TABLE llw_users MODIFY COLUMN role ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher','bus_admin','bus_finance') NOT NULL";
    $pdo->exec($sql);
    $msg[] = "SUCCESS: Updated llw_users.role ENUM to include bus roles.";

    // 2. Check for users with the new roles (informational)
    $stmt = $pdo->query("SELECT username, role, status FROM llw_users WHERE role IN ('bus_admin', 'bus_finance')");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($users) {
        $msg[] = "INFO: Found " . count($users) . " bus-related users.";
        foreach ($users as $u) {
            $msg[] = " - {$u['username']} ({$u['role']}) - Status: {$u['status']}";
        }
    } else {
        $msg[] = "WARNING: No users with bus_admin or bus_finance role found yet.";
    }

    // 3. (Optional) Activate all users if they are inactive
    if (isset($_GET['activate_all']) && $_GET['activate_all'] === 'yes') {
        $count = $pdo->exec("UPDATE llw_users SET status = 'active' WHERE status = 'inactive'");
        $msg[] = "SUCCESS: Activated $count inactive users.";
    }

} catch (Exception $e) {
    $msg[] = "ERROR: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>LLW System Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container bg-white p-4 rounded shadow-sm">
        <h3>LLW System Database Fix</h3>
        <hr>
        <div class="list-group">
            <?php foreach ($msg as $m): ?>
                <div class="list-group-item"><?= htmlspecialchars($m) ?></div>
            <?php endforeach; ?>
        </div>
        <hr>
        <div class="mt-3">
            <a href="index.php" class="btn btn-primary">Back to Portal</a>
            <a href="?activate_all=yes" class="btn btn-warning" onclick="return confirm('Activate all inactive users?')">Activate All Users</a>
        </div>
    </div>
</body>
</html>
