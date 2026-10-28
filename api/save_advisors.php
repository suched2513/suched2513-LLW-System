<?php
/**
 * api/save_advisors.php
 * POST { mappings: { "ม.1/1": [u1, u2], ... } }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mappings = $input['mappings'] ?? [];

if (empty($mappings)) {
    echo json_encode(['status' => 'success', 'message' => 'ไม่มีการเปลี่ยนแปลง']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. Clear ALL existing mappings in llw_class_advisors
    $pdo->exec("DELETE FROM llw_class_advisors");

    // 2. Insert new mappings into llw_class_advisors
    $caCols = $pdo->query("SHOW COLUMNS FROM `llw_class_advisors`")->fetchAll(PDO::FETCH_COLUMN);
    $hasRoleType = in_array('role_type', $caCols, true);
    $stmt = $hasRoleType
        ? $pdo->prepare("INSERT INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, ?)")
        : $pdo->prepare("INSERT INTO llw_class_advisors (classroom, user_id) VALUES (?, ?)");

    $primaryByRoom = [];
    foreach ($mappings as $room => $userIds) {
        $cleanRoom   = preg_replace('/^ม\.+/', 'ม.', trim((string)$room));
        $uniqueUsers = array_values(array_unique(array_filter((array)$userIds)));
        foreach ($uniqueUsers as $idx => $uid) {
            $uidInt = (int)$uid;
            if ($uidInt <= 0) continue;
            $roleType = ($idx === 0) ? 'primary' : 'secondary';
            if ($hasRoleType) {
                $stmt->execute([$cleanRoom, $uidInt, $roleType]);
            } else {
                $stmt->execute([$cleanRoom, $uidInt]);
            }
            if ($idx === 0) {
                $primaryByRoom[$cleanRoom] = $uidInt;
            }
        }
    }

    // 3. Sync to assembly_classrooms & beh_advisors so all modules see the same advisor data
    try {
        $upAsm = $pdo->prepare("
            INSERT INTO assembly_classrooms (classroom, llw_user_id, teacher_name)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE llw_user_id = VALUES(llw_user_id), teacher_name = VALUES(teacher_name)
        ");
        $nameSt = $pdo->prepare("SELECT TRIM(CONCAT(COALESCE(firstname,''), ' ', COALESCE(lastname,''))) FROM llw_users WHERE user_id = ? LIMIT 1");
        foreach ($primaryByRoom as $rm => $uidInt) {
            $nameSt->execute([$uidInt]);
            $tName = $nameSt->fetchColumn() ?: null;
            $upAsm->execute([$rm, $uidInt, $tName]);
        }
    } catch (Throwable $ignore) {}

    try {
        $pdo->exec("DELETE FROM beh_advisors");
        $insBeh = $pdo->prepare("INSERT IGNORE INTO beh_advisors (user_id, level, room) VALUES (?, ?, ?)");
        foreach ($mappings as $room => $userIds) {
            $cleanRoom = preg_replace('/^ม\.+/', 'ม.', trim((string)$room));
            if (strpos($cleanRoom, '/') === false) continue;
            [$lvl, $subRm] = array_map('trim', explode('/', $cleanRoom, 2));
            foreach (array_unique((array)$userIds) as $uid) {
                if ((int)$uid > 0) {
                    $insBeh->execute([(int)$uid, $lvl, $subRm]);
                }
            }
        }
    } catch (Throwable $ignore) {}

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[API] save_advisors error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
}
