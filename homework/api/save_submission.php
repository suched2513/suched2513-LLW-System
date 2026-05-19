<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['is_student'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$content       = trim($_POST['content'] ?? '');
$student_uid   = (int)$_SESSION['student_uid'];
$student_code  = $_SESSION['student_code']  ?? '';
$student_name  = $_SESSION['student_name']  ?? '';
$classroom     = $_SESSION['student_class'] ?? '';

if (!$assignment_id) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

// Process file uploads
$file_paths = [];
$upload_dir = __DIR__ . '/../../uploads/homework/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
    $allowed_mime = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $total = count($_FILES['files']['name']);
    if ($total > 3) {
        echo json_encode(['status' => 'error', 'message' => 'อัปโหลดไฟล์ได้สูงสุด 3 ไฟล์']);
        exit;
    }

    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        if ($_FILES['files']['size'][$i] > 10 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'แต่ละไฟล์ต้องไม่เกิน 10 MB']);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['files']['tmp_name'][$i]);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_mime, true)) {
            echo json_encode(['status' => 'error', 'message' => 'ไฟล์ประเภทนี้ไม่รองรับ (รองรับ: รูปภาพ, PDF, Word)']);
            exit;
        }

        $raw_ext  = pathinfo($_FILES['files']['name'][$i], PATHINFO_EXTENSION);
        $safe_ext = preg_replace('/[^a-zA-Z0-9]/', '', $raw_ext);
        $file_name = time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . ($safe_ext ? '.' . $safe_ext : '');
        $target    = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $file_paths[] = 'uploads/homework/' . $file_name;
        }
    }
}

if ($content === '' && empty($file_paths)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกเนื้อหาหรือแนบไฟล์']);
    exit;
}

$file_path_db = !empty($file_paths) ? json_encode($file_paths, JSON_UNESCAPED_UNICODE) : null;

try {
    $pdo = getPdo();

    $aStmt = $pdo->prepare("SELECT * FROM hw_assignments WHERE id = ? AND status = 'published' LIMIT 1");
    $aStmt->execute([$assignment_id]);
    $assignment = $aStmt->fetch();
    if (!$assignment) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบงาน']);
        exit;
    }

    $validClassrooms = array_map('trim', explode(',', $assignment['classroom']));
    if (!in_array($classroom, $validClassrooms, true)) {
        echo json_encode(['status' => 'error', 'message' => 'งานนี้ไม่ได้มอบหมายให้ห้องของคุณ']);
        exit;
    }

    $status = strtotime($assignment['due_date']) < time() ? 'late' : 'submitted';

    // COALESCE(VALUES(file_path), file_path) keeps old files if no new files uploaded
    $pdo->prepare("
        INSERT INTO hw_submissions
            (assignment_id, student_uid, student_code, student_name, classroom, content, file_path, submitted_at, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            content        = VALUES(content),
            file_path      = COALESCE(VALUES(file_path), file_path),
            submitted_at   = NOW(),
            status         = VALUES(status),
            score          = NULL,
            teacher_comment = NULL,
            reviewed_at    = NULL
    ")->execute([$assignment_id, $student_uid, $student_code, $student_name, $classroom, $content, $file_path_db, $status]);

    echo json_encode(['status' => 'success', 'late' => ($status === 'late')]);
} catch (Exception $e) {
    error_log('[homework] save_submission: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
