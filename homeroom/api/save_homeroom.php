<?php
/**
 * homeroom/api/save_homeroom.php
 * บันทึกหัวข้อโฮมรูม (homeroom_logs) + บันทึกการเช็คชื่อคาบโฮมรูมแยกใน hr_daily_attendance
 * (โดยไม่ไปเขียนทับข้อมูลเช็คชื่อเข้าแถวหน้าเสาธง assembly_attendance)
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

    // ดึง log_id ของ homeroom_logs
    $stmtId = $pdo->prepare("SELECT id FROM homeroom_logs WHERE classroom = ? AND log_date = ? LIMIT 1");
    $stmtId->execute([$classroom, $log_date]);
    $logId = (int)$stmtId->fetchColumn();

    // 2. บันทึกการเช็คชื่อเฉพาะของคาบโฮมรูมลงใน hr_daily_attendance
    //    (ไม่ไปเขียนทับตาราง assembly_attendance เพื่อรักษาประวัติการมาเข้าแถวตามจริง)
    $hasHrAttTable = (bool)$pdo->query("SHOW TABLES LIKE 'hr_daily_attendance'")->fetchColumn();
    if ($hasHrAttTable && $logId > 0 && is_array($attendance) && !empty($attendance)) {
        $allowedStatuses = ['มา', 'ขาด', 'สาย', 'ลา', 'โดด'];
        $stmtHrAtt = $pdo->prepare("
            INSERT INTO hr_daily_attendance (log_id, student_id, student_name, status)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                student_name = VALUES(student_name),
                status       = VALUES(status)
        ");

        foreach ($attendance as $att) {
            $sid   = llw_normalize_student_id($att['id'] ?? '');
            if ($sid === '') continue;
            $sName = trim($att['name'] ?? $sid);
            $st    = trim($att['status'] ?? 'มา');
            if (!in_array($st, $allowedStatuses, true)) {
                $st = 'มา';
            }
            $stmtHrAtt->execute([$logId, $sid, $sName, $st]);
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

    // 4. แจ้งเตือน Telegram เข้ากลุ่มเช็คชื่อนักเรียน (ใช้ sendAttendanceNotify)
    try {
        require_once __DIR__ . '/../../includes/telegram_bot.php';
        if (is_array($attendance) && !empty($attendance)) {
            $tcStmt = $pdo->prepare("
                SELECT CONCAT(u.firstname, ' ', u.lastname)
                FROM llw_class_advisors ca
                JOIN llw_users u ON u.user_id = ca.user_id
                WHERE ca.classroom = ?
                ORDER BY u.firstname
            ");
            $tcStmt->execute([$classroom]);
            $advisors    = $tcStmt->fetchAll(PDO::FETCH_COLUMN);
            $teacherName = !empty($advisors) ? implode(' / ', $advisors) : ($_SESSION['fullname'] ?? '-');

            $total      = count($attendance);
            $present    = count(array_filter($attendance, fn($r) => ($r['status'] ?? 'มา') === 'มา'));
            $absentList = array_filter($attendance, fn($r) => ($r['status'] ?? 'มา') !== 'มา');

            $thDate = date('j', strtotime($log_date));
            $monthNames = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                           'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            $month = (int)date('n', strtotime($log_date));
            $thDateStr = "$thDate {$monthNames[$month]} " . (date('Y', strtotime($log_date)) + 543);

            $safeRoom    = htmlspecialchars($classroom, ENT_QUOTES, 'UTF-8');
            $safeTeacher = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');

            $msg  = "🏫 <b>บันทึกกิจกรรมโฮมรูม & เช็คชื่อ</b>\n";
            $msg .= "📅 วันที่: $thDateStr\n";
            $msg .= "🏫 ห้อง: $safeRoom\n";
            $msg .= "👩‍🏫 ครูที่ปรึกษา: $safeTeacher\n";
            if ($topic !== '') {
                $msg .= "📝 เรื่องที่แจ้ง: " . htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') . "\n";
            }
            $msg .= "\n👥 นักเรียนทั้งหมด: $total คน\n";
            $msg .= "✅ มาเรียน: $present คน\n";

            if (count($absentList) > 0) {
                $msg .= "❌ ไม่มา/สาย:\n";
                foreach ($absentList as $a) {
                    $name = htmlspecialchars(trim($a['name'] ?? ($a['id'] ?? '')), ENT_QUOTES, 'UTF-8');
                    $st   = htmlspecialchars($a['status'] ?? '', ENT_QUOTES, 'UTF-8');
                    $msg .= "• $name ($st)\n";
                }
            } else {
                $msg .= "🎉 นักเรียนมาครบทุกคน!\n";
            }
            $msg .= "\n🕒 บันทึกเวลา: " . date('H:i:s');

            Telegram::sendAttendanceNotify($pdo, $msg);
        }
    } catch (\Throwable $tgErr) {
        error_log('[homeroom/save_homeroom] Telegram error: ' . $tgErr->getMessage());
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลโฮมรูมเรียบร้อยแล้ว (ไม่กระทบยอดเช็คชื่อเข้าแถว)']);

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
