<?php
/**
 * parent_meeting/index.php - หน้าเข้าสู่ระบบหลักและส่งตัวผู้ใช้ไปยัง Dashboard
 */
require_once __DIR__ . '/config.php';

// ตรวจสอบเซสชัน หากยังไม่ได้เข้าสู่ระบบระบบจะส่งผู้ใช้ไปที่ login.php อัตโนมัติ
checkLogin();

// หากเข้าสู่ระบบแล้ว ให้ส่งไปยังหน้า Dashboard
header('Location: ' . pm_url('dashboard.php'));
exit;
