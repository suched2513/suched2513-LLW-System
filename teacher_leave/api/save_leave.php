<?php
/**
 * teacher_leave/api/save_leave.php
 * API for saving leave request with signatures and attachments
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';
require_once '../includes/functions.php';
require_once '../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// Switch to $_POST since we are using FormData for file uploads
$userId = $_SESSION['user_id'];
$leaveType = $_POST['leave_type'] ?? '';
$reason = $_POST['reason'] ?? '';
$dateStart = $_POST['date_start'] ?? '';
$dateEnd = $_POST['date_end'] ?? '';
$contactInfo = $_POST['contact_info'] ?? '';
$signatureData = $_POST['signature'] ?? ''; // Base64 string from Canvas

if (!$leaveType || !$dateStart || !$dateEnd) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // ─── 1. ข้อมูลพื้นฐาน ───
    if (strtotime($dateEnd) < strtotime($dateStart)) {
        throw new Exception('วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น');
    }

    // ─── 2. ตรวจสอบการลาทับซ้อน (Overlap Check) ───
    $stmtCheck = $pdo->prepare("
        SELECT id FROM tl_requests 
        WHERE user_id = ? 
        AND status != 'rejected' 
        AND date_start <= ? 
        AND date_end >= ?
        LIMIT 1
    ");
    $stmtCheck->execute([$userId, $dateEnd, $dateStart]);
    if ($stmtCheck->fetch()) {
        throw new Exception('คุณมีการลาที่รออนุมัติหรืออนุมัติแล้วในช่วงเวลานี้แล้ว');
    }

    // ─── 3. คำนวณวันลา ───
    $daysCount = calculateLeaveDays($dateStart, $dateEnd, $pdo);
    if ($daysCount <= 0) {
        throw new Exception('ช่วงวันที่ที่เลือกตรงกับวันหยุดหรือวันเสาร์-อาทิตย์ทั้งหมด ไม่สามารถยื่นลาได้');
    }
    $fiscalYear = getThaiFiscalYear($dateStart);

    // ─── 4. จัดการบันทึกลายเซ็น (ภาพ) ───
    $signaturePath = null;
    if ($signatureData && strpos($signatureData, 'base64,') !== false) {
        $imgData = explode('base64,', $signatureData)[1];
        $imgBinary = base64_decode($imgData);
        $fileName = 'sig_' . $userId . '_' . time() . '.png';
        $sigDir = __DIR__ . '/../../uploads/signatures/';
        if (!is_dir($sigDir)) mkdir($sigDir, 0775, true);
        
        if (file_put_contents($sigDir . $fileName, $imgBinary)) {
            $signaturePath = 'uploads/signatures/' . $fileName;
        }
    }

    // ─── 5. จัดการบันทึกไฟล์แนบ (Medical Certificate) ───
    $attachmentPath = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowed)) {
            throw new Exception('ประเภทไฟล์แนบไม่ถูกต้อง (รองรับ JPG, PNG, PDF)');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('ขนาดไฟล์แนบต้องไม่เกิน 5MB');
        }

        $attDir = __DIR__ . '/../../uploads/attachments/';
        if (!is_dir($attDir)) mkdir($attDir, 0775, true);
        
        $newFileName = 'att_' . $userId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $attDir . $newFileName)) {
            $attachmentPath = 'uploads/attachments/' . $newFileName;
        }
    }

    // ─── 6. บันทึกคำขอ ───
    $stmt = $pdo->prepare("
        INSERT INTO tl_requests (user_id, leave_type, reason, date_start, date_end, days_count, contact_info, signature_path, attachment_path, status, fiscal_year, level_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 1)
    ");
    $stmt->execute([
        $userId, $leaveType, $reason, $dateStart, $dateEnd, $daysCount, $contactInfo, $signaturePath, $attachmentPath, $fiscalYear
    ]);
    
    $requestId = $pdo->lastInsertId();

    // ─── 7. สร้าง Approval Log เริ่มต้น (Level 1: เจ้าหน้าที่ตรวจสอบ) ───
    $stmtLog = $pdo->prepare("INSERT INTO tl_approvals (request_id, level, approver_id, status) VALUES (?, 1, 0, 0)");
    $stmtLog->execute([$requestId]);

    $pdo->commit();

    // ─── 8. ส่งแจ้งเตือน Telegram ───
    try {
        // ดึง admin_chat_id ก่อน (มีอยู่แน่) แล้วค่อย leave_chat_id แยก
        $stmtSet = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
        $config  = $stmtSet ? $stmtSet->fetch() : null;

        // leave_chat_id อาจยังไม่มี column (migration ยังไม่รัน) — ดึงแยก
        $leaveChatId = '';
        try {
            $lcs = $pdo->query("SELECT leave_chat_id FROM wfh_system_settings LIMIT 1");
            if ($lcs) $leaveChatId = (string)($lcs->fetchColumn() ?? '');
        } catch (\Throwable $ignore) {}

        $tgToken  = $config['telegram_token'] ?? '';
        $tgChatId = !empty($leaveChatId) ? $leaveChatId : ($config['admin_chat_id'] ?? '');

        if ($config && !empty($tgToken) && !empty($tgChatId)) {
            $typeMap  = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจส่วนตัว', 'vacation' => 'ลาพักผ่อน', 'maternity' => 'ลาคลอดบุตร', 'other' => 'ลาอื่นๆ'];
            $typeName = $typeMap[$leaveType] ?? 'ไม่ระบุ';
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $leaveUrl = "{$scheme}://" . ($_SERVER['HTTP_HOST'] ?? 'llw.krusuched.com') . "/teacher_leave/";

            $msg  = $attachmentPath ? "📝📎 <b>ยื่นใบลาใหม่ (มีไฟล์แนบ)</b>\n" : "📝 <b>ยื่นใบลาใหม่</b>\n";
            $msg .= "👤 ผู้ลา: " . $_SESSION['fullname'] . "\n";
            $msg .= "📂 ประเภท: " . $typeName . "\n";
            $msg .= "🗓 วันที่: " . $dateStart . " ถึง " . $dateEnd . "\n";
            $msg .= "⏱ จำนวน: " . $daysCount . " วัน\n";
            $msg .= "🔍 เหตุผล: " . $reason . "\n";
            $msg .= "-------------------\n";
            $msg .= "🔗 <a href=\"{$leaveUrl}\">กดที่นี่เพื่ออนุมัติใบลา</a>";

            sendTelegramMessage($tgToken, $tgChatId, $msg);
        }
    } catch (\Throwable $tgEx) {
        error_log("Telegram Error: " . $tgEx->getMessage());
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'ส่งใบลาเรียบร้อยแล้ว รอการตรวจสอบ',
        'data' => ['request_id' => $requestId, 'days_count' => $daysCount]
    ]);

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[LLW] save_leave error [' . get_class($e) . ']: ' . $e->getMessage());
    http_response_code(400);
    $msg = ($e instanceof \PDOException || strpos($e->getMessage(), 'SQLSTATE') === 0)
        ? 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่'
        : $e->getMessage();
    echo json_encode(['status' => 'error', 'message' => $msg]);
}
