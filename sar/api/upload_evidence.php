<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'wfh_staff', 'att_teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์หรืออัปโหลดไม่สำเร็จ']);
    exit;
}

$file = $_FILES['file'];

if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไฟล์ใหญ่เกิน 10 MB']);
    exit;
}

$allowedExts  = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$allowedMimes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$origName = $file['name'];
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExts)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ประเภทไฟล์ไม่อนุญาต (pdf, jpg, png, doc, docx)']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ประเภทไฟล์ไม่ถูกต้อง']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/sar_evidence/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newName = bin2hex(random_bytes(12)) . '.' . $ext;
$dest    = $uploadDir . $newName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'บันทึกไฟล์ไม่สำเร็จ']);
    exit;
}

echo json_encode(['status' => 'success', 'path' => $newName, 'filename' => $origName]);
