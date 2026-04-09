<?php
// api/approve_action.php — อนุมัติ/ปฏิเสธคำขอออกนอกบริเวณ
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/database.php';
require_once '../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

// Role guard: เฉพาะ super_admin หรือ wfh_admin เท่านั้นที่อนุมัติได้
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์อนุมัติคำขอ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['r_id']) || !isset($input['status'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่สมบูรณ์']);
    exit;
}

try {
    $pdo = getPdo();
    $r_id   = (int)$input['r_id'];
    $status = (int)$input['status']; // 1=อนุมัติ, 2=ปฏิเสธ

    // อัปเดตสถานะ
    $stmt = $pdo->prepare("UPDATE leave_requests SET status_boss1 = ? WHERE r_id = ?");
    $stmt->execute([$status, $r_id]);

    // ดึงข้อมูลผู้ขอพร้อม telegram_chat_id
    $stmt_req = $pdo->prepare("
        SELECT r.*, 
               CONCAT(u.firstname,' ',u.lastname) AS t_name,
               t.telegram_chat_id
        FROM leave_requests r 
        JOIN llw_users u ON r.teacher_id = u.user_id
        LEFT JOIN wfh_users t ON t.username = u.username
        WHERE r.r_id = ?
    ");
    $stmt_req->execute([$r_id]);
    $request = $stmt_req->fetch();

    // ส่ง Telegram แจ้งผู้ขอ
    if ($request) {
        try {
            $sStmt = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
            $settings = $sStmt->fetch();

            if ($settings && $settings['telegram_token']) {
                // แจ้งผู้ขอ (ถ้ามี chat_id)
                if (!empty($request['telegram_chat_id'])) {
                    if ($status == 1) {
                        $msg = "✅ <b>คำขอออกนอกบริเวณได้รับอนุมัติแล้ว</b>\n";
                    } else {
                        $msg = "❌ <b>คำขอออกนอกบริเวณไม่ได้รับอนุมัติ</b>\n";
                    }
                    $msg .= "📝 เหตุผล: " . $request['reason'] . "\n";
                    $msg .= "⏰ เวลา: " . $request['time_start'] . " - " . $request['time_end'];
                    sendTelegramMessage($settings['telegram_token'], $request['telegram_chat_id'], $msg);
                }

                // แจ้งครูสอนแทน (กรณีอนุมัติและมีคาบการสอน)
                if ($status == 1 && $request['has_class']) {
                    $stmt_sub = $pdo->prepare("
                        SELECT rd.*, 
                               CONCAT(u.firstname,' ',u.lastname) AS sub_name,
                               w.telegram_chat_id AS sub_telegram
                        FROM leave_request_details rd
                        JOIN llw_users u ON rd.sub_teacher_id = u.user_id
                        LEFT JOIN wfh_users w ON w.username = u.username
                        WHERE rd.r_id = ?
                    ");
                    $stmt_sub->execute([$r_id]);
                    $substitutes = $stmt_sub->fetchAll();

                    foreach ($substitutes as $sub) {
                        if (!empty($sub['sub_telegram'])) {
                            $msg_sub  = "📢 <b>แจ้งเตือนการสอนแทน</b>\n";
                            $msg_sub .= "👤 " . $request['t_name'] . " ฝากสอนแทน\n";
                            $msg_sub .= "🗓 คาบที่: " . $sub['period'] . " วิชา: " . $sub['subject'] . "\n";
                            $msg_sub .= "📍 ชั้น: " . $sub['class_level'];
                            sendTelegramMessage($settings['telegram_token'], $sub['sub_telegram'], $msg_sub);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // ไม่ให้ Telegram error หยุดระบบ
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อยแล้ว']);

} catch (Exception $e) {
    error_log('[LLW] approve_action error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
?>
