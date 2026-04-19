<?php
/**
 * attendance_system/index.php — Gateway to Attendance Dashboard
 * Redirects to Unified Login if not authenticated.
 */
require_once 'functions.php';

// หากล็อกอินอยู่แล้ว (ทั้งแบบเดิมหรือแบบใหม่) ให้ไป Dashboard
if (isset($_SESSION['llw_role']) || isset($_SESSION['teacher_id'])) {
    header("Location: dashboard.php");
    exit();
}

// ถ้ายังไม่ได้ล็อกอิน ให้ไปที่หน้าล็อกอินกลาง
global $base_path;
header("Location: " . $base_path . "/login.php?redirect=" . urlencode("/attendance_system/dashboard.php"));
exit();
