<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$u  = getCurrentUser();
$db = getDB();

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$photoId = (int)($input['photo_id'] ?? $_POST['photo_id'] ?? 0);

if (!$photoId) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบ photo_id']);
    exit;
}

$photo = $db->prepare("
    SELECT p.*, r.teacher_id, r.status
    FROM hr_report_photos p
    JOIN hr_reports r ON r.id = p.report_id
    WHERE p.id = ?
");
$photo->execute([$photoId]);
$row = $photo->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบภาพ']);
    exit;
}

$isAdmin = in_array($u['role'], ['admin', 'super_admin']);
if (!$isAdmin && (int)$row['teacher_id'] !== (int)$u['id']) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่มีสิทธิ์']);
    exit;
}
if (!in_array($row['status'], ['draft', 'revision'])) {
    echo json_encode(['ok' => false, 'msg' => 'รายงานที่ส่งแล้วไม่สามารถลบภาพได้']);
    exit;
}

// ลบไฟล์จาก disk
$fullPath = UPLOAD_PATH . $row['file_path'];
if (file_exists($fullPath)) {
    @unlink($fullPath);
}

$db->prepare("DELETE FROM hr_report_photos WHERE id = ?")->execute([$photoId]);

echo json_encode(['ok' => true]);
