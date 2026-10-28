<?php
// api/save_request.php — บันทึกคำขอออกนอกบริเวณ ใช้ llw_db
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/database.php';
require_once '../includes/telegram_bot.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

$pdo = getPdo();
$teacher_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. บันทึกลง leave_requests
    $stmt = $pdo->prepare("INSERT INTO leave_requests 
        (teacher_id, req_date, reason, reason_type, detail, time_start, time_end, total_hr, has_class, status_boss1) 
        VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 0)");
    
    $stmt->execute([
        $teacher_id,
        $input['reason'] ?? '',
        $input['reason_type'] ?? 'อื่นๆ',
        $input['detail'] ?? '',
        $input['time_start'] ?? '',
        $input['time_end'] ?? '',
        $input['total_hr'] ?? 0,
        ($input['has_class'] ?? false) ? 1 : 0
    ]);

    $request_id = $pdo->lastInsertId();

    // 2. บันทึก request_details ถ้ามีคาบการสอน
    if (!empty($input['has_class']) && !empty($input['cart'])) {
        $stmt_detail = $pdo->prepare("INSERT INTO leave_request_details 
            (r_id, period, subject, class_level, sub_teacher_id) 
            VALUES (?, ?, ?, ?, ?)");
        
        foreach ($input['cart'] as $item) {
            $stmt_detail->execute([
                $request_id,
                $item['period'] ?? '',
                $item['subject'] ?? '',
                $item['class_level'] ?? '',
                $item['sub_teacher_id'] ?? null
            ]);
        }
    }

    $pdo->commit();

    // 3. ส่ง Telegram แจ้ง ผอ. และกลุ่มการลา (พร้อมระบบ Fallback อัตโนมัติ)
    $teacher_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 
                    (isset($_SESSION['firstname']) ? $_SESSION['firstname'] : 'อาจารย์');
    
    try {
        $safeTName  = htmlspecialchars($teacher_name, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($input['reason'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeType   = htmlspecialchars($input['reason_type'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeStart  = htmlspecialchars($input['time_start'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeEnd    = htmlspecialchars($input['time_end'] ?? '', ENT_QUOTES, 'UTF-8');

        $msg  = "🔔 <b>มีคำขอออกนอกบริเวณใหม่</b>\n";
        $msg .= "👤 ผู้ขอ: {$safeTName}\n";
        $msg .= "📝 เหตุผล: {$safeReason} ({$safeType})\n";
        $msg .= "⏰ เวลา: {$safeStart} - {$safeEnd}\n";
        $msg .= "📅 วันที่: " . date('d/m/Y') . "\n";
        if (!empty($input['has_class'])) {
            $msg .= "📚 มีคาบการสอน: " . count($input['cart'] ?? []) . " คาบ\n";
        }
        $msg .= "🔗 กรุณาตรวจสอบในระบบ LLW";

        Telegram::sendLeaveNotify($pdo, $msg);
    } catch (\Throwable $e) {
        // ไม่ให้ Telegram error หยุดระบบ
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกคำขอเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[LLW] save_request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
}
?>
