<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์']);
    exit;
}

$teacherId = (int)($_POST['teacher_id'] ?? 0);
if (!$teacherId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ teacher_id']);
    exit;
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์หรืออัปโหลดล้มเหลว']);
    exit;
}

$file = $_FILES['photo'];

// Validate size (max 5 MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'ไฟล์ใหญ่เกิน 5 MB']);
    exit;
}

// Validate MIME
$mime = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    echo json_encode(['status' => 'error', 'message' => 'รองรับเฉพาะ JPG, PNG, WEBP']);
    exit;
}
$ext = $allowed[$mime];

$uploadDir = __DIR__ . '/../../uploads/teacher_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename  = 'teacher_' . $teacherId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath  = $uploadDir . $filename;
$relPath   = 'uploads/teacher_photos/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'บันทึกไฟล์ไม่ได้']);
    exit;
}

try {
    $pdo = getPdo();

    // ลบรูปเก่า
    $old = $pdo->prepare("SELECT photo FROM duty_teachers WHERE id = ?");
    $old->execute([$teacherId]);
    $oldPhoto = $old->fetchColumn();
    if ($oldPhoto) {
        $oldFull = __DIR__ . '/../../' . $oldPhoto;
        if (file_exists($oldFull)) @unlink($oldFull);
    }

    $pdo->prepare("UPDATE duty_teachers SET photo = ? WHERE id = ?")
        ->execute([$relPath, $teacherId]);

    echo json_encode(['status' => 'success', 'path' => $relPath]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
