<?php
// POST-only handler: admin approve or return report for revision
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inbox.php'); exit;
}
verifyCsrf();

$db           = getDB();
$u            = getCurrentUser();
$reportId     = (int)($_POST['report_id'] ?? 0);
$reviewAction = $_POST['review_action'] ?? '';
$reviewNote   = trim($_POST['review_note'] ?? '');

if (!$reportId || !in_array($reviewAction, ['approved', 'revision'])) {
    flashMessage('danger', 'ข้อมูลไม่ถูกต้อง');
    header('Location: inbox.php'); exit;
}

$rep = $db->prepare("SELECT id, teacher_id, status FROM hr_reports WHERE id = ?");
$rep->execute([$reportId]);
$report = $rep->fetch();

if (!$report || $report['status'] !== 'submitted') {
    flashMessage('danger', 'รายงานไม่อยู่ในสถานะรออนุมัติ');
    header('Location: inbox.php'); exit;
}

$db->prepare("
    UPDATE hr_reports SET
        status      = ?,
        reviewed_by = ?,
        reviewed_at = NOW(),
        review_note = ?
    WHERE id = ?
")->execute([$reviewAction, $u['id'], $reviewNote ?: null, $reportId]);

auditLog('hr_report_' . $reviewAction, 'hr_reports', $reportId);

// แจ้งเตือนครู
$msgTitle = $reviewAction === 'approved' ? 'รายงานโฮมรูมได้รับการอนุมัติ' : 'รายงานโฮมรูมถูกส่งคืนเพื่อแก้ไข';
addNotification($report['teacher_id'], $reviewAction === 'approved' ? 'success' : 'warning', $msgTitle, $reviewNote, $reportId, 'hr_report');

$msg = $reviewAction === 'approved' ? 'อนุมัติรายงานเรียบร้อย' : 'ส่งคืนรายงานเรียบร้อย';
flashMessage($reviewAction === 'approved' ? 'success' : 'warning', $msg);
header('Location: ' . BASE_URL . '/homeroom/reports/view.php?id=' . $reportId); exit;
