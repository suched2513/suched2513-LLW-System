<?php
/**
 * duty/cron/notify_duty.php
 * เรียกโดย GitHub Actions ตามเวลา:
 *   ?shift=day     → เวรเช้า   (06:30)
 *   ?shift=evening → เวรเย็น   (16:20)
 *   ?shift=night   → เวรกลาง  (17:30)
 *
 * ต้องส่ง ?key=<duty_notify_key> มาด้วยทุกครั้ง
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';

// ── 1. ตรวจ key ──────────────────────────────────────────────────────────
$key   = trim($_GET['key'] ?? '');
$shift = trim($_GET['shift'] ?? '');

$row = $conn->query("SELECT svalue FROM duty_settings WHERE skey='duty_notify_key' LIMIT 1")->fetch_assoc();
$expected = $row['svalue'] ?? '';

if (!$expected || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!in_array($shift, ['day', 'evening', 'night'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid shift']);
    exit;
}

// ── 2. ดึงการตั้งค่า Telegram ────────────────────────────────────────────
$settings = $conn->query("SELECT duty_bot_token, duty_chat_id FROM wfh_system_settings LIMIT 1")->fetch_assoc();
$token    = $settings['duty_bot_token'] ?? '';
$chat_id  = $settings['duty_chat_id']   ?? '';

if (!$token || !$chat_id) {
    echo json_encode(['ok' => false, 'message' => 'Telegram ยังไม่ได้ตั้งค่า']);
    exit;
}

// ── 3. ชื่อกะ ────────────────────────────────────────────────────────────
$shiftLabel = ['day' => '🌅 เวรเช้า', 'evening' => '🌆 เวรเย็น', 'night' => '🌙 เวรกลางคืน'];
$shiftTime  = ['day' => '06:30 น.', 'evening' => '16:20 น.', 'night' => '17:30 น.'];

// ── 4. วันที่ภาษาไทย ─────────────────────────────────────────────────────
$today = date('Y-m-d');
$dayTh = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$monTh = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$ts    = strtotime($today);
$dateStr = 'วัน' . $dayTh[date('w', $ts)] . 'ที่ ' . date('j', $ts) . ' ' . $monTh[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);

// ── 5. Query ตารางเวร (duty_schedule + duty_teachers) ────────────────────
$stmt = $conn->prepare("
    SELECT ds.point_no, ds.role, dt.prefix, dt.full_name, dg.name AS group_name
    FROM duty_schedule ds
    JOIN duty_teachers dt ON ds.teacher_id = dt.id
    LEFT JOIN duty_groups dg ON ds.group_id = dg.id
    WHERE ds.duty_date = ? AND ds.shift = ? AND dt.status = 'active'
    ORDER BY ds.point_no, ds.teacher_seq
");
$stmt->bind_param('ss', $today, $shift);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 6. ถ้าไม่มีข้อมูลรายจุด → ลองดูจาก duty_day_groups ─────────────────
if (empty($rows)) {
    $stmt2 = $conn->prepare("
        SELECT dt.prefix, dt.full_name, dg.name AS group_name
        FROM duty_day_groups ddg
        JOIN duty_groups dg ON ddg.group_id = dg.id
        JOIN duty_group_members dgm ON dgm.group_id = dg.id
        JOIN duty_teachers dt ON dgm.teacher_id = dt.id
        WHERE ddg.duty_date = ? AND ddg.shift = ? AND dt.status = 'active'
        ORDER BY dg.sort_order, dt.full_name
    ");
    $stmt2->bind_param('ss', $today, $shift);
    $stmt2->execute();
    $groupRows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
}

// ── 7. สร้างข้อความ ───────────────────────────────────────────────────────
$label = $shiftLabel[$shift];
$time  = $shiftTime[$shift];

$msg = "{$label} — {$dateStr}\n";
$msg .= "⏰ เวลาปฏิบัติหน้าที่: <b>{$time}</b>\n";
$msg .= "━━━━━━━━━━━━━━━━━━━\n";

if (!empty($rows)) {
    // แสดงรายจุด
    $byPoint = [];
    foreach ($rows as $r) {
        $byPoint[$r['point_no']][] = $r;
    }
    foreach ($byPoint as $pt => $teachers) {
        $msg .= "\n📍 <b>จุดที่ {$pt}</b>";
        if (!empty($teachers[0]['role'])) {
            $msg .= " — {$teachers[0]['role']}";
        }
        $msg .= "\n";
        foreach ($teachers as $t) {
            $name = trim(($t['prefix'] ?? '') . ' ' . $t['full_name']);
            $msg .= "   👤 {$name}\n";
        }
    }
} elseif (!empty($groupRows)) {
    // แสดงเป็นกลุ่ม
    $groupName = $groupRows[0]['group_name'] ?? 'ไม่ระบุกลุ่ม';
    $msg .= "\n🏷️ <b>กลุ่มเวร: {$groupName}</b>\n";
    foreach ($groupRows as $t) {
        $name = trim(($t['prefix'] ?? '') . ' ' . $t['full_name']);
        $msg .= "   👤 {$name}\n";
    }
} else {
    $msg .= "\n⚠️ ยังไม่มีการกำหนดตารางเวรสำหรับวันนี้\n";
}

$msg .= "\n━━━━━━━━━━━━━━━━━━━\n";
$msg .= "📌 กรุณาปฏิบัติหน้าที่ให้ครบถ้วน";

// ── 8. ส่ง Telegram ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/telegram_bot.php';
$bot    = new TelegramBot($token, $chat_id);
$result = $bot->sendMessage($msg);

echo json_encode([
    'ok'      => $result,
    'shift'   => $shift,
    'date'    => $today,
    'count'   => count($rows) ?: count($groupRows ?? []),
    'message' => $result ? 'ส่งสำเร็จ' : 'ส่งไม่สำเร็จ',
]);
