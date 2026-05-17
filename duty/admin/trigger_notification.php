<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Auth guard
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

define('DUTY_CRON_INTERNAL', true);
require_once __DIR__ . '/../cron/notify_duty.php';

echo "<script>alert('ส่งแจ้งเตือนทดสอบเรียบร้อยแล้ว'); window.location.href='schedule.php';</script>";
