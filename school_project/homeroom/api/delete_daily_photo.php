<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
requireLogin();

$u    = getCurrentUser();
$db   = getDB();
$body = json_decode(file_get_contents('php://input'), true);

$photoId = (int)($body['photo_id'] ?? 0);
if ($photoId <= 0) { echo json_encode(['ok' => false]); exit; }

$stmt = $db->prepare("
    SELECT p.*, l.teacher_id, l.status
    FROM hr_daily_photos p
    JOIN hr_daily_logs l ON l.id = p.log_id
    WHERE p.id = ?
");
$stmt->execute([$photoId]);
$row = $stmt->fetch();

if (!$row) { echo json_encode(['ok' => false, 'msg' => 'not found']); exit; }

$isAdmin = in_array($u['role'], ['admin', 'super_admin']);
if (!$isAdmin && (int)$row['teacher_id'] !== (int)$u['id']) {
    echo json_encode(['ok' => false, 'msg' => 'forbidden']); exit;
}
if (!in_array($row['status'], ['draft', 'revision'])) {
    echo json_encode(['ok' => false, 'msg' => 'report already submitted']); exit;
}

$filePath = UPLOAD_PATH . 'hr/daily/' . $row['log_id'] . '/' . $row['filename'];
if (file_exists($filePath)) unlink($filePath);

$db->prepare("DELETE FROM hr_daily_photos WHERE id = ?")->execute([$photoId]);

echo json_encode(['ok' => true]);
