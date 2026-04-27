<?php
/**
 * index.php — Main Redirector
 */
session_start();
require_once __DIR__ . '/config/auth.php';

checkLogin();

$role = $_SESSION['role'] ?? '';

if ($role === 'admin') {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
} elseif ($role === 'teacher') {
    header('Location: ' . BASE_URL . '/teacher/dashboard.php');
} elseif ($role === 'director') {
    header('Location: ' . BASE_URL . '/director/pending.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit();
