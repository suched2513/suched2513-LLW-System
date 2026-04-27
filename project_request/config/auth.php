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
        // Redirect to their respective dashboard or login
        if ($userRole === 'admin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        } elseif ($userRole === 'teacher') {
            header('Location: ' . BASE_URL . '/teacher/dashboard.php');
        } elseif ($userRole === 'director') {
            header('Location: ' . BASE_URL . '/dashboard/director.php');
        } elseif ($userRole === 'budget_officer') {
            header('Location: ' . BASE_URL . '/dashboard/budget_officer.php');
        } else {
            header('Location: ' . BASE_URL . '/login.php');
        }
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
