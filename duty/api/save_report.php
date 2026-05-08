<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$pdo = getPdo();
$assignmentId = (int)($_POST['assignment_id'] ?? 0);
$reportNote   = trim($_POST['report_note'] ?? '');
$photos       = $_FILES['photos'] ?? null;

// ── Auto-migration: เพิ่ม report_note ถ้ายังไม่มี ──
try {
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duty_reports' AND COLUMN_NAME = 'report_note'");
    $colCheck->execute();
    if ((int)$colCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE duty_reports ADD COLUMN report_note TEXT NULL AFTER teacher_id");
    }
} catch (Exception $e) {}

if (!$assignmentId || !$photos) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. ดึงข้อมูล assignment
    $stmtS = $pdo->prepare("SELECT * FROM duty_schedule WHERE id = ?");
    $stmtS->execute([$assignmentId]);
    $schedule = $stmtS->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) throw new Exception('ไม่พบข้อมูลตารางเวร');

    // 2. ค้นหาหรือสร้างหัวข้อรายงาน (duty_reports)
    $stmtR = $pdo->prepare("
        SELECT id FROM duty_reports 
        WHERE duty_date = ? AND shift = ? AND point_no = ?
        LIMIT 1
    ");
    $stmtR->execute([$schedule['duty_date'], $schedule['shift'], $schedule['point_no']]);
    $reportId = $stmtR->fetchColumn();

    if (!$reportId) {
        $stmtInsR = $pdo->prepare("
            INSERT INTO duty_reports (duty_date, shift, point_no, teacher_id, report_note, status)
            VALUES (?, ?, ?, ?, ?, 'partial')
        ");
        $stmtInsR->execute([$schedule['duty_date'], $schedule['shift'], $schedule['point_no'], $schedule['teacher_id'], $reportNote]);
        $reportId = $pdo->lastInsertId();
    } else {
        // อัปเดต note กรณีส่งเพิ่ม
        $pdo->prepare("UPDATE duty_reports SET report_note = ? WHERE id = ?")->execute([$reportNote, $reportId]);
    }

    // 3. จัดการอัปโหลดไฟล์
    $uploadDir = __DIR__ . '/../../uploads/reports/' . date('Y-m-d') . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $stmtPhoto = $pdo->prepare("
        INSERT INTO duty_report_photos (report_id, file_path, file_size, received_at)
        VALUES (?, ?, ?, NOW())
    ");

    $count = count($photos['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($photos['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = pathinfo($photos['name'][$i], PATHINFO_EXTENSION);
        $fileName = $reportId . '_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        $relativeDir = 'uploads/reports/' . date('Y-m-d') . '/';
        $dbPath = $relativeDir . $fileName;

        if (move_uploaded_file($photos['tmp_name'][$i], $targetPath)) {
            $stmtPhoto->execute([$reportId, $dbPath, $photos['size'][$i]]);
        }
    }

    // 4. อัปเดตสถานะรายงาน (ถ้ามีรูปครบตามกำหนด)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM duty_report_photos WHERE report_id = ? AND is_deleted = 0");
    $stmtCheck->execute([$reportId]);
    $photoCount = (int)$stmtCheck->fetchColumn();

    $stmtLimit = $pdo->query("SELECT svalue FROM duty_settings WHERE skey = 'photos_required_per_point'");
    $required = (int)($stmtLimit->fetchColumn() ?: 3);

    $status = ($photoCount >= $required) ? 'complete' : 'partial';
    $completedAt = ($status === 'complete') ? date('Y-m-d H:i:s') : null;

    $stmtUpdate = $pdo->prepare("
        UPDATE duty_reports SET status = ?, completed_at = COALESCE(?, completed_at)
        WHERE id = ?
    ");
    $stmtUpdate->execute([$status, $completedAt, $reportId]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกรายงานเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log($e->getMessage());
}
