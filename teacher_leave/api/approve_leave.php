<?php
/**
 * teacher_leave/api/approve_leave.php
 * Level-based approval for leave requests
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';
require_once '../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$requestId    = $input['id'] ?? null;
$status       = $input['status'] ?? null; // 1 = approved, 2 = rejected
$comment      = $input['comment'] ?? '';
$signatureB64 = $input['signature'] ?? null; // Base64 PNG จากลายเซ็น ผอ.
$approverId   = $_SESSION['user_id'];

if (!$requestId || !$status) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

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

    $currentLevel = $request['level_at'];

    // 2. บันทึกลายเซ็นผู้อนุมัติ (ถ้ามี)
    $sigPath = null;
    if ($signatureB64 && str_contains($signatureB64, 'base64,')) {
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
        echo json_encode(['status' => 'success', 'message' => 'ปฏิเสธใบลาเรียบร้อยแล้ว']);
        exit;
    }

    // กรณีอนุมัติ (Status = 1)
    if ($currentLevel < 2) {
        // เลื่อนไประดับถัดไป (จากเจ้าหน้าที่ -> ผอ./รอง)
        $nextLevel = $currentLevel + 1;
        $stmtUpdate = $pdo->prepare("UPDATE tl_requests SET level_at = ? WHERE id = ?");
        $stmtUpdate->execute([$nextLevel, $requestId]);

        // สร้างช่อง Log สำหรับระดับถัดไป (รออนุมัติ)
        $stmtNextLog = $pdo->prepare("INSERT INTO tl_approvals (request_id, level, approver_id, status) VALUES (?, ?, 0, 0)");
        $stmtNextLog->execute([$requestId, $nextLevel]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ตรวจสอบเรียบร้อยแล้วและส่งต่อให้ ผอ./รอง อนุมัติ']);
    } else {
        // ระดับสุดท้ายอนุมัติแล้ว (ผอ.) -> อัปเดตสถานะหลักเป็น Approved และ อัปเดตสถิติ
        $stmtFinal = $pdo->prepare("UPDATE tl_requests SET status = 'approved' WHERE id = ?");
        $stmtFinal->execute([$requestId]);

        // อัปเดตสถิติ
        $fieldMap = [
            'sick'     => 'sick_taken',
            'personal' => 'personal_taken',
            'vacation' => 'vacation_taken',
            'other'    => 'other_taken'
        ];
        $field = $fieldMap[$request['leave_type']] ?? 'other_taken';

        $stmtStat = $pdo->prepare("
            UPDATE tl_stats 
            SET $field = $field + ? 
            WHERE user_id = ? AND fiscal_year = ?
        ");
        $stmtStat->execute([$request['days_count'], $request['user_id'], $request['fiscal_year']]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'อนุมัติใบลาเสร็จสิ้นและปรับปรุงสถิติเรียบร้อยแล้ว']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[LLW] approve_leave error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}

// 4. ส่งแจ้งเตือน Telegram (ถ้าดำเนินการสำเร็จ)
if (isset($requestId) && isset($status)) {
    try {
        $stmtSet = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
        $config = $stmtSet->fetch();

        if ($config && !empty($config['telegram_token']) && !empty($config['admin_chat_id'])) {
            // ดึงข้อมูลผู้ลาเพื่อแจ้งเตือน
            $stmtInfo = $pdo->prepare("SELECT u.firstname, u.lastname, r.leave_type FROM tl_requests r JOIN llw_users u ON r.user_id = u.user_id WHERE r.id = ?");
            $stmtInfo->execute([$requestId]);
            $info = $stmtInfo->fetch();
            
            if ($status == 1) {
                if ($currentLevel == 1) {
                    $statusText = "🔍 <b>ตรวจสอบแล้วถูกต้อง</b>";
                } else {
                    $statusText = "✅ <b>อนุมัติ</b>";
                }
            } else {
                $statusText = "❌ <b>ไม่อนุมัติ/ตีกลับ</b>";
            }

            $levelText = ['1' => 'เจ้าหน้าที่ตรวจสอบ', '2' => 'ผู้อำนวยการ/รองฯ'][$currentLevel] ?? 'ผู้ดูแลระบบ';
            $typeMap = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจส่วนตัว', 'vacation' => 'ลาพักผ่อน', 'maternity' => 'ลาคลอดบุตร', 'other' => 'ลาอื่นๆ'];
            $typeName = $typeMap[$info['leave_type']] ?? 'ไม่ระบุ';

            $msg = "📢 <b>พิจารณาใบลา</b>\n";
            $msg .= "👤 ผู้ลา: " . $info['firstname'] . " " . $info['lastname'] . "\n";
            $msg .= "📂 ประเภท: " . $typeName . "\n";
            $msg .= "⚖️ สถานะ: " . $statusText . "\n";
            $msg .= "โดย: " . $_SESSION['fullname'] . " (" . $levelText . ")\n";
            if (!empty($comment)) {
                $msg .= "📝 ความเห็น: " . $comment . "\n";
            }
            $msg .= "-------------------\n";
            
            if ($status == 1 && $currentLevel < 2) {
                $msg .= "📍 สถานะปัจจุบัน: รอการอนุมัติจาก ผอ./รองฯ";
            } elseif ($status == 1 && $currentLevel == 2) {
                $msg .= "🏁 สถานะปัจจุบัน: อนุมัติเสร็่จสิ้น";
            }

            sendTelegramMessage($config['telegram_token'], $config['admin_chat_id'], $msg);
        }
    } catch (Exception $tgEx) {
        error_log("Telegram Error: " . $tgEx->getMessage());
    }
}
