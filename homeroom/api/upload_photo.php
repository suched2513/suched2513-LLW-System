<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$classroom = $_POST['classroom'] ?? null;
$log_date = $_POST['log_date'] ?? null;
$caption = $_POST['caption'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$classroom || !$log_date || !isset($_FILES['photo'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    
    $file = $_FILES['photo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($ext, $allowed)) {
        throw new Exception('นามสกุลไฟล์ไม่ถูกต้อง');
    }

    $uploadDir = __DIR__ . '/../../uploads/homeroom/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = 'hr_' . $classroom . '_' . $log_date . '_' . uniqid() . '.' . $ext;
    $fileName = str_replace(['/', '\\'], '_', $fileName); // sanitize
    $dest = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $pathForDb = '/uploads/homeroom/' . $fileName;
        
        $stmt = $pdo->prepare("
            INSERT INTO homeroom_photos (classroom, log_date, image_path, caption, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$classroom, $log_date, $pathForDb, $caption, $user_id]);

        echo json_encode(['status' => 'success', 'path' => $pathForDb]);
    } else {
        throw new Exception('ไม่สามารถอัปโหลดไฟล์ได้');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
