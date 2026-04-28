<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/constants.php';
requireLogin();
$role = $_SESSION['role'];
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
    'att_teacher'      => '/teacher/my_projects.php'
];
header('Location: ' . ($redirects[$role] ?? '/login.php'));
exit;
