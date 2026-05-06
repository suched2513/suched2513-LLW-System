<?php
/**
 * api/telegram_webhook.php — Telegram Bot Webhook Handler
 * รับ POST จาก Telegram และจัดการรูปภาพรายงานเวร
 */

// ── Security: ตรวจ secret token ก่อนทุกอย่าง ──
require_once __DIR__ . '/../config/database.php';

$pdo = getPdo();

// ดึง webhook secret จาก duty_settings
$stmtSecret = $pdo->prepare("SELECT svalue FROM duty_settings WHERE skey = 'telegram_webhook_secret'");
$stmtSecret->execute();
$webhookSecret = (string)($stmtSecret->fetchColumn() ?? '');

if (!empty($webhookSecret)) {
    $headerSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($webhookSecret, $headerSecret)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// ── อ่าน Telegram Bot Token ──
$stmtTok = $pdo->prepare("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
$stmtTok->execute();
$sysSettings = $stmtTok->fetch(PDO::FETCH_ASSOC);
$botToken    = $sysSettings['telegram_token'] ?? '';
$adminChatId = $sysSettings['admin_chat_id']  ?? '';

if (empty($botToken)) {
    http_response_code(500);
    exit('Bot token not configured');
}

require_once __DIR__ . '/../includes/telegram_bot.php';
Telegram::init($botToken, $adminChatId);

// ── อ่าน duty_settings ──
$stmtDS = $pdo->query("SELECT skey, svalue FROM duty_settings");
$ds = [];
while ($r = $stmtDS->fetch(PDO::FETCH_ASSOC)) {
    $ds[$r['skey']] = $r['svalue'];
}
$photosRequired  = (int)($ds['photos_required_per_point'] ?? 3);
$maxPerMinute    = (int)($ds['max_photos_per_minute'] ?? 30);
$uploadDir       = rtrim($ds['upload_dir'] ?? 'uploads/reports', '/');
$uploadBase      = __DIR__ . '/../' . $uploadDir;

// ── รับ JSON body ──
$raw    = file_get_contents('php://input');
$update = json_decode($raw, true);

if (empty($update)) {
    http_response_code(200);
    exit;
}

// ── Logging ──
$logDir  = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . '/telegram_' . date('Y-m-d') . '.log';
$updateId = $update['update_id'] ?? 0;
file_put_contents($logFile, date('[H:i:s]') . " update_id={$updateId} " . substr($raw, 0, 500) . "\n", FILE_APPEND);

// ── Route ──
if (!empty($update['message'])) {
    handleMessage($update['message'], $pdo, $botToken, $photosRequired, $maxPerMinute, $uploadBase, $uploadDir);
} elseif (!empty($update['callback_query'])) {
    handleCallback($update['callback_query'], $pdo, $photosRequired, $uploadBase, $uploadDir);
}

http_response_code(200);
exit;

// ════════════════════════════════════════════════════════════
// handleMessage — จัดการข้อความและรูปภาพ
// ════════════════════════════════════════════════════════════
function handleMessage(array $msg, PDO $pdo, string $botToken, int $photosRequired, int $maxPerMinute, string $uploadBase, string $uploadDir): void
{
    $fromId   = (int)($msg['from']['id']       ?? 0);
    $chatId   = (string)($msg['chat']['id']    ?? '');
    $text     = trim($msg['text']              ?? '');
    $username = $msg['from']['username']       ?? '';

    // ── /start <token> ──
    if (str_starts_with($text, '/start')) {
        $parts = explode(' ', $text, 2);
        $token = trim($parts[1] ?? '');
        handleStart($fromId, $chatId, $username, $token, $pdo);
        return;
    }

    // ── /status ──
    if ($text === '/status') {
        handleStatus($fromId, $chatId, $pdo, $photosRequired);
        return;
    }

    // ── Photo ──
    if (!empty($msg['photo'])) {
        handlePhoto($msg, $fromId, $chatId, $pdo, $photosRequired, $maxPerMinute, $uploadBase, $uploadDir);
        return;
    }

    // ── ข้อความอื่น ──
    if (!empty($text)) {
        Telegram::sendMessage("ส่งรูปภาพเพื่อรายงานเวร หรือพิมพ์ /status เพื่อดูสถานะ", $chatId);
    }
}

// ════════════════════════════════════════════════════════════
// handleStart — เชื่อมบัญชี Telegram กับครู
// ════════════════════════════════════════════════════════════
function handleStart(int $fromId, string $chatId, string $username, string $token, PDO $pdo): void
{
    if (!empty($token)) {
        // ค้นหา teacher จาก link_token
        $stmt = $pdo->prepare(
            "SELECT id, full_name FROM duty_teachers
             WHERE link_token = ? AND linked_at IS NULL AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher) {
            // ตรวจว่า telegram_user_id นี้ผูกกับคนอื่นแล้วหรือไม่
            $chk = $pdo->prepare("SELECT id FROM duty_teachers WHERE telegram_user_id = ? AND id != ?");
            $chk->execute([$fromId, $teacher['id']]);
            if ($chk->fetch()) {
                Telegram::sendMessage("❌ Telegram account นี้ผูกกับครูท่านอื่นแล้ว\nกรุณาติดต่อแอดมิน", $chatId);
                return;
            }

            // ผูกบัญชี
            $upd = $pdo->prepare(
                "UPDATE duty_teachers
                 SET telegram_user_id = ?, telegram_username = ?, linked_at = NOW(), link_token = NULL
                 WHERE id = ?"
            );
            $upd->execute([$fromId, $username, $teacher['id']]);

            Telegram::sendMessage(
                "✅ <b>เชื่อมต่อบัญชีสำเร็จ!</b>\n\n" .
                "👤 " . htmlspecialchars($teacher['full_name']) . "\n" .
                "🤖 @{$username}\n\n" .
                "ส่งรูปภาพเพื่อรายงานเวร หรือพิมพ์ /status ดูสถานะเวรวันนี้",
                $chatId
            );
        } else {
            Telegram::sendMessage(
                "❌ ลิงก์ไม่ถูกต้องหรือใช้ไปแล้ว\n\nกรุณาขอลิงก์ใหม่จากแอดมิน",
                $chatId
            );
        }
    } else {
        Telegram::sendMessage(
            "🏫 <b>ระบบรายงานเวรโรงเรียนละลมวิทยา</b>\n\n" .
            "เพื่อเริ่มใช้งาน กรุณาติดต่อแอดมินเพื่อขอลิงก์เชื่อมบัญชี\n" .
            "หรือสแกน QR Code จากหน้า admin ของระบบ",
            $chatId
        );
    }
}

// ════════════════════════════════════════════════════════════
// handleStatus — แสดงเวรวันนี้ + สถานะรายงาน
// ════════════════════════════════════════════════════════════
function handleStatus(int $fromId, string $chatId, PDO $pdo, int $photosRequired): void
{
    // ค้นหาครูจาก telegram_user_id
    $stmt = $pdo->prepare("SELECT id, full_name FROM duty_teachers WHERE telegram_user_id = ? AND status = 'active'");
    $stmt->execute([$fromId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Telegram::sendMessage("⚠️ ยังไม่ได้เชื่อมบัญชี\nกรุณาติดต่อแอดมินเพื่อขอลิงก์", $chatId);
        return;
    }

    // ดึงเวรวันนี้
    $stmtSch = $pdo->prepare(
        "SELECT ds.id, ds.shift, ds.point_no, ds.role,
                dr.status, dr.id AS report_id,
                (SELECT COUNT(*) FROM duty_report_photos drp WHERE drp.report_id = dr.id AND drp.is_deleted = 0) AS photo_count
         FROM duty_schedule ds
         LEFT JOIN duty_reports dr ON dr.duty_date = ds.duty_date
             AND dr.shift = ds.shift AND dr.point_no = ds.point_no AND dr.report_round = 1
         WHERE ds.teacher_id = ? AND ds.duty_date = CURDATE()
         ORDER BY ds.shift, ds.point_no"
    );
    $stmtSch->execute([$teacher['id']]);
    $schedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($schedules)) {
        Telegram::sendMessage(
            "📅 <b>วันนี้คุณไม่มีเวร</b>\n\nหากมีข้อสงสัยกรุณาติดต่อแอดมิน",
            $chatId
        );
        return;
    }

    $lines = ["👤 <b>" . htmlspecialchars($teacher['full_name']) . "</b>\n📋 <b>เวรวันนี้:</b>\n"];
    $shiftLabel = ['day' => '☀️ กลางวัน', 'night' => '🌙 กลางคืน'];

    foreach ($schedules as $sch) {
        $shift = $shiftLabel[$sch['shift']] ?? $sch['shift'];
        $point = $sch['point_no'];
        $count = (int)($sch['photo_count'] ?? 0);
        $status = $sch['status'] ?? 'pending';

        if ($status === 'complete') {
            $badge = "✅ ครบ {$photosRequired} รูป";
        } elseif ($count > 0) {
            $badge = "🟡 ส่งแล้ว {$count}/{$photosRequired} รูป";
        } else {
            $badge = "🔴 ยังไม่รายงาน";
        }

        $lines[] = "{$shift} จุดที่ {$point} — {$badge}";
    }

    Telegram::sendMessage(implode("\n", $lines), $chatId);
}

// ════════════════════════════════════════════════════════════
// handlePhoto — รับรูปภาพ
// ════════════════════════════════════════════════════════════
function handlePhoto(array $msg, int $fromId, string $chatId, PDO $pdo, int $photosRequired, int $maxPerMinute, string $uploadBase, string $uploadDir): void
{
    // ── ตรวจ rate limit ──
    if (!checkRateLimit($fromId, $maxPerMinute, $pdo)) {
        Telegram::sendMessage("⚠️ ส่งรูปบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่", $chatId);
        return;
    }

    // ── ค้นหาครู ──
    $stmt = $pdo->prepare("SELECT id, full_name FROM duty_teachers WHERE telegram_user_id = ? AND status = 'active'");
    $stmt->execute([$fromId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Telegram::sendMessage("⚠️ ยังไม่ได้เชื่อมบัญชี\nกรุณาติดต่อแอดมินเพื่อขอลิงก์เชื่อมบัญชี", $chatId);
        return;
    }

    // ── เลือกรูปคุณภาพสูงสุด (PhotoSize array เรียงจากเล็กไปใหญ่) ──
    $photoSizes  = $msg['photo'];
    $bestPhoto   = end($photoSizes); // ใหญ่สุดคืออันสุดท้าย
    $fileId      = $bestPhoto['file_id'];
    $fileSize    = $bestPhoto['file_size'] ?? 0;
    $width       = $bestPhoto['width']     ?? 0;
    $height      = $bestPhoto['height']    ?? 0;
    $caption     = $msg['caption']         ?? null;

    // ── ตรวจขนาดไฟล์ ≤ 10MB ──
    if ($fileSize > 10 * 1024 * 1024) {
        Telegram::sendMessage("❌ ไฟล์ใหญ่เกิน 10MB กรุณาลดขนาดรูปแล้วส่งใหม่", $chatId);
        return;
    }

    // ── ค้นหาเวรวันนี้ ──
    $stmtSch = $pdo->prepare(
        "SELECT id, shift, point_no FROM duty_schedule
         WHERE teacher_id = ? AND duty_date = CURDATE()
         ORDER BY shift, point_no"
    );
    $stmtSch->execute([$teacher['id']]);
    $schedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($schedules)) {
        Telegram::sendMessage("📅 วันนี้คุณไม่มีเวร ไม่สามารถส่งรูปได้\nหากมีข้อสงสัยกรุณาติดต่อแอดมิน", $chatId);
        return;
    }

    // ── ถ้ามีเวรเดียว → บันทึกทันที ──
    if (count($schedules) === 1) {
        $sch = $schedules[0];
        processPhotoForSchedule(
            $sch['shift'], $sch['point_no'], $teacher, $fileId, $fileSize,
            $width, $height, $caption, $fromId, $chatId, $pdo,
            $photosRequired, $uploadBase, $uploadDir
        );
        return;
    }

    // ── ถ้ามีหลายจุด → บันทึก pending แล้วถาม ──
    // บันทึก pending photo
    $exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $ins = $pdo->prepare(
        "INSERT INTO duty_pending_photos (telegram_user_id, file_id, file_size, width, height, caption, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([$fromId, $fileId, $fileSize, $width, $height, $caption, $exp]);
    $pendingId = $pdo->lastInsertId();

    // สร้าง Inline Keyboard
    $keyboard = [];
    $shiftLabel = ['day' => '☀️', 'night' => '🌙'];
    foreach ($schedules as $sch) {
        $label = ($shiftLabel[$sch['shift']] ?? '') . " จุดที่ {$sch['point_no']}";
        $keyboard[] = [['text' => $label, 'callback_data' => "rpt:{$pendingId}:{$sch['shift']}:{$sch['point_no']}"]];
    }

    Telegram::sendMessageWithKeyboard(
        "📸 ได้รับรูปแล้ว\nคุณมีเวรหลายจุดในวันนี้ กรุณาเลือกจุดที่ต้องการรายงาน:",
        $keyboard,
        $chatId
    );
}

// ════════════════════════════════════════════════════════════
// handleCallback — ผู้ใช้กดปุ่มเลือกจุด
// ════════════════════════════════════════════════════════════
function handleCallback(array $cb, PDO $pdo, int $photosRequired, string $uploadBase, string $uploadDir): void
{
    $callbackId = $cb['id'];
    $fromId     = (int)($cb['from']['id'] ?? 0);
    $chatId     = (string)($cb['message']['chat']['id'] ?? '');
    $data       = $cb['data'] ?? '';

    // ── Parse callback_data: rpt:<pendingId>:<shift>:<point_no> ──
    if (!preg_match('/^rpt:(\d+):(day|night):(\d+)$/', $data, $m)) {
        Telegram::answerCallbackQuery($callbackId, 'ข้อมูลไม่ถูกต้อง', true);
        return;
    }

    [, $pendingId, $shift, $pointNo] = $m;

    // ── ค้นหา pending photo ──
    $stmt = $pdo->prepare(
        "SELECT * FROM duty_pending_photos
         WHERE id = ? AND telegram_user_id = ? AND expires_at > NOW()"
    );
    $stmt->execute([$pendingId, $fromId]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pending) {
        Telegram::answerCallbackQuery($callbackId, '❌ หมดเวลา กรุณาส่งรูปใหม่อีกครั้ง', true);
        return;
    }

    // ── ค้นหาครู ──
    $stmtT = $pdo->prepare("SELECT id, full_name FROM duty_teachers WHERE telegram_user_id = ? AND status = 'active'");
    $stmtT->execute([$fromId]);
    $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Telegram::answerCallbackQuery($callbackId, '❌ ไม่พบข้อมูลครู', true);
        return;
    }

    // ── ลบ pending ──
    $pdo->prepare("DELETE FROM duty_pending_photos WHERE id = ?")->execute([$pendingId]);

    // ── บันทึกรูป ──
    Telegram::answerCallbackQuery($callbackId, '✅ กำลังบันทึกรูป...');

    processPhotoForSchedule(
        $shift, (int)$pointNo, $teacher,
        $pending['file_id'], (int)$pending['file_size'],
        (int)$pending['width'], (int)$pending['height'],
        $pending['caption'], $fromId, $chatId, $pdo,
        $photosRequired, $uploadBase, $uploadDir
    );
}

// ════════════════════════════════════════════════════════════
// processPhotoForSchedule — ดาวน์โหลดรูป + บันทึก DB
// ════════════════════════════════════════════════════════════
function processPhotoForSchedule(
    string $shift, int $pointNo, array $teacher,
    string $fileId, int $fileSize, int $width, int $height,
    ?string $caption, int $fromId, string $chatId,
    PDO $pdo, int $photosRequired,
    string $uploadBase, string $uploadDir
): void {
    $today       = date('Y-m-d');
    $teacherId   = $teacher['id'];

    // ── หรือสร้าง duty_reports row ──
    $stmtRep = $pdo->prepare(
        "INSERT INTO duty_reports (duty_date, shift, point_no, teacher_id, report_round, status)
         VALUES (?, ?, ?, ?, 1, 'pending')
         ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)"
    );
    $stmtRep->execute([$today, $shift, $pointNo, $teacherId]);

    $stmtGet = $pdo->prepare(
        "SELECT id FROM duty_reports WHERE duty_date = ? AND shift = ? AND point_no = ? AND report_round = 1"
    );
    $stmtGet->execute([$today, $shift, $pointNo]);
    $reportId = (int)$stmtGet->fetchColumn();

    if (!$reportId) {
        Telegram::sendMessage("❌ เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่", $chatId);
        return;
    }

    // ── นับรูปที่มีอยู่แล้ว ──
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM duty_report_photos WHERE report_id = ? AND is_deleted = 0");
    $stmtCount->execute([$reportId]);
    $existingCount = (int)$stmtCount->fetchColumn();

    // ── สร้าง path บันทึกไฟล์ ──
    $dateDir   = $uploadBase . '/' . $today . '/point_' . $pointNo;
    $rand      = bin2hex(random_bytes(6));
    $timestamp = time();
    $filename  = "{$timestamp}_{$rand}.jpg";
    $filePath  = $dateDir . '/' . $filename;

    // ── ดาวน์โหลดรูปจาก Telegram ──
    $dlResult = Telegram::downloadFile($fileId, $filePath);
    if (!$dlResult['ok']) {
        error_log("[Webhook] downloadFile failed: " . $dlResult['description']);
        Telegram::sendMessage("❌ ไม่สามารถดาวน์โหลดรูปได้ กรุณาลองส่งใหม่", $chatId);
        return;
    }

    // ── ตรวจ MIME type (security) ──
    $mime = mime_content_type($filePath);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) {
        @unlink($filePath);
        Telegram::sendMessage("❌ ไฟล์ที่รับได้ต้องเป็นรูปภาพ (JPEG/PNG/WebP) เท่านั้น", $chatId);
        return;
    }

    // ── สร้าง thumbnail ──
    $thumbDir  = $uploadBase . '/' . $today . '/point_' . $pointNo . '/thumbs';
    $thumbPath = $thumbDir . '/' . $filename;
    Telegram::createThumbnail($filePath, $thumbPath, 400);

    // ── ดึง EXIF GPS (เก็บเงียบ ไม่แสดงใน UI) ──
    $gps    = Telegram::extractExifGps($filePath);
    $gpsLat = $gps['lat'] ?? null;
    $gpsLng = $gps['lng'] ?? null;

    // ── แปลง path → relative path ──
    $baseDir      = __DIR__ . '/../';
    $relFilePath  = str_replace(realpath($baseDir), '', realpath($filePath));
    $relFilePath  = ltrim(str_replace('\\', '/', $relFilePath), '/');
    $relThumbPath = null;
    if (file_exists($thumbPath)) {
        $relThumbPath = str_replace(realpath($baseDir), '', realpath($thumbPath));
        $relThumbPath = ltrim(str_replace('\\', '/', $relThumbPath), '/');
    }

    // ── INSERT report_photos ──
    $stmtIns = $pdo->prepare(
        "INSERT INTO duty_report_photos
         (report_id, file_id, file_path, thumbnail_path, file_size, width, height,
          caption, sent_from_user_id, exif_gps_lat, exif_gps_lng)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtIns->execute([
        $reportId, $fileId, $relFilePath, $relThumbPath,
        $fileSize, $width, $height, $caption,
        $fromId, $gpsLat, $gpsLng
    ]);

    // ── อัปเดต status ──
    $newCount = $existingCount + 1;
    $newStatus = ($newCount >= $photosRequired) ? 'complete' : 'partial';
    $completedAt = ($newStatus === 'complete') ? date('Y-m-d H:i:s') : null;

    $stmtUpd = $pdo->prepare(
        "UPDATE duty_reports SET status = ?, completed_at = ? WHERE id = ?"
    );
    $stmtUpd->execute([$newStatus, $completedAt, $reportId]);

    // ── ตอบกลับครู ──
    $shiftLabel = ['day' => '☀️ กลางวัน', 'night' => '🌙 กลางคืน'];
    $shiftText  = $shiftLabel[$shift] ?? $shift;

    if ($newStatus === 'complete') {
        Telegram::sendMessage(
            "✅ <b>รายงานจุดที่ {$pointNo} ครบ {$photosRequired} รูปแล้ว</b>\n" .
            "กะ: {$shiftText}\nขอบคุณที่รายงานเวร! 🙏",
            $chatId
        );
    } else {
        Telegram::sendMessage(
            "📸 รับรูปที่ {$newCount}/{$photosRequired} สำหรับจุดที่ {$pointNo} แล้ว ✓\n" .
            "กะ: {$shiftText}\nยังเหลืออีก " . ($photosRequired - $newCount) . " รูป",
            $chatId
        );
    }
}

// ════════════════════════════════════════════════════════════
// checkRateLimit — ตรวจ rate limit (30 รูป/นาที)
// ════════════════════════════════════════════════════════════
function checkRateLimit(int $userId, int $maxPerMinute, PDO $pdo): bool
{
    $windowStart = date('Y-m-d H:i:00'); // ต้นนาทีปัจจุบัน

    // Upsert row
    $stmt = $pdo->prepare(
        "INSERT INTO duty_rate_limits (user_id, action, window_start, count)
         VALUES (?, 'photo', ?, 1)
         ON DUPLICATE KEY UPDATE count = count + 1"
    );
    $stmt->execute([$userId, $windowStart]);

    // อ่าน count ปัจจุบัน
    $stmtGet = $pdo->prepare(
        "SELECT count FROM duty_rate_limits
         WHERE user_id = ? AND action = 'photo' AND window_start = ?"
    );
    $stmtGet->execute([$userId, $windowStart]);
    $count = (int)$stmtGet->fetchColumn();

    return $count <= $maxPerMinute;
}
