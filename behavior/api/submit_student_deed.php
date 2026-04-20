<?php
/**
 * API: Submit student good deed (pending approval)
 * POST multipart/form-data: { student_id, date, activity, score, file }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$sid      = trim($_POST['student_id'] ?? '');
$date     = $_POST['date'] ?? date('Y-m-d');
$activity = trim($_POST['activity'] ?? '');
$score    = (int)($_POST['score'] ?? 5);

if ($sid === '' || $activity === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

// Normalize SID
if (preg_match('/^\d+$/', $sid)) {
    $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
}

try {
    $pdo = getPdo();
    
    // Get student info from Master (att_students)
    $stmt = $pdo->prepare("SELECT name, classroom FROM att_students WHERE student_id = ?");
    $stmt->execute([$sid]);
    $student = $stmt->fetch();
    if (!$student) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสนักเรียนในระบบ']);
        exit;
    }

    // Parse level/room from classroom (e.g. "ม.2/2", "3/1")
    $className = $student['classroom'] ?? '';
    $level = '';
    $room = '';
    if (preg_match('/(\d+)\/(\d+)/', $className, $matches)) {
        $level = $matches[1];
        $room = $matches[2];
    }

    $dbPath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/beh_deeds/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fileName = 'deed_' . $sid . '_' . time() . '.' . $extension;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
            $dbPath = '/uploads/beh_deeds/' . $fileName;
        }
    }

    // Insert into beh_records with status='pending'
    $sql = "INSERT INTO beh_records 
            (student_id, student_name, record_date, type, activity, score, image_path, status, teacher_name) 
            VALUES (?, ?, ?, 'ความดี', ?, ?, ?, 'pending', 'รอยืนยันโดยครูที่ปรึกษา')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sid, $student['name'], $date, $activity, $score, $dbPath]);

    // Send Telegram Notification
    try {
        $set = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1")->fetch();
        if ($set && !empty($set['telegram_token']) && !empty($set['admin_chat_id'])) {
            $msg = "📢 <b>มีบันทึกความดีใหม่รอยืนยัน</b>\n";
            $msg .= "---------------------------\n";
            $msg .= "👤 <b>นักเรียน:</b> " . $student['name'] . " (ม." . $level . "/" . $room . ")\n";
            $msg .= "📝 <b>กิจกรรม:</b> " . $activity . "\n";
            $msg .= "⭐ <b>คะแนนที่เสนอ:</b> +" . $score . "\n";
            $msg .= "---------------------------\n";
            $msg .= "🔗 <a href='https://llw.krusuched.com/behavior/dashboard.php'>เข้าสู่ระบบเพื่อตรวจสอบ</a>";
            
            sendTelegramMessage($set['telegram_token'], $set['admin_chat_id'], $msg);
        }
    } catch (Exception $e) {
        error_log('[behavior] telegram notify error: ' . $e->getMessage());
    }

    echo json_encode(['status' => 'success', 'message' => 'ส่งบันทึกความดีเรียบร้อยแล้ว รอครูที่ปรึกษาตรวจสอบ']);

} catch (Exception $e) {
    error_log('[behavior] submit_student_deed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึก']);
}
