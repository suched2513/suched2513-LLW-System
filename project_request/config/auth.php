<?php
/**
 * Authentication & Authorization Helpers
 */

require_once __DIR__ . '/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

/**
 * Check if user has required role
 * @param array|string $allowedRoles
 */
function checkRole($allowedRoles) {
    checkLogin();
    
    $userRole = $_SESSION['role'] ?? '';
    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    
    if (!in_array($userRole, $allowed)) {
        // Use a unified redirector
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
        $target = $map[$userRole] ?? '/login.php';
        header('Location: ' . BASE_URL . $target);
        exit();
    }
}

/**
 * Get current user data from session
 */
function currentUser() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
        'dept'      => $_SESSION['department'] ?? ''
    ];
}
