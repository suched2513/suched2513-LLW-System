<?php
/**
 * parent_meeting/logout.php - สคริปต์ออกจากระบบ
 */
require_once __DIR__ . '/config.php';

// เคลียร์ค่า Session ที่ขึ้นต้นด้วย pm_
unset($_SESSION['pm_user_id']);
unset($_SESSION['pm_fullname']);
unset($_SESSION['pm_username']);
unset($_SESSION['pm_role']);

// หาก Session ว่างเปล่าหมดแล้ว ให้ทำลาย session
// แต่ถ้ามีโมดูลอื่นทำงานอยู่ เราจะเลือกทำลายแค่ข้อมูลเฉพาะระบบเราเพื่อความปลอดภัย
header('Location: ' . pm_url('login.php'));
exit;
