<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'super_admin']);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="bus_students_template.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');

// UTF-8 BOM — ทำให้ Excel แสดงภาษาไทยถูกต้อง
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, ['รหัสนักเรียน', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'ห้องเรียน', 'บ้าน/หมู่บ้าน']);
fputcsv($out, ['04849', '1234567890123', 'เด็กชายสมชาย ใจดี', 'ม.1/1', 'บ้านประชาพัฒนา']);
fputcsv($out, ['04850', '1234567890124', 'เด็กหญิงสมหญิง รักเรียน', 'ม.2/1', 'บ้านหนองแวง']);

fclose($out);
