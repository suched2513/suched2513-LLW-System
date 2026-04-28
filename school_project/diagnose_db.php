<?php
require_once 'config/db.php';
$db = getDB();
echo "<pre>";
$q = $db->query("DESC llw_users");
while($r = $q->fetch()) {
    echo $r['Field'] . " - " . $r['Type'] . "\n";
}
echo "</pre>";
