<?php
/**
 * upload_students.php — เปลี่ยนเส้นทางไปยังระบบนำเข้าข้อมูลนักเรียนส่วนกลาง (Hybrid)
 * เพื่อมาตรฐานเดียวในการเข้ารหัสเลขบัตรประชาชน 13 หลักและซิงค์ข้อมูลทุกโมดูล
 */
session_start();
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /attendance_system/import_students.php' . $qs);
exit();
