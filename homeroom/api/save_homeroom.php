<?php
/**
 * homeroom/api/save_homeroom.php
 * บันทึกหัวข้อโฮมรูม + ซิงก์ข้อมูลเช็คชื่อกับระบบเช็คชื่อเข้าแถว (assembly_attendance) + อัปโหลดรูปภาพ
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$classroom  = trim($_POST['classroom'] ?? '');
$log_date   = trim($_POST['log_date'] ?? '');
$topic      = trim($_POST['topic'] ?? '');
$attendance = json_decode($_POST['attendance'] ?? '[]', true);
$sync_att   = ($_POST['sync_attendance'] ?? '1') === '1';
$user_id    = (int)($_SESSION['user_id'] ?? 0);

if ($classroom === '' || $log_date === '') {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. บันทึกหัวข้อกิจกรรม (Homeroom Topic)
    $stmt = $pdo->prepare("
        INSERT INTO homeroom_logs (classroom, log_date, topic, user_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE topic = VALUES(topic), user_id = VALUES(user_id)
    ");
    $stmt->execute([$classroom, $log_date, $topic, $user_id]);

    // 2. ซิงก์การเช็คชื่อเข้ากับตาราง assembly_attendance (เฉพาะกรณีที่ยังไม่มีการเช็คชื่อเข้าแถว หรือครูมีการปรับแก้สถานะ)
    if ($sync_att && is_array($attendance) && !empty($attendance)) {
        // แปลงสถานะเป็นรหัสมาตรฐานของตาราง assembly_attendance: ENUM('ม','ข','ล','ด')
        $toEnumMap = [
            'มา'  => 'ม',
            'ม'   => 'ม',
            'ขาด' => 'ข',
            'ข'   => 'ข',
            'ลา'  => 'ล',
            'ล'   => 'ล',
            'สาย' => 'ด',
            'โดด' => 'ด',
            'ด'   => 'ด',
        ];

        $stmtAtt = $pdo->prepare("
            INSERT INTO assembly_attendance (student_id, date, status, classroom, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                status     = VALUES(status),
                classroom  = VALUES(classroom),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($attendance as $att) {
            $sid = llw_normalize_student_id($att['id'] ?? '');
            if ($sid === '') continue;
            $rawStatus  = trim($att['status'] ?? 'มา');
            $enumStatus = $toEnumMap[$rawStatus] ?? 'ม';
            $stmtAtt->execute([$sid, $log_date, $enumStatus, $classroom, $user_id ?: null]);
        }
    }

    // 3. จัดการรูปภาพ (ถ้ามีอัปโหลดมา)
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['photo'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed, true)) {
            $uploadDir = __DIR__ . '/../../uploads/homeroom/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = 'hr_' . str_replace('/', '_', $classroom) . '_' . $log_date . '_' . uniqid() . '.jpg';
            $dest     = $uploadDir . $fileName;

            if (compressAndResizeImage($file['tmp_name'], $dest, 70, 1200)) {
                $pathForDb = '/uploads/homeroom/' . $fileName;
                $stmtPhoto = $pdo->prepare("
                    INSERT INTO homeroom_photos (classroom, log_date, image_path, user_id)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)
                ");
                $stmtPhoto->execute([$classroom, $log_date, $pathForDb, $user_id]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    llw_json_error('ไม่สามารถบันทึกข้อมูลโฮมรูมได้', $e, 500, 'homeroom/save_homeroom');
}

/**
 * ฟังก์ชันย่อขนาดและบีบอัดรูปภาพ
 */
function compressAndResizeImage($source, $destination, $quality = 70, $maxWidth = 1200) {
    $info = @getimagesize($source);
    if ($info === false) return false;

    $mime = $info['mime'];
    if ($mime === 'image/jpeg') $image = @imagecreatefromjpeg($source);
    elseif ($mime === 'image/png') $image = @imagecreatefrompng($source);
    elseif ($mime === 'image/webp') $image = @imagecreatefromwebp($source);
    elseif ($mime === 'image/gif') $image = @imagecreatefromgif($source);
    else return false;

    if (!$image) return false;

    $width  = $info[0];
    $height = $info[1];
    if ($width > $maxWidth) {
        $newWidth  = $maxWidth;
        $newHeight = (int)floor(($height / $width) * $maxWidth);
        $tmp       = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime === 'image/png' || $mime === 'image/gif') {
            $white = imagecolorallocate($tmp, 255, 255, 255);
            imagefill($tmp, 0, 0, $white);
        }

        imagecopyresampled($tmp, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $tmp;
    }

    $result = imagejpeg($image, $destination, $quality);
    imagedestroy($image);
    return $result;
}
