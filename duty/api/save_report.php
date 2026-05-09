<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$pdo = getPdo();
$assignmentId = (int)($_POST['assignment_id'] ?? 0);
$reportNote   = trim($_POST['report_note'] ?? '');
$photos       = $_FILES['photos'] ?? null;

// ── Auto-migration: เพิ่ม report_note และ completed_at ถ้ายังไม่มี ──
try {
    $stmtCols = $pdo->query("SHOW COLUMNS FROM duty_reports");
    $existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('report_note', $existingCols)) {
        $pdo->exec("ALTER TABLE duty_reports ADD COLUMN report_note TEXT NULL AFTER status");
    }
    if (!in_array('completed_at', $existingCols)) {
        $pdo->exec("ALTER TABLE duty_reports ADD COLUMN completed_at DATETIME NULL AFTER report_note");
    }
} catch (Exception $e) { error_log("Auto-migration failed: " . $e->getMessage()); }

if (!$assignmentId || !$photos) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. ดึงข้อมูล assignment
    $stmtS = $pdo->prepare("SELECT * FROM duty_schedule WHERE id = ?");
    $stmtS->execute([$assignmentId]);
    $schedule = $stmtS->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) throw new Exception('ไม่พบข้อมูลตารางเวร');

    // 2. ค้นหาหรือสร้างหัวข้อรายงาน (duty_reports)
    $stmtR = $pdo->prepare("
        SELECT id FROM duty_reports 
        WHERE duty_date = ? AND shift = ? AND point_no = ?
        LIMIT 1
    ");
    $stmtR->execute([$schedule['duty_date'], $schedule['shift'], $schedule['point_no']]);
    $reportId = $stmtR->fetchColumn();

    if (!$reportId) {
        $stmtInsR = $pdo->prepare("
            INSERT INTO duty_reports (duty_date, shift, point_no, teacher_id, report_note, status)
            VALUES (?, ?, ?, ?, ?, 'partial')
        ");
        $stmtInsR->execute([$schedule['duty_date'], $schedule['shift'], $schedule['point_no'], $schedule['teacher_id'], $reportNote]);
        $reportId = $pdo->lastInsertId();
    } else {
        // อัปเดต note กรณีส่งเพิ่ม
        $pdo->prepare("UPDATE duty_reports SET report_note = ? WHERE id = ?")->execute([$reportNote, $reportId]);
    }

    // 3. จัดการอัปโหลดไฟล์
    $uploadDir = __DIR__ . '/../../uploads/reports/' . date('Y-m-d') . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $stmtPhoto = $pdo->prepare("
        INSERT INTO duty_report_photos (report_id, file_path, file_size, received_at)
        VALUES (?, ?, ?, NOW())
    ");

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $maxFileSize = 10 * 1024 * 1024; // 10 MB

    $count = count($photos['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($photos['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($photos['size'][$i] > $maxFileSize) continue;

        $ext = strtolower(pathinfo($photos['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) continue;

        $mime = mime_content_type($photos['tmp_name'][$i]);
        if (!in_array($mime, $allowedMime, true)) continue;

        $fileName = $reportId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        $relativeDir = 'uploads/reports/' . date('Y-m-d') . '/';
        $dbPath = $relativeDir . $fileName;

        if (move_uploaded_file($photos['tmp_name'][$i], $targetPath)) {
            $stmtPhoto->execute([$reportId, $dbPath, $photos['size'][$i]]);
        }
    }

    // 4. อัปเดตสถานะรายงาน (ถ้ามีรูปครบตามกำหนด)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM duty_report_photos WHERE report_id = ? AND is_deleted = 0");
    $stmtCheck->execute([$reportId]);
    $photoCount = (int)$stmtCheck->fetchColumn();

    $stmtLimit = $pdo->query("SELECT svalue FROM duty_settings WHERE skey = 'photos_required_per_point'");
    $required = (int)($stmtLimit->fetchColumn() ?: 3);

    $status = ($photoCount >= $required) ? 'complete' : 'partial';
    $completedAt = ($status === 'complete') ? date('Y-m-d H:i:s') : null;

    $stmtUpdate = $pdo->prepare("
        UPDATE duty_reports SET status = ?, completed_at = COALESCE(?, completed_at)
        WHERE id = ?
    ");
    $stmtUpdate->execute([$status, $completedAt, $reportId]);

    $pdo->commit();

    // ── 5. ส่งแจ้งเตือน Telegram ────────────────────────────────────────
    try {
        require_once __DIR__ . '/../../includes/telegram_bot.php';

        // auto-migrate duty columns ถ้ายังไม่มี
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('duty_chat_id',   $cols)) $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN duty_chat_id VARCHAR(100) NULL");
        if (!in_array('duty_bot_token', $cols)) $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN duty_bot_token VARCHAR(200) NULL");

        $stmtSet  = $pdo->query("SELECT duty_bot_token, duty_chat_id FROM wfh_system_settings LIMIT 1");
        $tgCfg    = $stmtSet ? $stmtSet->fetch() : null;
        $tgToken  = $tgCfg['duty_bot_token'] ?? '';
        $tgChatId = $tgCfg['duty_chat_id']   ?? '';

        if (!empty($tgToken) && !empty($tgChatId)) {
            // ชื่อครู
            $stmtT = $pdo->prepare("SELECT prefix, full_name FROM duty_teachers WHERE id = ?");
            $stmtT->execute([$schedule['teacher_id']]);
            $teacher     = $stmtT->fetch();
            $teacherName = trim(($teacher['prefix'] ?? '') . ' ' . ($teacher['full_name'] ?? ''));

            // วันที่ภาษาไทย
            $dayTh  = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
            $monTh  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            $ts     = strtotime($schedule['duty_date']);
            $dateStr = 'วัน' . $dayTh[date('w', $ts)] . 'ที่ ' . date('j', $ts)
                     . ' ' . $monTh[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);

            $shiftLabel = ['day' => '🌞 กลางวัน', 'evening' => '🌆 เย็น', 'night' => '🌙 กลางคืน'];
            $shiftText  = $shiftLabel[$schedule['shift']] ?? $schedule['shift'];
            $statusIcon = ($status === 'complete') ? '✅ ครบแล้ว' : "⚠️ ยังไม่ครบ ({$photoCount}/{$required} รูป)";

            $msg  = "📸 <b>รายงานเวรประจำวัน</b>\n";
            $msg .= "📍 จุดที่ {$schedule['point_no']} — {$shiftText}\n";
            $msg .= "🗓 {$dateStr}\n";
            $msg .= "👤 ผู้รายงาน: {$teacherName}\n";
            if ($reportNote !== '') {
                $msg .= "📝 หมายเหตุ: {$reportNote}\n";
            }
            $msg .= "📷 ภาพถ่าย: {$photoCount} รูป\n";
            $msg .= "สถานะ: {$statusIcon}";

            // ใช้ Telegram class (cURL) แทน sendTelegramMessage (file_get_contents)
            Telegram::init($tgToken);
            $tgResult = Telegram::sendMessage($msg, $tgChatId);
            if (!($tgResult['ok'] ?? false)) {
                error_log('[Duty] Telegram send failed: ' . ($tgResult['description'] ?? 'unknown'));
            }
        }
    } catch (\Throwable $tgEx) {
        error_log('[Duty] Telegram notify error: ' . $tgEx->getMessage());
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกรายงานเรียบร้อยแล้ว']);

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('[Duty] save_report error [' . get_class($e) . ']: ' . $e->getMessage());
    $msg = ($e instanceof \PDOException || strpos($e->getMessage(), 'SQLSTATE') === 0)
        ? 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่'
        : $e->getMessage();
    echo json_encode(['status' => 'error', 'message' => $msg]);
}
