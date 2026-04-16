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
    $fiscalYear = getThaiFiscalYear($dateStart);

    // ─── 4. จัดการบันทึกลายเซ็น (ภาพ) ───
    $signaturePath = null;
    if ($signatureData && str_contains($signatureData, 'base64,')) {
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
        $stmtSet = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
        $config = $stmtSet->fetch();
        
        if ($config && !empty($config['telegram_token']) && !empty($config['admin_chat_id'])) {
            $typeMap = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจส่วนตัว', 'vacation' => 'ลาพักผ่อน', 'maternity' => 'ลาคลอดบุตร', 'other' => 'ลาอื่นๆ'];
            $typeName = $typeMap[$leaveType] ?? 'ไม่ระบุ';
            
            $msg = "📝 <b>ยื่นใบลาใหม่</b>\n";
            if ($attachmentPath) $msg = "📝📎 <b>ยื่นใบลาใหม่ (มีไฟล์แนบ)</b>\n";
            
            $msg .= "👤 ผู้ลา: " . $_SESSION['fullname'] . "\n";
            $msg .= "📂 ประเภท: " . $typeName . "\n";
            $msg .= "🗓 วันที่: " . $dateStart . " ถึง " . $dateEnd . "\n";
            $msg .= "⏱ จำนวน: " . $daysCount . " วัน\n";
            $msg .= "🔍 เหตุผล: " . $reason . "\n";
            $msg .= "-------------------\n";
            $msg .= "กรุณาตรวจสอบในระบบจัดการครับ";
            
            sendTelegramMessage($config['telegram_token'], $config['admin_chat_id'], $msg);
        }
    } catch (Exception $tgEx) {
        error_log("Telegram Error: " . $tgEx->getMessage());
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'ส่งใบลาเรียบร้อยแล้ว รอการตรวจสอบ',
        'data' => ['request_id' => $requestId, 'days_count' => $daysCount]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
