<?php
/**
 * API: Manage students (CRUD)
 * POST JSON: { action: 'save'|'delete', ... }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'save';

try {
    $pdo = getPdo();

    if ($action === 'delete') {
        $studentId = trim($input['studentId'] ?? '');
        if ($studentId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสนักเรียน']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE beh_students SET status = 'inactive' WHERE student_id = ?");
        $stmt->execute([$studentId]);
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลนักเรียนแล้ว']);
        exit;
    }

    // Save (insert or update)
    $studentId  = trim($input['studentId'] ?? '');
    $originalId = trim($input['originalId'] ?? '');
    $name       = trim($input['name'] ?? '');
    $level      = trim($input['level'] ?? '');
    $room       = trim($input['room'] ?? '');
    $classroom  = $level . '/' . $room;
    $homeroom   = trim($input['homeroom'] ?? '');
    $img        = trim($input['img'] ?? '');

    if (preg_match('/^\d+$/', $studentId)) $studentId = str_pad($studentId, 5, '0', STR_PAD_LEFT);
    if (preg_match('/^\d+$/', $originalId)) $originalId = str_pad($originalId, 5, '0', STR_PAD_LEFT);

    if ($studentId === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสนักเรียนและชื่อ']);
        exit;
    }

    $pdo->beginTransaction();

    // 1. Manage Master (att_students)
    $existingId = $originalId ?: $studentId;
    $checkMaster = $pdo->prepare("SELECT id FROM att_students WHERE student_id = ?");
    $checkMaster->execute([$existingId]);
    
    if ($checkMaster->fetch()) {
        $pdo->prepare("UPDATE att_students SET student_id = ?, name = ?, classroom = ? WHERE student_id = ?")
            ->execute([$studentId, $name, $classroom, $existingId]);
    } else {
        $pdo->prepare("INSERT INTO att_students (student_id, name, classroom) VALUES (?, ?, ?)")
            ->execute([$studentId, $name, $classroom]);
    }

    // 2. Manage Meta (beh_students)
    $checkMeta = $pdo->prepare("SELECT id FROM beh_students WHERE student_id = ?");
    $checkMeta.execute([$studentId]); // Use the new ID
    
    if ($checkMeta->fetch()) {
        $pdo->prepare("UPDATE beh_students SET homeroom = ?, img_url = ?, status = 'active' WHERE student_id = ?")
            ->execute([$homeroom, $img ?: null, $studentId]);
    } else {
        $pdo->prepare("INSERT INTO beh_students (student_id, name, level, room, homeroom, img_url) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$studentId, $name, $level, $room, $homeroom, $img ?: null]);
    }

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลนักเรียนสำเร็จ']);

} catch (Exception $e) {
    error_log('[behavior] manage_students error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
