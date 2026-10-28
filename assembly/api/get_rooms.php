<?php
/**
 * assembly/api/get_rooms.php
 * GET — ดึงรายการห้องเรียนและชั้น พร้อมระบุห้องที่ปรึกษาของผู้ใช้ที่ล็อกอิน
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] === 'student') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบสำหรับครู/บุคลากร']);
    exit;
}

try {
    $pdo    = getPdo();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    // 1. ดึงห้องที่ปรึกษาของผู้ใช้จากทุกตารางที่เกี่ยวข้อง (llw_class_advisors, assembly_classrooms, beh_advisors)
    $advisorRooms = [];
    if ($userId > 0) {
        try {
            $stAdv = $pdo->prepare("SELECT DISTINCT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
            $stAdv->execute([$userId]);
            foreach ($stAdv->fetchAll(PDO::FETCH_COLUMN) as $rm) {
                $clean = preg_replace('/^ม\.+/', 'ม.', trim((string)$rm));
                if ($clean !== '') $advisorRooms[] = $clean;
            }
        } catch (Throwable $ignore) {}

        try {
            $stAsm = $pdo->prepare("SELECT DISTINCT classroom FROM assembly_classrooms WHERE llw_user_id = ? ORDER BY classroom");
            $stAsm->execute([$userId]);
            foreach ($stAsm->fetchAll(PDO::FETCH_COLUMN) as $rm) {
                $clean = preg_replace('/^ม\.+/', 'ม.', trim((string)$rm));
                if ($clean !== '') $advisorRooms[] = $clean;
            }
        } catch (Throwable $ignore) {}

        try {
            $stBeh = $pdo->prepare("SELECT level, room FROM beh_advisors WHERE user_id = ?");
            $stBeh->execute([$userId]);
            foreach ($stBeh->fetchAll(PDO::FETCH_ASSOC) as $br) {
                $lvl = trim((string)($br['level'] ?? ''));
                $sub = trim((string)($br['room'] ?? ''));
                if ($lvl !== '' && $sub !== '') {
                    $prefix = (strpos($lvl, 'ม.') === 0) ? $lvl : ('ม.' . $lvl);
                    $advisorRooms[] = preg_replace('/^ม\.+/', 'ม.', $prefix . '/' . $sub);
                }
            }
        } catch (Throwable $ignore) {}
    }
    $advisorRooms = array_values(array_unique($advisorRooms));

    // 2. ดึงห้องเรียนทั้งหมดที่มีนักเรียน active เพื่อให้ครูที่ปรึกษา / ครูเวร / ครูผู้สอนเช็คแถวได้ทุกห้องโดยไม่ติดห้องว่าง
    $curYear = function_exists('llw_current_academic_year') ? llw_current_academic_year() : 2569;
    $stmt = $pdo->prepare("
        SELECT DISTINCT classroom
        FROM att_students
        WHERE status = 'active'
          AND (academic_year = ? OR academic_year IS NULL)
          AND classroom REGEXP '^ม\\\\.[0-9]+/[0-9]+'
        ORDER BY classroom
    ");
    $stmt->execute([$curYear]);
    $allClassrooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allClassrooms)) {
        $stmt = $pdo->query("
            SELECT DISTINCT classroom
            FROM att_students
            WHERE status = 'active'
              AND classroom REGEXP '^ม\\\\.[0-9]+/[0-9]+'
            ORDER BY classroom
        ");
        $allClassrooms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // จัดลำดับให้ห้องที่ปรึกษาของครูขึ้นก่อน ตามด้วยห้องอื่นๆ เรียงตามชั้น
    $classrooms = array_values(array_unique(array_merge($advisorRooms, $allClassrooms)));

    // สร้าง grades จาก classroom
    $grades = [];
    foreach ($classrooms as $c) {
        if (preg_match('/ม\.(\d+)/', $c, $m)) {
            $grades[] = 'ม.' . $m[1];
        }
    }
    $grades = array_values(array_unique($grades));
    sort($grades);

    echo json_encode([
        'status'        => 'success',
        'classrooms'    => $classrooms,
        'advisor_rooms' => $advisorRooms,
        'grades'        => $grades,
    ]);
} catch (Exception $e) {
    error_log('[Assembly] get_rooms: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
