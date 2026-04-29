<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/constants.php';
requireLogin();
$role = $_SESSION['llw_role'] ?? $_SESSION['role'] ?? '';
$redirects = [
    'admin'            => '/admin/dashboard.php',
    'super_admin'      => '/admin/dashboard.php',
    'director'         => '/dashboard/director.php',
    'budget_officer'   => '/dashboard/budget_officer.php',
    'wfh_admin'        => '/dashboard/budget_officer.php',
    'procurement_head' => '/director/pending.php',
    'finance_head'     => '/director/pending.php',
    'deputy_director'  => '/director/pending.php',
    'teacher'          => '/teacher/my_projects.php',
    'att_teacher'      => '/teacher/my_projects.php',
    'wfh_staff'        => '/teacher/my_projects.php',
    'cb_admin'         => '/teacher/my_projects.php'
];
$target = $redirects[$role] ?? null;
if (!$target) {
    // If role is unknown but user is logged in, try to find their role from DB
    $db = getDB();
    $s = $db->prepare("SELECT role FROM llw_users WHERE user_id = ?");
    $s->execute([$_SESSION['user_id']]);
    $u = $s->fetch();
    if ($u) {
        $_SESSION['llw_role'] = $u['role'];
        $target = $redirects[$u['role']] ?? '/login.php';
    } else {
        $target = '/login.php';
    }
}
header('Location: ' . BASE_URL . $target);
exit;
