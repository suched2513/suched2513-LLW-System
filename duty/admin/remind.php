<?php
/**
 * duty/admin/remind.php — ส่งการเตือนครูด้วยตนเอง
 * รองรับ ?report_id=X หรือ ?schedule_id=X
 */
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) {
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

// ── ดึง duty_settings ──
$ds = [];
try {
    $rows = $pdo->query("SELECT skey, svalue FROM duty_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $ds[$r['skey']] = $r['svalue'];
} catch (Exception $e) {}
$deadlineDay   = $ds['report_deadline_day']   ?? '10:00';
$deadlineNight = $ds['report_deadline_night'] ?? '22:00';
$required      = (int)($ds['photos_required_per_point'] ?? 3);

// ── ถ้าส่ง schedule_id มา → หาหรือสร้าง report row ──
if (!$reportId && $scheduleId) {
    $stmtSch = $pdo->prepare(
        "SELECT ds.*, dt.full_name, dt.telegram_user_id
         FROM duty_schedule ds
         JOIN duty_teachers dt ON dt.id = ds.teacher_id
         WHERE ds.id = ?"
    );
    $stmtSch->execute([$scheduleId]);
    $sch = $stmtSch->fetch(PDO::FETCH_ASSOC);

    if ($sch) {
        $pdo->prepare(
            "INSERT INTO duty_reports (duty_date, shift, point_no, teacher_id, report_round, status)
             VALUES (?, ?, ?, ?, 1, 'pending')
             ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)"
        )->execute([$sch['duty_date'], $sch['shift'], $sch['point_no'], $sch['teacher_id'] ?? null]);

        $stmtGet = $pdo->prepare(
            "SELECT id FROM duty_reports WHERE duty_date=? AND shift=? AND point_no=? AND report_round=1"
        );
        $stmtGet->execute([$sch['duty_date'], $sch['shift'], $sch['point_no']]);
        $reportId = (int)$stmtGet->fetchColumn();
    }
}

// ── ส่งเตือนจาก report_id ──
if ($reportId) {
    $stmt = $pdo->prepare(
        "SELECT dr.*, dt.full_name, dt.telegram_user_id
         FROM duty_reports dr
         JOIN duty_teachers dt ON dt.id = dr.teacher_id
         WHERE dr.id = ? AND dr.status != 'complete'"
    );
    $stmt->execute([$reportId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['telegram_user_id']) {
        $shiftLabel = ['day' => 'กลางวัน', 'night' => 'กลางคืน'];
        $shiftTh    = $shiftLabel[$row['shift']] ?? $row['shift'];
        $deadline   = $row['shift'] === 'day' ? $deadlineDay : $deadlineNight;

        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM duty_report_photos WHERE report_id=? AND is_deleted=0");
        $stmtCnt->execute([$reportId]);
        $photoCount = (int)$stmtCnt->fetchColumn();

        $msg = "⏰ <b>เตือน: รายงานเวรจุดที่ {$row['point_no']}</b>\n" .
               "กะ: {$shiftTh}\n" .
               "ส่งรูปแล้ว: {$photoCount}/{$required} รูป\n" .
               "กรุณาส่งรูปให้ครบก่อนเวลา {$deadline} น.\n\n" .
               "ส่งรูปได้เลยในแชทนี้ 📸";

        $res = Telegram::sendMessage($msg, (string)$row['telegram_user_id']);
        if ($res['ok'] ?? false) {
            $pdo->prepare("UPDATE duty_reports SET reminder_sent_at=NOW() WHERE id=?")->execute([$reportId]);
            $sent = true;
        }
    }
}

$back = $_SERVER['HTTP_REFERER'] ?? '/duty/admin/reports.php';
$back = preg_replace('/[&?](reminded|remind_fail)=\d/', '', $back);
$sep  = str_contains($back, '?') ? '&' : '?';
header('Location: ' . $back . $sep . ($sent ? 'reminded=1' : 'remind_fail=1'));
exit();
