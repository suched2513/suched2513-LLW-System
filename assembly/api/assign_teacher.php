<?php
/**
 * assembly/api/assign_teacher.php
 * POST JSON — ผูกครูกับห้องเรียน (ซิงค์เข้า llw_class_advisors และ beh_advisors อัตโนมัติ)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if ($_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'เฉพาะ Super Admin เท่านั้น']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$classroom = preg_replace('/^ม\.+/', 'ม.', trim($input['classroom'] ?? ''));
$userId    = ($input['llw_user_id'] ?? '') !== '' && ($input['llw_user_id'] ?? null) !== null ? (int)$input['llw_user_id'] : null;

if ($classroom === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุห้องเรียน']);
    exit;
}

try {
    $pdo = getPdo();

    $teacherName = null;
    if ($userId) {
        $nameRow = $pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) AS name FROM llw_users WHERE user_id = ? LIMIT 1");
        $nameRow->execute([$userId]);
        $row = $nameRow->fetch();
        $teacherName = $row ? trim($row['name']) : null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO assembly_classrooms (classroom, llw_user_id, teacher_name)
        VALUES (:classroom, :user_id, :teacher_name)
        ON DUPLICATE KEY UPDATE llw_user_id = VALUES(llw_user_id), teacher_name = COALESCE(VALUES(teacher_name), teacher_name)
    ");
    $stmt->execute([':classroom' => $classroom, ':user_id' => $userId, ':teacher_name' => $teacherName]);

    // ซิงค์เข้า llw_class_advisors และ beh_advisors เพื่อให้สิทธิ์เช็คชื่อเข้าแถวทำงานตรงกันทันที
    if ($userId) {
        try {
            $caCols = $pdo->query("SHOW COLUMNS FROM `llw_class_advisors`")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('role_type', $caCols, true)) {
                $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, 'primary')")->execute([$classroom, $userId]);
            } else {
                $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id) VALUES (?, ?)")->execute([$classroom, $userId]);
            }
        } catch (Throwable $ignore) {}

        if (strpos($classroom, '/') !== false) {
            try {
                [$lvl, $subRm] = array_map('trim', explode('/', $classroom, 2));
                $pdo->prepare("INSERT IGNORE INTO beh_advisors (user_id, level, room) VALUES (?, ?, ?)")->execute([$userId, $lvl, $subRm]);
            } catch (Throwable $ignore) {}
        }
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log('[Assembly] assign_teacher: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
