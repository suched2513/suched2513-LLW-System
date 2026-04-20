<?php
/**
 * api/download_user_template.php — ดาวน์โหลดไฟล์ CSV ตัวอย่างสำหรับนำเข้าผู้ใช้
 */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=llw_user_template.csv');

$output = fopen('php://output', 'w');

// ใส่ข้อมูลตัวอย่าง (ไม่มีหัวตารางตามที่ระบบออกแบบไว้)
fputcsv($output, ['สมชาย_ใจดี', 'สมชาย', 'ใจดี', '123456', 'att_teacher']);
fputcsv($output, ['มาลี_มีสุข', 'มาลี', 'มีสุข', '654321', 'wfh_staff']);
fputcsv($output, ['แอดมิน_ไอที', 'ไอที', 'ระบบ', 'password123', 'super_admin']);

fclose($output);
exit;
