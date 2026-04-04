<?php
// api/approve_action.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/db_central.php';
require_once '../config/db_project.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['r_id']) || !isset($input['status'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
    exit;
}

function sendTelegram($msg, $chatId) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'HTML'
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

try {
    $r_id = (int)$input['r_id'];
    $status = (int)$input['status'];
    
    // Update status based on who approves (boss 1 or boss 2)
    $stmt = $pdo_project->prepare("UPDATE requests SET status_boss1 = ? WHERE r_id = ?");
    $stmt->execute([$status, $r_id]);

    // Fetch requester and substitutes to notify
    $stmt_req = $pdo_project->prepare("SELECT r.*, t.t_telegram_id, t.t_name FROM requests r JOIN school_central_db.teachers t ON r.teacher_id = t.t_id WHERE r.r_id = ?");
    $stmt_req->execute([$r_id]);
    $request = $stmt_req->fetch();

    if ($request && $status == 1) { // Approved
        // Notify Requester
        if ($request['t_telegram_id']) {
            $msg = "✅ <b>คำขอออกนอกบริเวณได้รับอนุมัติแล้ว</b>\n";
            $msg .= "👤 ผู้ขอ: " . $request['t_name'] . "\n";
            $msg .= "⏰ เวลา: " . $request['time_start'] . " - " . $request['time_end'] . "\n";
            sendTelegram($msg, $request['t_telegram_id']);
        }

        // Notify Substitutes
        $stmt_sub = $pdo_project->prepare("SELECT rd.*, t.t_telegram_id, t.t_name FROM request_details rd JOIN school_central_db.teachers t ON rd.sub_teacher_id = t.t_id WHERE rd.r_id = ?");
        $stmt_sub->execute([$r_id]);
        $substitutes = $stmt_sub->fetchAll();

        foreach ($substitutes as $sub) {
            if ($sub['t_telegram_id']) {
                $msg_sub = "📢 <b>แจ้งเตือนการสอนแทน</b>\n";
                $msg_sub .= "👤 " . $request['t_name'] . " ฝากสอนแทน\n";
                $msg_sub .= "🗓 คาบที่: " . $sub['period'] . " วิชา: " . $sub['subject'] . "\n";
                $msg_sub .= "📍 ชั้น: " . $sub['class_level'] . "\n";
                sendTelegram($msg_sub, $sub['t_telegram_id']);
            }
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะการอนุมัติเรียบร้อยแล้ว']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
