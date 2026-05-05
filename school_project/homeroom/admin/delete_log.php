<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
requireRole(['admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: daily_overview.php'); exit; }
verifyCsrf();

$db    = getDB();
$logId = (int)($_POST['log_id'] ?? 0);
if (!$logId) { header('Location: daily_overview.php'); exit; }

$stmt = $db->prepare("SELECT classroom, log_date FROM hr_daily_logs WHERE id = ?");
$stmt->execute([$logId]);
$log = $stmt->fetch();
if (!$log) { $_SESSION['flash'] = ['type'=>'warning','msg'=>'ไม่พบรายการ']; header('Location: daily_overview.php'); exit; }

try {
    $db->beginTransaction();

    // ลบรูปจาก disk
    $photos = $db->prepare("SELECT filename FROM hr_daily_photos WHERE log_id = ?");
    $photos->execute([$logId]);
    foreach ($photos->fetchAll() as $p) {
        $path = UPLOAD_PATH . 'hr/daily/' . $logId . '/' . $p['filename'];
        if (file_exists($path)) unlink($path);
    }

    $db->prepare("DELETE FROM hr_daily_photos     WHERE log_id = ?")->execute([$logId]);
    $db->prepare("DELETE FROM hr_daily_attendance WHERE log_id = ?")->execute([$logId]);
    $db->prepare("DELETE FROM hr_daily_logs       WHERE id     = ?")->execute([$logId]);

    $db->commit();
    $_SESSION['flash'] = ['type'=>'success','msg'=>'ลบบันทึก ' . $log['classroom'] . ' วันที่ ' . $log['log_date'] . ' แล้ว'];
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('delete_log: ' . $e->getMessage());
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'เกิดข้อผิดพลาด'];
}

header('Location: daily_overview.php'); exit;
