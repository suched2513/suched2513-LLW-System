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
        (teacher_id, req_date, reason, detail, time_start, time_end, total_hr, has_class, status_boss1) 
        VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, 0)");
    
    $stmt->execute([
        $teacher_id,
        $input['reason'] ?? '',
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

    // 3. ส่ง Telegram แจ้ง ผอ.
    $teacher_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 
                    (isset($_SESSION['firstname']) ? $_SESSION['firstname'] : 'อาจารย์');
    
    // ดึง settings จาก wfh_system_settings
    try {
        $sStmt = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
        $settings = $sStmt->fetch();
        
        if ($settings && $settings['telegram_token'] && $settings['admin_chat_id']) {
            $msg  = "🔔 <b>มีคำขอออกนอกบริเวณใหม่</b>\n";
            $msg .= "👤 ผู้ขอ: " . $teacher_name . "\n";
            $msg .= "📝 เหตุผล: " . ($input['reason'] ?? '') . "\n";
            $msg .= "⏰ เวลา: " . ($input['time_start'] ?? '') . " - " . ($input['time_end'] ?? '') . "\n";
            $msg .= "📅 วันที่: " . date('d/m/Y') . "\n";
            if (!empty($input['has_class'])) {
                $msg .= "📚 มีคาบการสอน: " . count($input['cart'] ?? []) . " คาบ\n";
            }
            $msg .= "🔗 กรุณาตรวจสอบในระบบ LLW";

            sendTelegramMessage($settings['telegram_token'], $settings['admin_chat_id'], $msg);
        }
    } catch (Exception $e) {
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
