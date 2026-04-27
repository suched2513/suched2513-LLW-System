<?php
/**
 * index.php — Main Redirector
 */
session_start();
require_once __DIR__ . '/config/auth.php';

checkLogin();

$role = $_SESSION['role'] ?? '';

$map = [
    'admin'          => '/admin/dashboard.php',
    'super_admin'    => '/admin/dashboard.php',
    'wfh_admin'      => '/admin/dashboard.php',
    'teacher'        => '/teacher/dashboard.php',
    'att_teacher'    => '/teacher/dashboard.php',
    'wfh_staff'      => '/teacher/dashboard.php',
    'director'       => '/dashboard/director.php',
    'budget_officer' => '/dashboard/budget_officer.php',
];

$target = $map[$role] ?? null;

if ($target) {
    header('Location: ' . BASE_URL . $target);
} else {
    // If logged in but role not recognized, go to login with error
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?error=unauthorized_role');
}
exit();
