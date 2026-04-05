<?php
session_start();
require_once '../config.php';
require_once '../includes/telegram_bot.php';

header('Content-Type: application/json');

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit();
}

$user_id = $_SESSION['user_id'];
$username_session = $_SESSION['username'];
$fullname_session = $_SESSION['fullname'];
$action  = $_POST['action'] ?? '';
$lat     = $_POST['lat']    ?? null;
$lng     = $_POST['lng']    ?? null;
$photo_b64 = $_POST['photo'] ?? null;
$today   = date('Y-m-d');
$now     = date('H:i:s');

// --- 1. ดึงการตั้งค่าระบบ ---
$settings  = $conn->query("SELECT * FROM wfh_system_settings LIMIT 1")->fetch_assoc();
$late_time = $settings['late_time'] ?? '08:30:00';
$school_lat = $settings['school_lat'] ?? '15.1182';
$school_lng = $settings['school_lng'] ?? '104.2239';
$geofence_radius = $settings['geofence_radius'] ?? 200; // เมตร
$telegram_token = $settings['telegram_token'] ?? '';
$admin_chat_id = $settings['admin_chat_id'] ?? '';

// --- 2. ฟังก์ชันคำนวณระยะทาง (Haversine Formula) ---
function calcDistance($lat1, $lon1, $lat2, $lon2) {
    if (($lat1 == $lat2) && ($lon1 == $lon2)) return 0;
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return ($miles * 1.609344) * 1000; // แปลงเป็นเมตร
}

// เช็ค Geofence (เฉพาะเมื่อมีพิกัดส่งมา)
if ($lat && $lng) {
    $distance = calcDistance((float)$lat, (float)$lng, (float)$school_lat, (float)$school_lng);
    if ($distance > $geofence_radius) {
        // ส่ง Telegram แจ้งเตือนแอดมินว่ามีการพยายามลงเวลานอกพื้นที่
        if (!empty($telegram_token) && !empty($admin_chat_id)) {
            $bot = new TelegramBot($telegram_token, $admin_chat_id);
            $msg = "⚠️ <b>แจ้งเตือนการลงเวลานอกพื้นที่</b>\n";
            $msg .= "👤: " . $fullname_session . "\n";
            $msg .= "📍: ห่างจากจุดกำหนด " . round($distance) . " เมตร\n";
            $msg .= "⏰: " . $today . " " . $now;
            $bot->sendMessage($msg);
        }
        
        echo json_encode(['success' => false, 'message' => 'คุณอยู่นอกพื้นที่ทำงาน (ห่าง ' . round($distance) . ' เมตร)']);
        exit();
    }
} else {
    // กรณีไม่มี GPS (อนุญาตให้ลงเวลาได้สำหรับช่วงทดสอบ)
    $distance = null;
}

// --- 3. ฟังก์ชันบันทึกรูปภาพจาก Base64 เป็นไฟล์ ---
function saveBase64Image($base64_string, $folder) {
    if (empty($base64_string)) return null;
    
    // ตรวจสอบและสร้างโฟลเดอร์ถ้ายังไม่มี
    $target_dir = "../uploads/" . $folder . "/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $image_parts = explode(";base64,", $base64_string);
    $image_type_aux = explode("image/", $image_parts[0]);
    $image_type = $image_type_aux[1] ?? 'png';
    $image_base64 = base64_decode($image_parts[1]);
    
    $file_name = uniqid() . '_' . time() . '.' . $image_type;
    $file_path = $target_dir . $file_name;
    
    file_put_contents($file_path, $image_base64);
    return "uploads/" . $folder . "/" . $file_name; // คืนค่า path ที่จะเก็บใน DB
}

// --- 4. ดำเนินการลงเวลา ---
// ดึง log วันนี้
$stmt = $conn->prepare("SELECT * FROM wfh_timelogs WHERE user_id = ? AND log_date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($action === 'checkin') {
    if ($log && $log['check_in_time']) {
        echo json_encode(['success' => false, 'message' => 'คุณได้ลงเวลาเข้างานแล้ววันนี้']);
        exit();
    }

    $status = ($now > $late_time) ? 'มาสาย' : 'ปกติ';
    $photo_path = saveBase64Image($photo_b64, 'check_in');

    if ($log) {
        $stmt = $conn->prepare("UPDATE wfh_timelogs SET check_in_time=?, check_in_lat=?, check_in_lng=?, check_in_photo=?, check_in_status=? WHERE log_id=?");
        $stmt->bind_param("sssssi", $now, $lat, $lng, $photo_path, $status, $log['log_id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO wfh_timelogs (user_id, log_date, check_in_time, check_in_lat, check_in_lng, check_in_photo, check_in_status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssss", $user_id, $today, $now, $lat, $lng, $photo_path, $status);
    }

    if ($stmt->execute()) {
        // แจ้ง Telegram
        if (!empty($telegram_token) && !empty($admin_chat_id)) {
            $bot = new TelegramBot($telegram_token, $admin_chat_id);
            $msg = "✅ <b>ลงเวลาเข้างาน</b>\n";
            $msg .= "👤: " . $fullname_session . "\n";
            $msg .= "⏰: " . $now . " (" . $status . ")";
            $bot->sendMessage($msg);
        }

        $msg = ($status === 'มาสาย') ? "ลงเวลาเข้างานสำเร็จ ($now) - สถานะ: มาสาย" : "ลงเวลาเข้างานสำเร็จ ($now) - สถานะ: ปกติ";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก']);
    }
    $stmt->close();

} elseif ($action === 'checkout') {
    if (!$log || !$log['check_in_time']) {
        echo json_encode(['success' => false, 'message' => 'ยังไม่ได้ลงเวลาเข้างาน']);
        exit();
    }
    if ($log['check_out_time']) {
        echo json_encode(['success' => false, 'message' => 'คุณได้ลงเวลาออกงานแล้ววันนี้']);
        exit();
    }

    $photo_path = saveBase64Image($photo_b64, 'check_out');

    $stmt = $conn->prepare("UPDATE wfh_timelogs SET check_out_time=?, check_out_lat=?, check_out_lng=?, check_out_photo=? WHERE log_id=?");
    $stmt->bind_param("ssssi", $now, $lat, $lng, $photo_path, $log['log_id']);

    if ($stmt->execute()) {
        // แจ้ง Telegram
        if (!empty($telegram_token) && !empty($admin_chat_id)) {
            $bot = new TelegramBot($telegram_token, $admin_chat_id);
            $msg = "👋 <b>ลงเวลาออกงาน</b>\n";
            $msg .= "👤: " . $fullname_session . "\n";
            $msg .= "⏰: " . $now;
            $bot->sendMessage($msg);
        }

        echo json_encode(['success' => true, 'message' => "ลงเวลาออกงานสำเร็จ ($now)"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก']);
    }
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'คำสั่งไม่ถูกต้อง']);
}
?>
