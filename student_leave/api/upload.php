<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth
$isStudent = isset($_SESSION['is_student']) && $_SESSION['is_student'] === true;
$isStaff   = isset($_SESSION['llw_role']);

if (!$isStudent && !$isStaff) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบหมายเลขคำขอ']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์หรือเกิดข้อผิดพลาดในการอัปโหลด']);
    exit;
}

$file = $_FILES['file'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไฟล์มีขนาดเกิน 5MB']);
    exit;
}

// Validate extension and mime type
$allowed_ext  = ['jpg', 'jpeg', 'png', 'pdf'];
$allowed_mime = ['image/jpeg', 'image/png', 'application/pdf'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ประเภทไฟล์ไม่ได้รับอนุญาต (JPG, PNG, PDF เท่านั้น)']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_mime, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ประเภทไฟล์ไม่ถูกต้อง']);
    exit;
}

// Verify request ownership if student
try {
    $pdo = getPdo();
    if ($isStudent) {
        $student_code = $_SESSION['student_code'] ?? '';
        $stmt = $pdo->prepare("SELECT id FROM stl_requests WHERE id = ? AND student_id = ?");
        $stmt->execute([$request_id, $student_code]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์อัปโหลดสำหรับคำขอนี้']);
            exit;
        }
    }

    // Generate random filename
    $new_name = bin2hex(random_bytes(16)) . '.' . $ext;
    $upload_dir = __DIR__ . '/../../uploads/student_leave/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $dest = $upload_dir . $new_name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถบันทึกไฟล์ได้']);
        exit;
    }

    $file_path = 'uploads/student_leave/' . $new_name;
    $stmt = $pdo->prepare("
        INSERT INTO stl_attachments (request_id, file_path, file_name, file_size, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$request_id, $file_path, $file['name'], $file['size']]);

    echo json_encode(['status' => 'success', 'file_path' => $file_path]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
    error_log('[stl upload] ' . $e->getMessage());
}
