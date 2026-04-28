<?php
require_once 'config/db.php';
$db = getDB();
// Update Chonlada (llw14) to wfh_admin (budget_officer)
$s = $db->prepare("UPDATE llw_users SET role='wfh_admin' WHERE username='llw14'");
$s->execute();
echo "Updated Chonlada to wfh_admin successfully.";
