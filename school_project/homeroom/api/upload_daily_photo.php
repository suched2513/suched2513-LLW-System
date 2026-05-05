<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
requireLogin();

$u  = getCurrentUser();
$db = getDB();

$logId = (int)($_POST['log_id'] ?? 0);
if ($logId <= 0) { echo json_encode(['ok' => false, 'msg' => 'log_id required']); exit; }

// ตรวจสิทธิ์ + สถานะ
$stmt = $db->prepare("SELECT teacher_id, status FROM hr_daily_logs WHERE id = ?");
$stmt->execute([$logId]);
$log = $stmt->fetch();

if (!$log) { echo json_encode(['ok' => false, 'msg' => 'not found']); exit; }

$isAdmin = in_array($u['role'], ['admin', 'super_admin']);
if (!$isAdmin && (int)$log['teacher_id'] !== (int)$u['id']) {
    echo json_encode(['ok' => false, 'msg' => 'forbidden']); exit;
}
if (!in_array($log['status'], ['draft', 'revision'])) {
    echo json_encode(['ok' => false, 'msg' => 'report already submitted']); exit;
}

// ตรวจไฟล์
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'no file']); exit;
}

$file     = $_FILES['photo'];
$maxBytes = 5 * 1024 * 1024;
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];

$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mime     = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed) || $file['size'] > $maxBytes) {
    echo json_encode(['ok' => false, 'msg' => 'invalid file']); exit;
}

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$subDir   = 'hr/daily/' . $logId . '/';
$fullDir  = UPLOAD_PATH . $subDir;

if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

if (!move_uploaded_file($file['tmp_name'], $fullDir . $filename)) {
    echo json_encode(['ok' => false, 'msg' => 'upload failed']); exit;
}

$ins = $db->prepare("INSERT INTO hr_daily_photos (log_id, filename, original_name) VALUES (?, ?, ?)");
$ins->execute([$logId, $filename, $file['name']]);
$photoId = (int)$db->lastInsertId();

echo json_encode([
    'ok'  => true,
    'id'  => $photoId,
    'url' => BASE_URL . '/uploads/' . $subDir . $filename,
]);
