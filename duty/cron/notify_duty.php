<?php
/**
 * duty/cron/notify_duty.php
 * ส่งแจ้งเตือนตารางเวรประจำวัน (กลางวัน + กลางคืน) + preview พรุ่งนี้
 *
 * เรียกใช้ 2 แบบ:
 *   CLI  : php /path/llw/duty/cron/notify_duty.php
 *   Admin: define('DUTY_CRON_INTERNAL', true) แล้ว include ไฟล์นี้
 *
 * Cron: 0 6 * * * php /path/llw/duty/cron/notify_duty.php >> /var/log/duty_notify.log 2>&1
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

// ── Settings ──────────────────────────────────────────────────────────────
$stmtSet = $pdo->query("SELECT duty_bot_token, duty_chat_id FROM wfh_system_settings LIMIT 1");
$sys     = $stmtSet->fetch(PDO::FETCH_ASSOC);
$token   = $sys['duty_bot_token'] ?? '';
$chatId  = $sys['duty_chat_id']   ?? '';

if (!$token || !$chatId) {
    echo "[" . date('H:i:s') . "] ยังไม่ได้ตั้งค่า duty_bot_token / duty_chat_id\n";
    return;
}

Telegram::init($token);

// ── Helpers ───────────────────────────────────────────────────────────────
$dayTh = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$monTh = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

function notifyThaiDate(string $date, array $dayTh, array $monTh): string {
    $ts = strtotime($date);
    return 'วัน' . $dayTh[(int)date('w', $ts)]
         . 'ที่ ' . (int)date('j', $ts)
         . ' ' . $monTh[(int)date('n', $ts)]
         . ' ' . ((int)date('Y', $ts) + 543);
}

function getDutyByDate(PDO $pdo, string $date): array {
    $stmt = $pdo->prepare("
        SELECT ds.shift, ds.point_no, ds.role,
               dt.prefix, dt.full_name
        FROM duty_schedule ds
        JOIN duty_teachers dt ON ds.teacher_id = dt.id
        WHERE ds.duty_date = ? AND dt.status = 'active'
        ORDER BY ds.shift ASC, ds.point_no ASC
    ");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = ['day' => [], 'night' => []];
    foreach ($rows as $r) {
        $shift = $r['shift'];
        if (!array_key_exists($shift, $result)) continue;
        $result[$shift][] = [
            'point_no' => (int)$r['point_no'],
            'role'     => $r['role'] ?? '',
            'name'     => trim(($r['prefix'] ?? '') . ' ' . $r['full_name']),
        ];
    }
    return $result;
}

function buildDayBlock(array $rows): string {
    $out = "  ☀️ <b>เวรกลางวัน (06:00–18:00)</b>\n";
    if (empty($rows)) {
        return $out . "  — ยังไม่มีข้อมูล\n";
    }
    $points   = [];
    $chairman = [];
    foreach ($rows as $r) {
        if (mb_strpos($r['role'], 'ประธาน') !== false || mb_strpos($r['role'], 'ครูเวร') !== false) {
            $chairman[] = $r['name'];
        } else {
            $points[$r['point_no']][] = $r['name'];
        }
    }
    foreach ($points as $pt => $names) {
        $out .= "  📍 จุดที่ {$pt}: " . implode(', ', $names) . "\n";
    }
    if (!empty($chairman)) {
        $out .= "  ✍️ ประธาน/ครูเวร: " . implode(', ', $chairman) . "\n";
    }
    return $out;
}

function buildNightBlock(array $rows): string {
    $out = "  🌙 <b>เวรกลางคืน (18:00–06:00)</b>\n";
    if (empty($rows)) {
        return $out . "  — ยังไม่มีข้อมูล\n";
    }
    $points = [];
    foreach ($rows as $r) {
        $points[$r['point_no']][] = $r['name'];
    }
    foreach ($points as $pt => $names) {
        $out .= "  📍 จุดที่ {$pt}: " . implode(', ', $names) . "\n";
    }
    return $out;
}

// ── Build Message ─────────────────────────────────────────────────────────
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$todayDuty    = getDutyByDate($pdo, $today);
$tomorrowDuty = getDutyByDate($pdo, $tomorrow);

if (empty($todayDuty['day']) && empty($todayDuty['night'])) {
    echo "[" . date('H:i:s') . "] ไม่มีตารางเวรวันนี้ ({$today})\n";
    return;
}

$msg  = "🏫 <b>โรงเรียนละลมวิทยา</b>\n";
$msg .= "📋 <b>ตารางเวรประจำวัน</b>\n";
$msg .= "📅 " . notifyThaiDate($today, $dayTh, $monTh) . "\n";
$msg .= "━━━━━━━━━━━━━━━\n";
$msg .= buildDayBlock($todayDuty['day']);
$msg .= "\n";
$msg .= buildNightBlock($todayDuty['night']);

if (!empty($tomorrowDuty['day']) || !empty($tomorrowDuty['night'])) {
    $msg .= "\n━━━━━━━━━━━━━━━\n";
    $msg .= "🔔 <b>พรุ่งนี้</b> " . notifyThaiDate($tomorrow, $dayTh, $monTh) . "\n";
    $msg .= buildDayBlock($tomorrowDuty['day']);
    if (!empty($tomorrowDuty['night'])) {
        $msg .= "\n";
        $msg .= buildNightBlock($tomorrowDuty['night']);
    }
}

$msg .= "\n━━━━━━━━━━━━━━━\n";
$msg .= "📸 <b>ลิงก์ส่งรายงานเวร:</b>\n";
$msg .= "👉 https://llw.krusuched.com/duty/\n";

// ── ส่ง ──────────────────────────────────────────────────────────────────
$res = Telegram::sendMessage($msg, $chatId);
$ok  = $res['ok'] ?? false;
echo "[" . date('H:i:s') . "] notify_duty: " . ($ok ? "สำเร็จ" : "ล้มเหลว — " . ($res['description'] ?? '')) . "\n";
if (!$ok) {
    error_log('[Duty] notify_duty failed: ' . json_encode($res));
}
