<?php
/**
 * homeroom/api/save_homeroom.php
 * บันทึกหัวข้อโฮมรูม + เช็คชื่อนักเรียน + อัปโหลดรูปภาพ (Integrated API)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// POST Data
$classroom = $_POST['classroom'] ?? null;
$log_date = $_POST['log_date'] ?? null;
$topic = $_POST['topic'] ?? '';
$attendance = json_decode($_POST['attendance'] ?? '[]', true); // [{id: '12345', status: 'มา'}]
$user_id = $_SESSION['user_id'];

if (!$classroom || !$log_date) {
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

    // 2. บันทึก/อัปเดตการเช็คชื่อ (Sync back to assembly_attendance)
    if (!empty($attendance)) {
        // ดึงคาบเรียนปัจจุบัน (สมมติว่าเป็นคาบโฮมรูม หรือใช้รหัสที่ระบุ)
        $stmtAtt = $pdo->prepare("
            INSERT INTO assembly_attendance (student_id, date, status, classroom, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        foreach ($attendance as $att) {
            $stmtAtt->execute([$att['id'], $log_date, $att['status'], $classroom]);
        }
    }

    // 3. จัดการรูปภาพ (ถ้ามีอัปโหลดมา)
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/../../uploads/homeroom/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = 'hr_' . str_replace('/', '_', $classroom) . '_' . $log_date . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
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

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
