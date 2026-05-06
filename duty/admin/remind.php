<?php
/**
 * duty/admin/remind.php — ส่งการเตือนครูด้วยตนเอง
 */
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403); exit();
}

$pdo = getPdo();

// ดึง bot token
$stmtT = $pdo->query("SELECT telegram_token FROM wfh_system_settings LIMIT 1");
$botToken = (string)($stmtT->fetchColumn() ?? '');
Telegram::init($botToken);

$reportId   = (int)($_GET['report_id']   ?? 0);
$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$sent = false;

if ($reportId) {
    $stmt = $pdo->prepare(
        "SELECT dr.*, dt.full_name, dt.telegram_user_id, dr.shift, dr.point_no
         FROM duty_reports dr
         JOIN duty_teachers dt ON dt.id = dr.teacher_id
         WHERE dr.id = ? AND dr.status != 'complete'"
    );
    $stmt->execute([$reportId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['telegram_user_id']) {
        $shiftLabel = ['day' => 'กลางวัน', 'night' => 'กลางคืน'];
        $shift = $shiftLabel[$row['shift']] ?? $row['shift'];

        $stmtDS = $pdo->query("SELECT svalue FROM duty_settings WHERE skey IN('report_deadline_day','report_deadline_night','photos_required_per_point')");
        $dsArr = [];
        while ($r = $stmtDS->fetch()) $dsArr[$r['skey']] = $r['svalue'];
        $deadline = $row['shift'] === 'day' ? ($dsArr['report_deadline_day'] ?? '10:00') : ($dsArr['report_deadline_night'] ?? '22:00');
        $required = $dsArr['photos_required_per_point'] ?? 3;

        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM duty_report_photos WHERE report_id=? AND is_deleted=0");
        $stmtCnt->execute([$reportId]);
        $photoCount = (int)$stmtCnt->fetchColumn();

        $msg = "⏰ <b>เตือน: รายงานเวรจุดที่ {$row['point_no']}</b>\n" .
               "กะ: {$shift}\n" .
               "ส่งรูปแล้ว: {$photoCount}/{$required} รูป\n" .
               "กรุณาส่งรูปให้ครบก่อนเวลา {$deadline} น.\n\n" .
               "ส่งรูปได้เลยในแชทนี้ 📸";

        $res = Telegram::sendMessage($msg, (string)$row['telegram_user_id']);
        if ($res['ok']) {
            $pdo->prepare("UPDATE duty_reports SET reminder_sent_at=NOW() WHERE id=?")->execute([$reportId]);
            $sent = true;
        }
    }
}

// Redirect back
$back = $_SERVER['HTTP_REFERER'] ?? '/duty/admin/reports.php';
header('Location: ' . $back . ($sent ? '&reminded=1' : '&remind_fail=1'));
exit();
