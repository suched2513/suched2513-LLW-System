<?php
/**
 * API: Update student profile photo
 * POST multipart/form-data: { student_id: string, file: image }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

// No teacher session required if coming from student portal, but we should validate
// For now, we trust student_id in the POST (simplified as per requirements)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$sid = $_POST['student_id'] ?? '';
if ($sid === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสนักเรียน']);
    exit;
}

// Normalize SID
if (preg_match('/^\d+$/', $sid)) {
    $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาอัปโหลดรูปภาพ']);
    exit;
}

try {
    $pdo = getPdo();

    // Check if student exists in Master (att_students)
    $stmtSt = $pdo->prepare("SELECT name, classroom FROM att_students WHERE student_id = ?");
    $stmtSt->execute([$sid]);
    $student = $stmtSt->fetch();

    if (!$student) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสนักเรียนในระบบหลัก']);
        exit;
    }

    $uploadDir = __DIR__ . '/../../uploads/profiles/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileTmp = $_FILES['file']['tmp_name'];
    $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $newNodeName = 'std_' . $sid . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $newNodeName;
    $dbPath = '/uploads/profiles/' . $newNodeName;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        // Parse level/room from classroom for the metadata table
        $className = $student['classroom'] ?? '';
        $level = '';
        $room = '';
        if (preg_match('/(\d+)\/(\d+)/', $className, $matches)) {
            $level = $matches[1];
            $room = $matches[2];
        }

        // Upsert into beh_students
        $stmt = $pdo->prepare("
            INSERT INTO beh_students (student_id, name, level, room, img_url) 
            VALUES (:sid, :name, :lvl, :rm, :img)
            ON DUPLICATE KEY UPDATE img_url = :img
        ");
        $stmt->execute([
            'sid'  => $sid,
            'name' => $student['name'],
            'lvl'  => $level,
            'rm'   => $room,
            'img'  => $dbPath
        ]);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'อัปโหลดรูปโปรไฟล์สำเร็จ',
            'img_url' => $dbPath
        ]);
    } else {
        throw new Exception("ไม่สามารถบันทึกไฟล์ได้");
    }

} catch (Exception $e) {
    error_log('[behavior] update_student_profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปโหลด']);
}
