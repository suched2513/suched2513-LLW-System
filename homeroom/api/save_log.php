<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$classroom = trim($input['classroom'] ?? '');
$log_date  = trim($input['log_date'] ?? '');
$topic     = trim($input['topic'] ?? '');
$user_id   = (int)($_SESSION['user_id'] ?? 0);

if ($classroom === '' || $log_date === '') {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("
        INSERT INTO homeroom_logs (classroom, log_date, topic, user_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE topic = VALUES(topic), user_id = VALUES(user_id)
    ");
    $stmt->execute([$classroom, $log_date, $topic, $user_id]);

    // แจ้งเตือน Telegram เมื่อบันทึกหัวข้อกิจกรรมโฮมรูม
    try {
        $checkerName = htmlspecialchars(trim((string)($_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? '-'))), ENT_QUOTES, 'UTF-8');
        $safeRoom    = htmlspecialchars($classroom, ENT_QUOTES, 'UTF-8');
        $safeTopic   = htmlspecialchars($topic !== '' ? $topic : '-', ENT_QUOTES, 'UTF-8');
        $msg  = "🏫 <b>บันทึกสมุดโฮมรูม</b>\n";
        $msg .= "📅 วันที่: " . htmlspecialchars($log_date, ENT_QUOTES, 'UTF-8') . "\n";
        $msg .= "🏫 ห้อง: {$safeRoom}\n";
        $msg .= "👩‍🏫 ผู้บันทึก: {$checkerName}\n";
        $msg .= "📝 เรื่องที่แจ้ง: {$safeTopic}\n";
        $msg .= "🕒 เวลา: " . date('H:i:s');
        Telegram::sendAttendanceNotify($pdo, $msg);
    } catch (\Throwable $ignore) {}

    echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
    error_log($e->getMessage());
}
