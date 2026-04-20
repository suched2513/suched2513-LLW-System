<?php
/**
 * API: Save behavior record (with optional image upload)
 * POST JSON: { date, teacher, type, activity, score, studentId, studentName, image (base64) }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$date        = trim($input['date'] ?? '');
$teacher     = trim($input['teacher'] ?? '');
$type        = trim($input['type'] ?? '');
$activity    = trim($input['activity'] ?? '');
$score       = (int)($input['score'] ?? 0);
$studentId   = trim($input['studentId'] ?? '');
$studentName = trim($input['studentName'] ?? '');
$imageBase64 = $input['imageBase64'] ?? null;
$imageName   = $input['imageName'] ?? null;
$imageType   = $input['imageType'] ?? null;

// Normalize student ID
if (preg_match('/^\d+$/', $studentId)) {
    $studentId = str_pad($studentId, 5, '0', STR_PAD_LEFT);
}

// Validate
if ($studentId === '' || $type === '' || $activity === '' || $score <= 0 || $date === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

if (!in_array($type, ['ความดี', 'ความผิด'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ประเภทไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();

    // Handle image upload (base64 → file)
    $imagePath = null;
    if ($imageBase64 && $imageName) {
        $uploadDir = __DIR__ . '/../../uploads/behavior/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate image type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($imageType && !in_array($imageType, $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ประเภทไฟล์ไม่รองรับ']);
            exit;
        }

        $ext = pathinfo($imageName, PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower($ext);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }

        $newFilename = 'beh_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $decoded = base64_decode($imageBase64, true);

        if ($decoded !== false && strlen($decoded) <= 5 * 1024 * 1024) { // max 5MB
            file_put_contents($uploadDir . $newFilename, $decoded);
            $imagePath = '/uploads/behavior/' . $newFilename;
        }
    }

    // Use teacher name from session if not provided
    if ($teacher === '' && isset($_SESSION['firstname'])) {
        $teacher = $_SESSION['firstname'] . ' ' . ($_SESSION['lastname'] ?? '');
    }

    $teacherUserId = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO beh_records (student_id, student_name, record_date, type, activity, score, teacher_name, teacher_user_id, image_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $studentId,
        $studentName,
        $date,
        $type,
        $activity,
        $score,
        $teacher,
        $teacherUserId,
        $imagePath,
    ]);

    // ── Smart Notifications (Telegram) ──
    $shouldNotify = ($type === 'ความดี' && $score >= 20) || ($type === 'ความผิด' && $score >= 10);
    if ($shouldNotify) {
        $settings = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1")->fetch();
        if (is_array($settings) && !empty($settings['telegram_token']) && !empty($settings['admin_chat_id'])) {
            $icon = ($type === 'ความดี') ? '🌟' : '🛑';
            $msg = "$icon <b>สรุปบันทึกพฤติกรรม</b>\n";
            $msg .= "👤 นักเรียน: $studentName ($studentId)\n";
            $msg .= "📝 ประเภท: $type\n";
            $msg .= "🔹 กิจกรรม: $activity\n";
            $msg .= "💎 คะแนน: " . (($type === 'ความดี') ? '+' : '-') . "$score\n";
            $msg .= "👨‍🏫 ผู้บันทึก: $teacher\n";
            $msg .= "📅 วันที่: " . date('d/m/Y', strtotime($date));
            
            sendTelegramMessage($settings['telegram_token'], $settings['admin_chat_id'], $msg);
        }
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกเรียบร้อย']);

} catch (Exception $e) {
    error_log('[behavior] save_record error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
