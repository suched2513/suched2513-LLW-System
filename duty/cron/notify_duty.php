<?php
/**
 * duty/cron/notify_duty.php
 * ส่งสรุปตารางเวรประจำวัน 1 ครั้ง/วัน ตอนเช้า 06:00 ICT
 *
 * ข้อความประกอบด้วย:
 *   - เวรกลางวัน/กลางคืน ของวันนี้
 *   - เวรกลางวัน/กลางคืน ของพรุ่งนี้ (preview)
 *
 * ต้องส่ง ?key=<duty_notify_key> มาทุกครั้ง
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

// ── 1. ตรวจ key ──────────────────────────────────────────────────────────
$key      = trim($_GET['key'] ?? '');
$row      = $conn->query("SELECT svalue FROM duty_settings WHERE skey='duty_notify_key' LIMIT 1")->fetch_assoc();
$expected = $row['svalue'] ?? '';

if (!$expected || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── 2. ดึงการตั้งค่า Telegram (duty bot) ────────────────────────────────
$settings = $conn->query("SELECT duty_bot_token, duty_chat_id FROM wfh_system_settings LIMIT 1")->fetch_assoc();
$token    = $settings['duty_bot_token'] ?? '';
$chat_id  = $settings['duty_chat_id']   ?? '';

if (!$token || !$chat_id) {
    echo json_encode(['ok' => false, 'message' => 'Telegram ยังไม่ได้ตั้งค่า (duty_bot_token / duty_chat_id)']);
    exit;
}

// ── 3. Helper: วันที่ภาษาไทย ────────────────────────────────────────────
$dayTh = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$monTh = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
           'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

function thaiDate(string $date, array $dayTh, array $monTh): string {
    $ts = strtotime($date);
    return 'วัน' . $dayTh[date('w', $ts)] . 'ที่ ' . date('j', $ts)
         . ' ' . $monTh[(int)date('n', $ts)]
         . ' พ.ศ. ' . (date('Y', $ts) + 543);
}

// ── 4. Helper: ดึงข้อมูลเวรจาก duty_schedule ────────────────────────────
function getDutyRows(mysqli $conn, string $date, string $shift): array {
    $stmt = $conn->prepare("
        SELECT ds.point_no, ds.role, dt.prefix, dt.full_name
        FROM duty_schedule ds
        JOIN duty_teachers dt ON ds.teacher_id = dt.id
        WHERE ds.duty_date = ? AND ds.shift = ? AND dt.status = 'active'
        ORDER BY ds.point_no ASC, ds.teacher_seq ASC
    ");
    $stmt->bind_param('ss', $date, $shift);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ── 5. สร้างบล็อกข้อความ (วันนี้ / พรุ่งนี้) ────────────────────────────
function buildBlock(mysqli $conn, string $date, array $dayTh, array $monTh, bool $isToday): string {
    $dayRows   = getDutyRows($conn, $date, 'day');
    $nightRows = getDutyRows($conn, $date, 'night');

    // Header
    if ($isToday) {
        $block = thaiDate($date, $dayTh, $monTh) . "\n";
    } else {
        $block = "\n✍<b>วันพรุ่งนี้ จิตอาสาตรวจความเรียบร้อยกลางวัน</b>\n";
    }

    // กลางวัน 06:00-18:00
    $block .= "🌙ตั้งแต่เวลา 06.00-18.00 น.\n";

    // แยก "จุดเวร" กับ "ประธาน/ครูเวร"
    $points   = [];   // [point_no => [name, ...]]
    $chairman = [];

    foreach ($dayRows as $r) {
        $name = trim(($r['prefix'] ?? '') . ' ' . $r['full_name']);
        $role = $r['role'] ?? '';
        if (mb_strpos($role, 'ประธาน') !== false || mb_strpos($role, 'ครูเวร') !== false) {
            $chairman[] = $name;
        } else {
            $points[(int)$r['point_no']][] = $name;
        }
    }

    if (!empty($points)) {
        $first = true;
        foreach ($points as $pt => $names) {
            $nameStr = implode(', ', $names);
            if ($first) {
                $block .= "😴ตามคำสั่งจุดที่{$pt} คือ\n{$nameStr}\n";
                $first  = false;
            } else {
                $block .= "จุดที่{$pt} {$nameStr}\n";
            }
        }
    } else {
        $block .= "😴 ยังไม่มีข้อมูลจุดเวรกลางวัน\n";
    }

    if (!empty($chairman)) {
        $block .= "\n✍ประธานกิจกรรมวิชาหน้าเสาธง/ครูเวรฯ คือ\n" . implode(', ', $chairman) . "\n";
    }

    // กลางคืน 18:00-06:00
    $block .= "\nจิตอาสาตรวจความเรียบร้อยกลางคืนตั้งแต่ 18.00-06.00\n";
    if (!empty($nightRows)) {
        foreach ($nightRows as $r) {
            $block .= trim(($r['prefix'] ?? '') . ' ' . $r['full_name']) . "\n";
        }
    } else {
        $block .= "ยังไม่มีข้อมูลเวรกลางคืน\n";
    }

    return $block;
}

// ── 6. สร้างข้อความรวม ───────────────────────────────────────────────────
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$msg  = "โรงเรียนละลมวิทยา\n";
$msg .= "📣 <b>แจ้งเตือนจิตอาสาตรวจความเรียบร้อยโรงเรียน</b>\n";
$msg .= buildBlock($conn, $today,    $dayTh, $monTh, true);
$msg .= buildBlock($conn, $tomorrow, $dayTh, $monTh, false);

// ── 7. ส่ง Telegram ───────────────────────────────────────────────────────
$bot    = new TelegramBot($token, $chat_id);
$result = $bot->sendMessage($msg);

echo json_encode([
    'ok'      => $result,
    'today'   => $today,
    'tomorrow'=> $tomorrow,
    'message' => $result ? 'ส่งสำเร็จ' : 'ส่งไม่สำเร็จ',
]);
