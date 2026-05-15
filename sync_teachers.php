<?php
require_once __DIR__ . '/config.php';
$pdo = getPdo();

$users = $pdo->query("SELECT * FROM llw_users")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;
foreach ($users as $user) {
    // Roles that can be teachers or club advisors
    if (in_array($user['role'], ['att_teacher', 'club_admin', 'super_admin', 'wfh_admin', 'wfh_staff', 'finance_head', 'procurement_head', 'deputy_director', 'director'])) {
        $chk = $pdo->prepare("SELECT id FROM att_teachers WHERE llw_user_id = ?");
        $chk->execute([$user['user_id']]);
        if (!$chk->fetch()) {
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            $ins = $pdo->prepare("INSERT INTO att_teachers (name, username, password, llw_user_id) VALUES (?, ?, ?, ?)");
            $ins->execute([$name, $user['username'], $user['password'], $user['user_id']]);
            $count++;
            echo "Added: $name\n";
        } else {
            // Update name just in case it was changed
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            $upd = $pdo->prepare("UPDATE att_teachers SET name = ?, username = ? WHERE llw_user_id = ?");
            $upd->execute([$name, $user['username'], $user['user_id']]);
        }
    }
}
echo "Synced $count missing teachers.\n";
