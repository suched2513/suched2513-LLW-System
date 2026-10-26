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

try {
    $pdo = getPdo();

    // ── Self-healing: Ensure table and enhanced columns exist ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS learning_media (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NULL,
            media_date   DATE NOT NULL,
            author       VARCHAR(150) NOT NULL,
            grade        VARCHAR(50) NOT NULL,
            subject      VARCHAR(100) NOT NULL,
            media_type   VARCHAR(50) NOT NULL DEFAULT 'slide',
            media_name   VARCHAR(255) NOT NULL,
            media_link   TEXT NOT NULL,
            objective    TEXT NULL,
            usage_results TEXT NULL,
            cover_url    TEXT NULL,
            views        INT NOT NULL DEFAULT 0,
            rating_avg   DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            rating_count INT NOT NULL DEFAULT 0,
            status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_media_date (media_date),
            INDEX idx_grade (grade),
            INDEX idx_subject (subject),
            INDEX idx_user_id (user_id),
            INDEX idx_views (views)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS learning_media_ratings (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            media_id       INT NOT NULL,
            rating         TINYINT NOT NULL DEFAULT 5,
            feedback       TEXT NULL,
            evaluator_name VARCHAR(100) NULL,
            evaluator_role VARCHAR(50) NOT NULL DEFAULT 'student',
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lmr_media (media_id),
            INDEX idx_lmr_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Check if columns exist on previously created table
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM learning_media")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('media_type', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN media_type VARCHAR(50) NOT NULL DEFAULT 'slide'");
        if (!in_array('objective', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN objective TEXT NULL");
        if (!in_array('usage_results', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN usage_results TEXT NULL");
        if (!in_array('rating_avg', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0.00");
        if (!in_array('rating_count', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN rating_count INT NOT NULL DEFAULT 0");

        if (!in_array('academic_head_approved', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_approved TINYINT(1) NOT NULL DEFAULT 0");
        if (!in_array('academic_head_name', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_name VARCHAR(100) NOT NULL DEFAULT 'นายเฉลิมชัย ศรีชัย'");
        if (!in_array('academic_head_comment', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_comment VARCHAR(255) NULL DEFAULT 'ทราบ และเห็นควรอนุมัติ'");
        if (!in_array('academic_head_at', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_at DATETIME NULL");
        if (!in_array('academic_head_signature', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_signature LONGTEXT NULL");

        if (!in_array('director_approved', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_approved TINYINT(1) NOT NULL DEFAULT 0");
        if (!in_array('director_name', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_name VARCHAR(100) NOT NULL DEFAULT 'นายสถาน ปรางมาศ'");
        if (!in_array('director_comment', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_comment VARCHAR(255) NULL DEFAULT 'ทราบ และอนุมัติ'");
        if (!in_array('director_at', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_at DATETIME NULL");
        if (!in_array('director_signature', $cols, true)) $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_signature LONGTEXT NULL");
    } catch (Exception $e) {}

    // ─────────────────────────────────────────────────────────────
    // 1. LIST MEDIA
    // ─────────────────────────────────────────────────────────────
    if ($action === 'list') {
        $grade     = trim($_GET['grade'] ?? '');
        $subject   = trim($_GET['subject'] ?? '');
        $mediaType = trim($_GET['mediaType'] ?? '');
        $search    = trim($_GET['search'] ?? '');

        $sql = "SELECT id, user_id, media_date, author, grade, subject, media_type, media_name, media_link,
                       objective, usage_results, cover_url, views, rating_avg, rating_count,
                       academic_head_approved, academic_head_name, academic_head_comment, academic_head_at, academic_head_signature,
                       director_approved, director_name, director_comment, director_at, director_signature,
                       created_at
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
        if ($mediaType !== '') {
            $sql .= " AND media_type = ?";
            $params[] = $mediaType;
        }
        if ($search !== '') {
            $sql .= " AND (media_name LIKE ? OR author LIKE ? OR grade LIKE ? OR subject LIKE ? OR objective LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
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
                'id'           => (int)$r['id'],
                'userId'       => $r['user_id'] ? (int)$r['user_id'] : null,
                'date'         => toThaiDateString($r['media_date']),
                'rawDate'      => $r['media_date'],
                'author'       => $r['author'],
                'grade'        => $r['grade'],
                'subject'      => $r['subject'],
                'mediaType'    => $r['media_type'] ?: 'slide',
                'mediaName'    => $r['media_name'],
                'mediaLink'    => $r['media_link'],
                'objective'    => $r['objective'] ?? '',
                'usageResults' => $r['usage_results'] ?? '',
                'cover'        => $r['cover_url'] ?: 'https://cdn-icons-png.flaticon.com/512/847/847969.png',
                'views'        => (int)$r['views'],
                'ratingAvg'            => (float)($r['rating_avg'] ?? 0),
                'ratingCount'          => (int)($r['rating_count'] ?? 0),
                'academicHeadApproved' => (int)($r['academic_head_approved'] ?? 0),
                'academicHeadName'     => $r['academic_head_name'] ?: 'นายเฉลิมชัย ศรีชัย',
                'academicHeadComment'  => $r['academic_head_comment'] ?: 'ทราบ และเห็นควรอนุมัติ',
                'academicHeadAt'       => $r['academic_head_at'] ? toThaiDateString(substr($r['academic_head_at'], 0, 10)) : null,
                'academicHeadSig'      => $r['academic_head_signature'],
                'directorApproved'     => (int)($r['director_approved'] ?? 0),
                'directorName'         => $r['director_name'] ?: 'นายสถาน ปรางมาศ',
                'directorComment'      => $r['director_comment'] ?: 'ทราบ และอนุมัติ',
                'directorAt'           => $r['director_at'] ? toThaiDateString(substr($r['director_at'], 0, 10)) : null,
                'directorSig'          => $r['director_signature'],
                'createdAt'            => $r['created_at'],
            ];
        }

        // Summary counts by subject
        $subjStmt = $pdo->query("SELECT subject, COUNT(*) as cnt FROM learning_media WHERE status = 'active' GROUP BY subject ORDER BY subject ASC");
        $subjectsCount = $subjStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Summary counts by media_type
        $typeStmt = $pdo->query("SELECT media_type, COUNT(*) as cnt FROM learning_media WHERE status = 'active' GROUP BY media_type");
        $typesCount = $typeStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Distinct grades
        $gradeStmt = $pdo->query("SELECT DISTINCT grade FROM learning_media WHERE status = 'active' ORDER BY grade ASC");
        $gradesList = $gradeStmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'status'        => 'success',
            'data'          => $mediaList,
            'subjectsCount' => $subjectsCount,
            'typesCount'    => $typesCount,
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
    // 3. RATE MEDIA (แบบประเมินความพึงพอใจ 1-5 ดาว)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'rate') {
        $mediaId   = (int)($_POST['media_id'] ?? 0);
        $rating    = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $feedback  = trim($_POST['feedback'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $role      = $_SESSION['llw_role'] ?? 'student';

        if (!$name) {
            $name = $_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? 'ผู้ประเมิน');
        }

        if ($mediaId <= 0) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบรหัสสื่อการเรียนรู้']);
            exit;
        }

        // Insert rating
        $rStmt = $pdo->prepare("
            INSERT INTO learning_media_ratings (media_id, rating, feedback, evaluator_name, evaluator_role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $rStmt->execute([$mediaId, $rating, $feedback ?: null, $name, $role]);

        // Recalculate average and count on learning_media
        $calcStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt, AVG(rating) as avg_score
            FROM learning_media_ratings
            WHERE media_id = ?
        ");
        $calcStmt->execute([$mediaId]);
        $calc = $calcStmt->fetch(PDO::FETCH_ASSOC);

        $newCount = (int)($calc['cnt'] ?? 0);
        $newAvg   = round((float)($calc['avg_score'] ?? 0), 2);

        $upStmt = $pdo->prepare("UPDATE learning_media SET rating_avg = ?, rating_count = ? WHERE id = ?");
        $upStmt->execute([$newAvg, $newCount, $mediaId]);

        echo json_encode([
            'status'      => 'success',
            'message'     => 'ขอบคุณสำหรับการประเมินสื่อการเรียนรู้',
            'ratingAvg'   => $newAvg,
            'ratingCount' => $newCount,
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 4. GET RATINGS FOR A MEDIA ITEM
    // ─────────────────────────────────────────────────────────────
    if ($action === 'get_ratings') {
        $mediaId = (int)($_GET['media_id'] ?? 0);
        if ($mediaId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสสื่อ']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT rating, feedback, evaluator_name, evaluator_role, created_at
            FROM learning_media_ratings
            WHERE media_id = ?
            ORDER BY id DESC
            LIMIT 50
        ");
        $stmt->execute([$mediaId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'reviews' => $reviews], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 5. SAVE NEW MEDIA (Requires login)
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

        $mediaDate    = trim($_POST['date'] ?? date('Y-m-d'));
        $author       = trim($_POST['author'] ?? ($_SESSION['fullname'] ?? 'ครูโรงเรียนละลมวิทยา'));
        $grade        = trim($_POST['grade'] ?? '');
        $subject      = trim($_POST['subject'] ?? '');
        $mediaType    = trim($_POST['mediaType'] ?? 'slide');
        $mediaName    = trim($_POST['mediaName'] ?? '');
        $mediaLink    = trim($_POST['mediaLink'] ?? '');
        $objective    = trim($_POST['objective'] ?? '');
        $usageResults = trim($_POST['usageResults'] ?? '');
        $coverUrl     = trim($_POST['coverUrl'] ?? '');

        if (!$grade || !$subject || !$mediaName || !$mediaLink) {
            echo json_encode(['status' => 'fail', 'msg' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }

        // Handle uploaded file
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverUrl = handleCoverUpload($_FILES['cover']);
        }

        if (!$coverUrl) {
            $coverUrl = 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
        }

        $userId = $_SESSION['user_id'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO learning_media (user_id, media_date, author, grade, subject, media_type, media_name, media_link, objective, usage_results, cover_url, views, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'active')
        ");
        $stmt->execute([$userId, $mediaDate, $author, $grade, $subject, $mediaType, $mediaName, $mediaLink, $objective ?: null, $usageResults ?: null, $coverUrl]);

        echo json_encode(['status' => 'success', 'message' => 'บันทึกสื่อการเรียนรู้เรียบร้อยแล้ว']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 6. UPDATE MEDIA (Requires login & permission)
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

        $mediaDate    = trim($_POST['date'] ?? date('Y-m-d'));
        $author       = trim($_POST['author'] ?? $existing['author']);
        $grade        = trim($_POST['grade'] ?? '');
        $subject      = trim($_POST['subject'] ?? '');
        $mediaType    = trim($_POST['mediaType'] ?? 'slide');
        $mediaName    = trim($_POST['mediaName'] ?? '');
        $mediaLink    = trim($_POST['mediaLink'] ?? '');
        $objective    = trim($_POST['objective'] ?? '');
        $usageResults = trim($_POST['usageResults'] ?? '');
        $coverUrl     = trim($_POST['cover'] ?? $existing['cover_url']);

        if (!$grade || !$subject || !$mediaName || !$mediaLink) {
            echo json_encode(['status' => 'fail', 'msg' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }

        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverUrl = handleCoverUpload($_FILES['cover']);
        }

        $stmt = $pdo->prepare("
            UPDATE learning_media
            SET media_date = ?, author = ?, grade = ?, subject = ?, media_type = ?, media_name = ?, media_link = ?,
                objective = ?, usage_results = ?, cover_url = ?
            WHERE id = ?
        ");
        $stmt->execute([$mediaDate, $author, $grade, $subject, $mediaType, $mediaName, $mediaLink, $objective ?: null, $usageResults ?: null, $coverUrl, $id]);

        echo json_encode(['status' => 'success', 'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 7. DELETE MEDIA (Requires login & permission)
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

        $stmt = $pdo->prepare("UPDATE learning_media SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['status' => 'success', 'message' => 'ลบสื่อการเรียนรู้เรียบร้อยแล้ว']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 8. APPROVE MEDIA (Academic Head & Director)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'approve') {
        if (!isset($_SESSION['llw_role'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
            exit;
        }

        $id           = (int)($_POST['id'] ?? 0);
        $step         = trim($_POST['step'] ?? 'head'); // 'head' or 'director'
        $comment      = trim($_POST['comment'] ?? '');
        $signature    = trim($_POST['signature'] ?? '');
        $approverName = trim($_POST['approver_name'] ?? '');

        if ($id <= 0) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบรหัสสื่อ']);
            exit;
        }

        $now = date('Y-m-d H:i:s');

        if ($step === 'head') {
            $headName = $approverName ?: 'นายเฉลิมชัย ศรีชัย';
            $headComment = $comment ?: 'ทราบ และเห็นควรอนุมัติ';

            $sql = "UPDATE learning_media SET academic_head_approved = 1, academic_head_name = ?, academic_head_comment = ?, academic_head_at = ?";
            $params = [$headName, $headComment, $now];
            if ($signature) {
                $sql .= ", academic_head_signature = ?";
                $params[] = $signature;
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['status' => 'success', 'message' => 'หัวหน้าฝ่ายวิชาการอนุมัติเรียบร้อยแล้ว', 'at' => toThaiDateString(date('Y-m-d'))]);
            exit;
        } elseif ($step === 'director') {
            $directorName = $approverName ?: 'นายสถาน ปรางมาศ';
            $directorComment = $comment ?: 'ทราบ และอนุมัติ';

            $sql = "UPDATE learning_media SET director_approved = 1, director_name = ?, director_comment = ?, director_at = ?";
            $params = [$directorName, $directorComment, $now];
            if ($signature) {
                $sql .= ", director_signature = ?";
                $params[] = $signature;
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['status' => 'success', 'message' => 'ผู้อำนวยการอนุมัติเรียบร้อยแล้ว', 'at' => toThaiDateString(date('Y-m-d'))]);
            exit;
        } else {
            echo json_encode(['status' => 'fail', 'msg' => 'ขั้นตอนการอนุมัติไม่ถูกต้อง']);
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 9. UNAPPROVE MEDIA (Reset)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'unapprove') {
        if (!isset($_SESSION['llw_role'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
            exit;
        }

        $id   = (int)($_POST['id'] ?? 0);
        $step = trim($_POST['step'] ?? 'head');

        if ($id <= 0) {
            echo json_encode(['status' => 'fail', 'msg' => 'ไม่พบรหัสสื่อ']);
            exit;
        }

        if ($step === 'head') {
            $stmt = $pdo->prepare("UPDATE learning_media SET academic_head_approved = 0, academic_head_at = NULL, academic_head_signature = NULL WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการอนุมัติฝ่ายวิชาการแล้ว']);
            exit;
        } elseif ($step === 'director') {
            $stmt = $pdo->prepare("UPDATE learning_media SET director_approved = 0, director_at = NULL, director_signature = NULL WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการอนุมัติผู้อำนวยการแล้ว']);
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 10. SAVE SIGNATURE (For Head, Director, or Author)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'save_signature') {
        $id        = (int)($_POST['id'] ?? 0);
        $target    = trim($_POST['target'] ?? '');
        $signature = trim($_POST['signature'] ?? '');

        if ($id <= 0 || !$target) {
            echo json_encode(['status' => 'fail', 'msg' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        if ($target === 'head') {
            $stmt = $pdo->prepare("UPDATE learning_media SET academic_head_signature = ? WHERE id = ?");
            $stmt->execute([$signature ?: null, $id]);
        } elseif ($target === 'director') {
            $stmt = $pdo->prepare("UPDATE learning_media SET director_signature = ? WHERE id = ?");
            $stmt->execute([$signature ?: null, $id]);
        }

        echo json_encode(['status' => 'success', 'message' => 'บันทึกลายเซ็นเรียบร้อยแล้ว']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
} catch (Exception $e) {
    error_log('[Learning Media API Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'msg' => $e->getMessage()]);
}
