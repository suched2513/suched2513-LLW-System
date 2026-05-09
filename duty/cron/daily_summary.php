<?php
/**
 * duty/cron/daily_summary.php — ส่งสรุปรายงานเวรประจำวันเข้ากลุ่ม Telegram
 * รันตามเวลาใน settings.daily_summary_time:
 *   30 18 * * * php /path/to/llw/duty/cron/daily_summary.php >> /var/log/duty_summary.log 2>&1
 *
 * เรียกได้ทั้งจาก CLI และจากหน้า admin (define DUTY_CRON_INTERNAL ก่อน)
 */

if (php_sapi_name() !== 'cli' && !defined('DUTY_CRON_INTERNAL')) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit('CLI or localhost only');
    }
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

$pdo = getPdo();

// ── Settings ──
$stmtS = $pdo->query("SELECT duty_bot_token, duty_chat_id FROM wfh_system_settings LIMIT 1");
$sys = $stmtS->fetch(PDO::FETCH_ASSOC);
$botToken    = $sys['duty_bot_token'] ?? '';
$groupChatId = $sys['duty_chat_id']   ?? '';
Telegram::init($botToken, $groupChatId);

$stmtDS = $pdo->query("SELECT skey, svalue FROM duty_settings");
$ds = [];
while ($r = $stmtDS->fetch()) $ds[$r['skey']] = $r['svalue'];
$required       = (int)($ds['photos_required_per_point'] ?? 3);
$attachPreview  = ($ds['summary_attach_preview'] ?? '0') === '1';

// ── ดึงข้อมูลเวรวันนี้ ──
$today = date('Y-m-d');

$stmtSch = $pdo->prepare(
    "SELECT ds.shift, ds.point_no,
            dt.full_name AS teacher_name,
            dr.status,
            dr.completed_at,
            (SELECT COUNT(*) FROM duty_report_photos drp
             WHERE drp.report_id = dr.id AND drp.is_deleted = 0) AS photo_count
     FROM duty_schedule ds
     LEFT JOIN duty_teachers dt ON dt.id = ds.teacher_id
     LEFT JOIN duty_reports  dr ON dr.duty_date = ? AND dr.shift = ds.shift
                                    AND dr.point_no = ds.point_no AND dr.report_round = 1
     WHERE ds.duty_date = ?
     ORDER BY ds.shift ASC, ds.point_no ASC"
);
$stmtSch->execute([$today, $today]);
$schedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

if (empty($schedules)) {
    echo "[" . date('H:i:s') . "] ไม่มีตารางเวรวันนี้\n";
    return; // รองรับ include จากหน้า admin
}

// ── สร้างข้อความสรุป ──
$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$d = (int)date('d');
$m = (int)date('m');
$y = date('Y') + 543;
$thaiDate = "{$d} {$thaiMonths[$m]} {$y}";

$lines = ["📊 <b>สรุปรายงานเวรประจำวัน</b> {$thaiDate}\n"];

$dayRows   = array_filter($schedules, fn($s) => $s['shift'] === 'day');
$nightRows = array_filter($schedules, fn($s) => $s['shift'] === 'night');

$totalAll    = count($schedules);
$completeAll = 0;

function buildShiftLines(array $rows, int $required, int &$completeAll): string {
    $out = '';
    foreach ($rows as $s) {
        $name  = $s['teacher_name'] ?? '(ไม่ระบุ)';
        $count = (int)($s['photo_count'] ?? 0);
        $status = $s['status'] ?? 'pending';

        if ($status === 'complete') {
            $time = $s['completed_at'] ? ' เวลา ' . date('H:i', strtotime($s['completed_at'])) : '';
            $icon = '✅';
            $completeAll++;
        } elseif ($count > 0) {
            $time = '';
            $icon = '🟡';
        } else {
            $time = '';
            $icon = '🔴';
        }

        $out .= "  {$icon} จุดที่ {$s['point_no']} ({$name}) - ";
        if ($status === 'complete') {
            $out .= "ครบ {$required} รูป{$time}";
        } elseif ($count > 0) {
            $out .= "ส่งแล้ว {$count}/{$required} รูป";
        } else {
            $out .= "ยังไม่รายงาน";
        }
        $out .= "\n";
    }
    return $out;
}

if (!empty($dayRows)) {
    $lines[] = "☀️ <b>เวรกลางวัน:</b>";
    $lines[] = buildShiftLines($dayRows, $required, $completeAll);
}

if (!empty($nightRows)) {
    $lines[] = "🌙 <b>เวรกลางคืน:</b>";
    $lines[] = buildShiftLines($nightRows, $required, $completeAll);
}

$pct = $totalAll > 0 ? round(($completeAll / $totalAll) * 100) : 0;
$lines[] = "───────────";
$lines[] = "ครบทั้งหมด <b>{$completeAll}/{$totalAll} จุด ({$pct}%)</b>";

$summaryText = implode("\n", $lines);

// ── ส่งข้อความสรุป ──
$res = Telegram::sendMessage($summaryText, $groupChatId);
echo "[" . date('H:i:s') . "] ส่งสรุป: " . ($res['ok'] ? 'สำเร็จ' : 'ล้มเหลว') . "\n";

// ── ถ้าตั้งค่าให้แนบ preview รูป ──
if ($attachPreview && ($res['ok'] ?? false)) {
    // ส่งรูปแรกของแต่ละจุด (ถ้ามี)
    $stmtPrev = $pdo->prepare(
        "SELECT drp.file_path, dr.point_no, dr.shift
         FROM duty_reports dr
         JOIN duty_report_photos drp ON drp.report_id = dr.id AND drp.is_deleted = 0
         WHERE dr.duty_date = ?
         GROUP BY dr.id
         ORDER BY dr.shift, dr.point_no"
    );
    $stmtPrev->execute([$today]);
    $previews = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);

    $photoPaths = [];
    $baseDir = realpath(__DIR__ . '/../../');
    foreach ($previews as $prev) {
        $fullPath = $baseDir . '/' . ltrim(str_replace('\\', '/', $prev['file_path']), '/');
        if (file_exists($fullPath)) {
            $photoPaths[] = $fullPath;
        }
    }

    if (!empty($photoPaths)) {
        // ส่งเป็น album (สูงสุด 10 รูป)
        $photoPaths = array_slice($photoPaths, 0, 10);
        Telegram::sendMediaGroup($photoPaths, "📸 Preview รูปรายงานเวร", $groupChatId);
    }
}
