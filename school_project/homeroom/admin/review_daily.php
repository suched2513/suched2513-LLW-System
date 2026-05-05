<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
requireRole(['admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: daily_overview.php'); exit; }
verifyCsrf();

$u    = getCurrentUser();
$db   = getDB();

$logId = (int)($_POST['log_id'] ?? 0);
$action = in_array($_POST['action'] ?? '', ['approved', 'revision']) ? $_POST['action'] : null;
$note   = trim($_POST['review_note'] ?? '');

if (!$logId || !$action) { header('Location: daily_overview.php'); exit; }

$stmt = $db->prepare("SELECT * FROM hr_daily_logs WHERE id = ?");
$stmt->execute([$logId]);
$log = $stmt->fetch();

if (!$log || $log['status'] !== 'submitted') {
    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'รายการนี้ไม่สามารถตรวจสอบได้'];
    header('Location: daily_overview.php'); exit;
}

try {
    $db->prepare("UPDATE hr_daily_logs SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?")
       ->execute([$action, $u['id'], $note, $logId]);

    $label = $action === 'approved' ? 'อนุมัติแล้ว' : 'ส่งคืนเพื่อแก้ไข';
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'บันทึกโฮมรูม ' . $log['classroom'] . ' — ' . $label];

    // แจ้งเตือนครู
    addNotification($log['teacher_id'], 'homeroom',
        'บันทึกโฮมรูม ' . $log['log_date'] . ' — ' . $label,
        $note ?: ($action === 'approved' ? 'ผ่านการตรวจสอบแล้ว' : 'กรุณาแก้ไขและส่งใหม่'),
        $logId, 'hr_daily_log'
    );
} catch (Exception $e) {
    error_log('review_daily: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'เกิดข้อผิดพลาด'];
}

header('Location: daily_overview.php?date=' . urlencode($log['log_date'])); exit;
