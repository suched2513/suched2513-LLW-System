<?php
/**
 * attendance_system/logout.php — Redirect to Central Logout
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
header("Location: " . $base_path . "/logout.php");
exit();
