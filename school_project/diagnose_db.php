<?php
require_once 'config/db.php';
$db = getDB();
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
$q = $db->query("SHOW COLUMNS FROM llw_users");
while($r = $q->fetch()) {
    echo "<tr><td>{$r['Field']}</td><td>{$r['Type']}</td><td>{$r['Null']}</td><td>{$r['Default']}</td></tr>";
}
echo "</table>";
