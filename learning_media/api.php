<?php
/**
 * learning_media/api.php — REST API for Learning Media Repository
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper to format Thai Buddhist date: DD/MM/BBBB
function toThaiDateString(?string $dateStr): string {
    if (!$dateStr) return '';
    $ts = strtotime($dateStr);
    if (!$ts) return $dateStr;
    $d = date('d', $ts);
    $m = date('m', $ts);
    $y = (int)date('Y', $ts) + 543;
    return "{$d}/{$m}/{$y}";
}

// Helper to handle image uploads safely
function handleCoverUpload(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new Exception('ขนาดไฟล์รูปภาพต้องไม่เกิน 3MB');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        throw new Exception('อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP, GIF)');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new Exception('ไฟล์รูปภาพไม่ถูกต้องตามรูปแบบ');
    }

    $uploadDir = __DIR__ . '/../uploads/learning_media/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'media_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์รูปภาพได้');
    }

    return '/uploads/learning_media/' . $filename;
}

// Helper to handle base64 image data (e.g. from canvas or mobile camera)
function handleCoverBase64(string $base64Data, ?string $originalName = null): ?string {
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        return null;
    }

    $ext = strtolower($type[1]);
    if ($ext === 'jpeg') $ext = 'jpg';
    $allowedExts = ['jpg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        throw new Exception('อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP, GIF)');
    }

    $data = substr($base64Data, strpos($base64Data, ',') + 1);
    $decoded = base64_decode($data);
    if ($decoded === false) {
        throw new Exception('ข้อมูลรูปภาพ base64 ไม่ถูกต้อง');
    }
    if (strlen($decoded) > 3 * 1024 * 1024) {
        throw new Exception('ขนาดไฟล์รูปภาพต้องไม่เกิน 3MB');
    }

    $uploadDir = __DIR__ . '/../uploads/learning_media/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'media_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (file_put_contents($targetPath, $decoded) === false) {
        throw new Exception('ไม่สามารถบันทึกไฟล์รูปภาพได้');
    }

    return '/uploads/learning_media/' . $filename;
}

try {
    $pdo = getPdo();

    // ─────────────────────────────────────────────────────────────
    // 1. LIST MEDIA
    // ─────────────────────────────────────────────────────────────
    if ($action === 'list') {
        $grade   = trim($_GET['grade'] ?? '');
        $subject = trim($_GET['subject'] ?? '');
        $search  = trim($_GET['search'] ?? '');

        $sql = "SELECT id, user_id, media_date, author, grade, subject, media_name, media_link, cover_url, views, created_at
                FROM learning_media
                WHERE status = 'active'";
        $params = [];

        if ($grade !== '') {
            $sql .= " AND grade = ?";
            $params[] = $grade;
        }
        if ($subject !== '') {
            $sql .= " AND subject = ?";
            $params[] = $subject;
        }
        if ($search !== '') {
            $sql .= " AND (media_name LIKE ? OR author LIKE ? OR grade LIKE ? OR subject LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mediaList = [];
        $totalViews = 0;
        foreach ($rows as $r) {
            $totalViews += (int)$r['views'];
            $mediaList[] = [
                'id'         => (int)$r['id'],
                'userId'     => $r['user_id'] ? (int)$r['user_id'] : null,
                'date'       => toThaiDateString($r['media_date']),
                'rawDate'    => $r['media_date'],
                'author'     => $r['author'],
                'grade'      => $r['grade'],
                'subject'    => $r['subject'],
                'mediaName'  => $r['media_name'],
                'mediaLink'  => $r['media_link'],
                'cover'      => $r['cover_url'] ?: 'https://cdn-icons-png.flaticon.com/512/847/847969.png',
                'views'      => (int)$r['views'],
                'createdAt'  => $r['created_at'],
            ];
        }

        // Summary counts by subject for filter tabs
        $subjStmt = $pdo->query("SELECT subject, COUNT(*) as cnt FROM learning_media WHERE status = 'active' GROUP BY subject ORDER BY subject ASC");
        $subjectsCount = $subjStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Distinct grades
        $gradeStmt = $pdo->query("SELECT DISTINCT grade FROM learning_media WHERE status = 'active' ORDER BY grade ASC");
        $gradesList = $gradeStmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'status'        => 'success',
            'data'          => $mediaList,
            'subjectsCount' => $subjectsCount,
            'gradesList'    => $gradesList,
            'totalCount'    => count($mediaList),
            'totalViews'    => $totalViews,
            'user'          => [
                'isLoggedIn' => isset($_SESSION['llw_role']),
                'role'       => $_SESSION['llw_role'] ?? null,
                'userId'     => $_SESSION['user_id'] ?? null,
                'name'       => $_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? null),
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. INCREMENT VIEW
    // ─────────────────────────────────────────────────────────────
    if ($action === 'view') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'รหัสสื่อไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE learning_media SET views = views + 1 WHERE id = ?");
        $stmt->execute([$id]);

        $vStmt = $pdo->prepare("SELECT views FROM learning_media WHERE id = ?");
        $vStmt->execute([$id]);
        $newViews = (int)$vStmt->fetchColumn();

        echo json_encode(['status' => 'success', 'views' => $newViews]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 3. SAVE NEW MEDIA (Requires login)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'save') {
        if (!isset($_SESSION['llw_role'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนเพิ่มสื่อการเรียนรู้']);
            exit;
        }

        $allowedRoles = ['super_admin', 'att_teacher', 'wfh_admin', 'wfh_staff', 'club_admin'];
        if (!in_array($_SESSION['llw_role'], $allowedRoles, true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการเพิ่มสื่อการเรียนรู้']);
            exit;
        }

        $mediaDate = trim($_POST['date'] ?? date('Y-m-d'));
        $author    = trim($_POST['author'] ?? ($_SESSION['fullname'] ?? 'ครูโรงเรียนละลมวิทยา'));
        $grade     = trim($_POST['grade'] ?? '');
        $subject   = trim($_POST['subject'] ?? '');
        $mediaName = trim($_POST['mediaName'] ?? '');
        $mediaLink = trim($_POST['mediaLink'] ?? '');
        $coverUrl  = trim($_POST['coverUrl'] ?? '');

        if (!$grade || !$subject || !$mediaName || !$mediaLink) {
            echo json_encode(['status' => 'fail', 'msg' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }

        // Handle uploaded file or base64
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverUrl = handleCoverUpload($_FILES['cover']);
        } elseif (!empty($_POST['coverBase64'])) {
            $coverUrl = handleCoverBase64($_POST['coverBase64'], $_POST['coverName'] ?? null);
        }

        if (!$coverUrl) {
            $coverUrl = 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
        }

        $userId = $_SESSION['user_id'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO learning_media (user_id, media_date, author, grade, subject, media_name, media_link, cover_url, views, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'active')
        ");
        $stmt->execute([$userId, $mediaDate, $author, $grade, $subject, $mediaName, $mediaLink, $coverUrl]);

        echo json_encode(['status' => 'success', 'message' => 'บันทึกสื่อการเรียนรู้เรียบร้อยแล้ว']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 4. UPDATE MEDIA (Requires login & permission)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'update') {
        if (!isset($_SESSION['llw_role'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบรหัสสื่อที่ต้องการแก้ไข']);
            exit;
        }

        // Check ownership or super_admin
        $checkStmt = $pdo->prepare("SELECT user_id, author, cover_url FROM learning_media WHERE id = ? AND status = 'active'");
        $checkStmt->execute([$id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบข้อมูลสื่อ']);
            exit;
        }

        $isSuperAdmin = $_SESSION['llw_role'] === 'super_admin';
        $isOwner = (isset($_SESSION['user_id']) && (int)$existing['user_id'] === (int)$_SESSION['user_id']) ||
                   (isset($_SESSION['fullname']) && trim($existing['author']) === trim($_SESSION['fullname']));

        if (!$isSuperAdmin && !$isOwner) {
            http_response_code(403);
            echo json_encode(['status' => 'fail', 'msg' => 'คุณสามารถแก้ไขได้เฉพาะสื่อที่คุณเป็นผู้จัดทำเท่านั้น']);
            exit;
        }

        $mediaDate = trim($_POST['date'] ?? date('Y-m-d'));
        $author    = trim($_POST['author'] ?? $existing['author']);
        $grade     = trim($_POST['grade'] ?? '');
        $subject   = trim($_POST['subject'] ?? '');
        $mediaName = trim($_POST['mediaName'] ?? '');
        $mediaLink = trim($_POST['mediaLink'] ?? '');
        $coverUrl  = trim($_POST['cover'] ?? $existing['cover_url']);

        if (!$grade || !$subject || !$mediaName || !$mediaLink) {
            echo json_encode(['status' => 'fail', 'msg' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }

        // Handle uploaded file or base64 if provided
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverUrl = handleCoverUpload($_FILES['cover']);
        } elseif (!empty($_POST['coverBase64'])) {
            $coverUrl = handleCoverBase64($_POST['coverBase64'], $_POST['coverName'] ?? null);
        }

        $stmt = $pdo->prepare("
            UPDATE learning_media
            SET media_date = ?, author = ?, grade = ?, subject = ?, media_name = ?, media_link = ?, cover_url = ?
            WHERE id = ?
        ");
        $stmt->execute([$mediaDate, $author, $grade, $subject, $mediaName, $mediaLink, $coverUrl, $id]);

        echo json_encode(['status' => 'success', 'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 5. DELETE MEDIA (Requires login & permission)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        if (!isset($_SESSION['llw_role'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบรหัสสื่อที่ต้องการลบ']);
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT user_id, author FROM learning_media WHERE id = ?");
        $checkStmt->execute([$id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบข้อมูลสื่อ']);
            exit;
        }

        $isSuperAdmin = $_SESSION['llw_role'] === 'super_admin';
        $isOwner = (isset($_SESSION['user_id']) && (int)$existing['user_id'] === (int)$_SESSION['user_id']) ||
                   (isset($_SESSION['fullname']) && trim($existing['author']) === trim($_SESSION['fullname']));

        if (!$isSuperAdmin && !$isOwner) {
            http_response_code(403);
            echo json_encode(['status' => 'fail', 'msg' => 'คุณสามารถลบได้เฉพาะสื่อที่คุณเป็นผู้จัดทำเท่านั้น']);
            exit;
        }

        // Soft delete
        $stmt = $pdo->prepare("UPDATE learning_media SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['status' => 'success', 'message' => 'ลบสื่อการเรียนรู้เรียบร้อยแล้ว']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
} catch (Exception $e) {
    error_log('[Learning Media API Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'msg' => $e->getMessage()]);
}
