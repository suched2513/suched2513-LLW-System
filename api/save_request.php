<?php
// api/save_request.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/db_central.php';
require_once '../config/db_project.php';

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format.']);
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
    $pdo_project->beginTransaction();

    // 1. Save to `requests` table
    $stmt = $pdo_project->prepare("INSERT INTO requests 
        (teacher_id, req_date, reason, detail, time_start, time_end, total_hr, has_class, status_boss1, status_boss2) 
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 0, 0)");
    
    $stmt->execute([
        $teacher_id,
        $input['reason'],
        $input['detail'],
        $input['time_start'],
        $input['time_end'],
        $input['total_hr'],
        $input['has_class'] ? 1 : 0
    ]);

    $request_id = $pdo_project->lastInsertId();

    // 2. Save substitution items if `has_class` is true
    if ($input['has_class'] && !empty($input['cart'])) {
        $stmt_detail = $pdo_project->prepare("INSERT INTO request_details 
            (r_id, period, subject, class_level, sub_teacher_id) 
            VALUES (?, ?, ?, ?, ?)");
        
        foreach ($input['cart'] as $item) {
            $stmt_detail->execute([
                $request_id,
                $item['period'],
                $item['subject'],
                $item['class_level'],
                $item['sub_teacher_id']
            ]);
        }
    }

    $pdo_project->commit();

    // 3. Telegram Notification to Boss (ผอ.)
    $teacher_name = $_SESSION['user_name'] ?? 'อาจารย์';
    $msg = "🔔 <b>มีคำขอออกนอกบริเวณใหม่</b>\n";
    $msg .= "👤 ผู้ขอ: " . $teacher_name . "\n";
    $msg .= "📝 เหตุผล: " . $input['reason'] . "\n";
    $msg .= "⏰ เวลา: " . $input['time_start'] . " - " . $input['time_end'] . "\n";
    $msg .= "📅 วันที่: " . date('d/m/Y') . "\n";
    $msg .= "🔗 กรุณาตรวจสอบในระบบ";

    sendTelegram($msg, BOSS1_CHAT_ID);

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo_project->inTransaction()) {
        $pdo_project->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
