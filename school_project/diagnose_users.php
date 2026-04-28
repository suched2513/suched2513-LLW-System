<?php
require_once 'config/db.php';
$db = getDB();
$users = $db->query("SELECT user_id, username, firstname, role FROM llw_users")->fetchAll();
echo "<table border='1'><tr><th>ID</th><th>User</th><th>Name</th><th>Role</th></tr>";
foreach($users as $u) {
    echo "<tr><td>{$u['user_id']}</td><td>{$u['username']}</td><td>{$u['firstname']}</td><td>{$u['role']}</td></tr>";
}
echo "</table>";
