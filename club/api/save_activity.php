<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
$content    = trim($_POST['content'] ?? '');

if ($session_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุ session_id']);
    exit;
}

try {
    $pdo = getPdo();

    // Get session + club teacher_id
    $stmt = $pdo->prepare("SELECT cs.id, cg.teacher_id FROM club_sessions cs JOIN club_groups cg ON cg.id = cs.club_id WHERE cs.id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบคาบนี้']);
        exit;
    }

    // Verify teacher ownership
    if ($_SESSION['llw_role'] === 'att_teacher') {
        $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
        if ((int)$session['teacher_id'] !== $teacherId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่ใช่ครูที่ปรึกษาของชุมนุมนี้']);
            exit;
        }
    }

    // Process uploaded photos
    $photoPaths = [];
    $existingLog = $pdo->prepare("SELECT id, photo_paths FROM club_activity_logs WHERE session_id = ? LIMIT 1");
    $existingLog->execute([$session_id]);
    $logRow = $existingLog->fetch(PDO::FETCH_ASSOC);

    if ($logRow && $logRow['photo_paths']) {
        $photoPaths = json_decode($logRow['photo_paths'], true) ?: [];
    }

    if (!empty($_FILES['photos']['name'])) {
        $allowedMime = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $uploadDir = __DIR__ . '/../../uploads/club/' . date('Y') . '/' . date('m') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $files = $_FILES['photos'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        $maxNew = 5 - count($photoPaths);

        for ($i = 0; $i < min($fileCount, $maxNew); $i++) {
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size    = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
            $error   = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];

            if ($error !== UPLOAD_ERR_OK || $size > $maxSize) continue;

            $mime = mime_content_type($tmpName);
            if (!in_array($mime, $allowedMime)) continue;

            $ext      = ($mime === 'image/png') ? 'png' : 'jpg';
            $filename = bin2hex(random_bytes(12)) . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $destPath)) {
                $photoPaths[] = 'uploads/club/' . date('Y') . '/' . date('m') . '/' . $filename;
            }
        }
    }

    $loggedBy  = (int)($_SESSION['teacher_id'] ?? 0);
    $photoJson = json_encode($photoPaths);

    if ($logRow) {
        $stmt = $pdo->prepare("UPDATE club_activity_logs SET content=?, photo_paths=?, logged_by=?, logged_at=NOW() WHERE id=?");
        $stmt->execute([$content, $photoJson, $loggedBy ?: null, $logRow['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO club_activity_logs (session_id, content, photo_paths, logged_by) VALUES (?,?,?,?)");
        $stmt->execute([$session_id, $content, $photoJson, $loggedBy ?: null]);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกกิจกรรมสำเร็จ', 'photo_count' => count($photoPaths)]);
} catch (Exception $e) {
    error_log('[save_activity] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
