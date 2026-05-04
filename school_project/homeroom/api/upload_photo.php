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

$reportId      = (int)($_POST['report_id'] ?? 0);
$activityId    = (int)($_POST['activity_id'] ?? 0) ?: null;
$caption       = trim($_POST['caption'] ?? '');

if (!$reportId) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบ report_id']);
    exit;
}

// ตรวจสิทธิ์: ครูเจ้าของรายงาน หรือ admin
$isAdmin = in_array($u['role'], ['admin', 'super_admin']);
$rep = $db->prepare("SELECT id, status FROM hr_reports WHERE id = ?");
$rep->execute([$reportId]);
$report = $rep->fetch();

if (!$report) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบรายงาน']);
    exit;
}
if (!$isAdmin) {
    $own = $db->prepare("SELECT id FROM hr_reports WHERE id = ? AND teacher_id = ?");
    $own->execute([$reportId, $u['id']]);
    if (!$own->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'ไม่มีสิทธิ์']);
        exit;
    }
}
if (!in_array($report['status'], ['draft', 'revision'])) {
    echo json_encode(['ok' => false, 'msg' => 'รายงานที่ส่งแล้วไม่สามารถเพิ่มภาพได้']);
    exit;
}

// ตรวจไฟล์
$file = $_FILES['photo'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบไฟล์หรืออัปโหลดผิดพลาด (error: ' . ($file['error'] ?? 'none') . ')']);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'msg' => 'ไฟล์ใหญ่เกิน 5MB']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extMap[$mime])) {
    echo json_encode(['ok' => false, 'msg' => 'รองรับเฉพาะ JPG, PNG, WebP']);
    exit;
}

// บันทึกไฟล์
$subDir  = 'hr/reports/' . $reportId . '/';
$fullDir = UPLOAD_PATH . $subDir;
if (!is_dir($fullDir)) {
    mkdir($fullDir, 0755, true);
}

$ext      = $extMap[$mime];
$filename = uniqid('p_') . '.' . $ext;
$dest     = $fullDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok' => false, 'msg' => 'บันทึกไฟล์ไม่สำเร็จ']);
    exit;
}

// หา order_no ถัดไป
$maxOrd = $db->prepare("SELECT COALESCE(MAX(order_no), 0) + 1 FROM hr_report_photos WHERE report_id = ?");
$maxOrd->execute([$reportId]);
$orderNo = (int)$maxOrd->fetchColumn();

$filePath = $subDir . $filename;
$sizeKb   = (int)ceil($file['size'] / 1024);

$ins = $db->prepare("
    INSERT INTO hr_report_photos (report_id, activity_id, file_path, original_name, caption, file_size_kb, order_no)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$ins->execute([$reportId, $activityId, $filePath, $file['name'], $caption, $sizeKb, $orderNo]);
$photoId = (int)$db->lastInsertId();

echo json_encode([
    'ok'  => true,
    'id'  => $photoId,
    'url' => BASE_URL . '/uploads/' . $filePath,
]);
