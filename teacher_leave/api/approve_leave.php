<?php
/**
 * teacher_leave/api/approve_leave.php
 * Level-based approval for leave requests
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config.php';
require_once '../../includes/telegram_bot.php';

$isAdmin = function_exists('llw_has_any_role')
    ? llw_has_any_role(['super_admin', 'wfh_admin', 'director', 'deputy_director'])
    : in_array($_SESSION['llw_role'] ?? '', ['super_admin', 'wfh_admin', 'director', 'deputy_director']);

$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && $userId > 0) {
    try {
        $stmtRole = getPdo()->prepare("SELECT 1 FROM llw_user_roles WHERE user_id = ? AND role IN ('super_admin', 'wfh_admin', 'director', 'deputy_director') LIMIT 1");
        $stmtRole->execute([$userId]);
        if ($stmtRole->fetchColumn()) {
            $isAdmin = true;
        }
    } catch (\Throwable $ignore) {}
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$requestId    = $input['id'] ?? null;
$status       = $input['status'] ?? null; // 1 = approved, 2 = rejected
$comment      = $input['comment'] ?? '';
$signatureB64 = $input['signature'] ?? null; // Base64 PNG จากลายเซ็น ผอ.
$approverId   = $_SESSION['user_id'] ?? 0;
// ถ้าเป็น super_admin หรือเลือก direct_final สามารถอนุมัติจบในขั้นตอนเดียวได้
$directFinal  = !empty($input['direct_final']) || (($_SESSION['llw_role'] ?? '') === 'super_admin');

if (!$requestId || !$status) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$responseResult = null;
$wasFinalApproved = false;

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. ดึงข้อมูลคำขอล่าสุดเพื่อดูระดับปัจจุบัน
    $stmtReq = $pdo->prepare("SELECT * FROM tl_requests WHERE id = ?");
    $stmtReq->execute([$requestId]);
    $request = $stmtReq->fetch();

    if (!$request) {
        throw new Exception('ไม่พบใบลาที่ระบุ');
    }

    $currentLevel = (int)$request['level_at'];

    // 2. บันทึกลายเซ็นผู้อนุมัติ (ถ้ามี)
    $sigPath = null;
    if ($signatureB64 && strpos($signatureB64, 'base64,') !== false) {
        $imgData   = explode('base64,', $signatureB64)[1];
        $imgBinary = base64_decode($imgData);
        $sigDir    = __DIR__ . '/../../uploads/signatures/';
        if (!is_dir($sigDir)) mkdir($sigDir, 0775, true);
        $fileName = 'appr_' . $approverId . '_' . $requestId . '_' . time() . '.png';
        if (file_put_contents($sigDir . $fileName, $imgBinary)) {
            $sigPath = 'uploads/signatures/' . $fileName;
        }
    }

    // 3. บันทึกผลการพิจารณาใน Log
    $stmtLog = $pdo->prepare("
        UPDATE tl_approvals 
        SET approver_id = ?, status = ?, comment = ?, signature_path = ?, approved_at = NOW() 
        WHERE request_id = ? AND level = ?
    ");
    $stmtLog->execute([$approverId, $status, $comment, $sigPath, $requestId, $currentLevel]);

    if ($status == 2) {
        // กรณีไม่อนุมัติ -> จบทันที
        $stmtUpdate = $pdo->prepare("UPDATE tl_requests SET status = 'rejected' WHERE id = ?");
        $stmtUpdate->execute([$requestId]);
        $pdo->commit();
        $responseResult = ['status' => 'success', 'message' => 'ปฏิเสธใบลาเรียบร้อยแล้ว'];
    } elseif ($status == 1) {
        // กรณีอนุมัติ (Status = 1)
        // ถ้าเป็นระดับ 1 และไม่อนุญาตให้อนุมัติจบตรงๆ ให้เลื่อนระดับ
        if ($currentLevel < 2 && !$directFinal) {
            $nextLevel = $currentLevel + 1;
            $stmtUpdate = $pdo->prepare("UPDATE tl_requests SET level_at = ? WHERE id = ?");
            $stmtUpdate->execute([$nextLevel, $requestId]);

            // สร้างช่อง Log สำหรับระดับถัดไป (รออนุมัติ)
            $stmtNextLog = $pdo->prepare("INSERT INTO tl_approvals (request_id, level, approver_id, status) VALUES (?, ?, 0, 0)");
            $stmtNextLog->execute([$requestId, $nextLevel]);

            $pdo->commit();
            $responseResult = ['status' => 'success', 'message' => 'ตรวจสอบเรียบร้อยแล้วและส่งต่อให้ ผอ./รอง อนุมัติ'];
        } else {
            // อนุมัติขั้นสุดท้าย (ผอ. หรือ direct final)
            $wasFinalApproved = true;
            $stmtFinal = $pdo->prepare("UPDATE tl_requests SET status = 'approved', level_at = 2 WHERE id = ?");
            $stmtFinal->execute([$requestId]);

            // บันทึก approval level 2 ด้วยกรณี direct final จาก level 1
            if ($currentLevel < 2 && $directFinal) {
                $stmtFinalLog = $pdo->prepare("INSERT INTO tl_approvals (request_id, level, approver_id, status, comment, signature_path, approved_at) VALUES (?, 2, ?, 1, ?, ?, NOW())");
                $stmtFinalLog->execute([$requestId, $approverId, $comment, $sigPath]);
            }

            // อัปเดตสถิติ
            $fieldMap = [
                'sick'     => 'sick_taken',
                'personal' => 'personal_taken',
                'vacation' => 'vacation_taken',
                'other'    => 'other_taken'
            ];
            $field = $fieldMap[$request['leave_type']] ?? 'other_taken';

            // ตรวจว่ามี tl_stats หรือยัง
            $chkStat = $pdo->prepare("SELECT id FROM tl_stats WHERE user_id = ? AND fiscal_year = ?");
            $chkStat->execute([$request['user_id'], $request['fiscal_year']]);
            if ($chkStat->fetch()) {
                $stmtStat = $pdo->prepare("
                    UPDATE tl_stats 
                    SET $field = $field + ? 
                    WHERE user_id = ? AND fiscal_year = ?
                ");
                $stmtStat->execute([$request['days_count'], $request['user_id'], $request['fiscal_year']]);
            } else {
                $stmtInsStat = $pdo->prepare("
                    INSERT INTO tl_stats (user_id, fiscal_year, $field)
                    VALUES (?, ?, ?)
                ");
                $stmtInsStat->execute([$request['user_id'], $request['fiscal_year'], $request['days_count']]);
            }

            $pdo->commit();
            $responseResult = ['status' => 'success', 'message' => 'อนุมัติใบลาเสร็จสิ้นและปรับปรุงสถิติเรียบร้อยแล้ว'];
        }
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[LLW] approve_leave error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}

// 4. ส่งแจ้งเตือน Telegram
if (isset($requestId) && isset($status)) {
    try {
        $stmtSet = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
        $config  = $stmtSet ? $stmtSet->fetch() : null;

        $leaveChatId   = '';
        $leaveBotToken = '';
        try {
            $lcs = $pdo->query("SELECT leave_chat_id, leave_bot_token FROM wfh_system_settings LIMIT 1");
            if ($lcs && ($lRow = $lcs->fetch(PDO::FETCH_ASSOC))) {
                $leaveChatId   = (string)($lRow['leave_chat_id'] ?? '');
                $leaveBotToken = (string)($lRow['leave_bot_token'] ?? '');
            }
        } catch (\Throwable $ignore) {}

        $tgToken  = !empty($leaveBotToken) ? $leaveBotToken : ($config['telegram_token'] ?? '');
        $tgChatId = !empty($leaveChatId)   ? $leaveChatId   : ($config['admin_chat_id']  ?? '');

        if (!empty($tgToken) && !empty($tgChatId)) {
            $stmtInfo = $pdo->prepare("SELECT u.firstname, u.lastname, r.leave_type FROM tl_requests r LEFT JOIN llw_users u ON r.user_id = u.user_id WHERE r.id = ?");
            $stmtInfo->execute([$requestId]);
            $info = $stmtInfo->fetch();

            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'llw.krusuched.com';
            $leaveUrl = "{$scheme}://{$host}/teacher_leave/";

            if ($status == 1) {
                $statusText = $wasFinalApproved ? "✅ <b>อนุมัติเสร็จสิ้น (ผอ.)</b>" : "🔍 <b>ตรวจสอบแล้วถูกต้อง (ส่งต่อ ผอ.)</b>";
            } else {
                $statusText = "❌ <b>ไม่อนุมัติ/ตีกลับ</b>";
            }

            $approverName = $_SESSION['fullname'] ?? 'เจ้าหน้าที่';
            $typeMap   = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจส่วนตัว', 'vacation' => 'ลาพักผ่อน', 'maternity' => 'ลาคลอดบุตร', 'other' => 'ลาอื่นๆ'];
            $typeName  = $typeMap[$info['leave_type'] ?? ''] ?? 'ไม่ระบุ';
            $requesterName = trim(($info['firstname'] ?? '') . ' ' . ($info['lastname'] ?? ''));

            $msg  = "📢 <b>พิจารณาใบลา</b>\n";
            $msg .= "👤 ผู้ลา: " . $requesterName . "\n";
            $msg .= "📂 ประเภท: " . $typeName . "\n";
            $msg .= "⚖️ สถานะ: " . $statusText . "\n";
            $msg .= "โดย: " . $approverName . "\n";
            if (!empty($comment)) {
                $msg .= "📝 ความเห็น: " . $comment . "\n";
            }
            $msg .= "-------------------\n";

            if ($status == 1 && !$wasFinalApproved) {
                $msg .= "📍 รอการอนุมัติจาก ผอ./รองฯ\n";
                $msg .= "🔗 <a href=\"{$leaveUrl}\">กดที่นี่เพื่ออนุมัติ</a>";
            } elseif ($status == 1 && $wasFinalApproved) {
                $msg .= "🏁 อนุมัติเสร็จสิ้นเรียบร้อย";
            }

            sendTelegramMessage($tgToken, $tgChatId, $msg);
        }
    } catch (\Throwable $tgEx) {
        error_log("Telegram Error: " . $tgEx->getMessage());
    }
}

if ($responseResult) {
    echo json_encode($responseResult);
} else {
    echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
}
