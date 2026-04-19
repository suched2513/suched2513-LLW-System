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
    $homeroom   = trim($input['homeroom'] ?? '');
    $img        = trim($input['img'] ?? '');

    if ($studentId === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสนักเรียนและชื่อ']);
        exit;
    }

    // Check if updating existing or creating new
    $existingId = $originalId ?: $studentId;
    $stmt = $pdo->prepare("SELECT id FROM beh_students WHERE student_id = ?");
    $stmt->execute([$existingId]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update
        $sql = "UPDATE beh_students SET student_id = ?, name = ?, level = ?, room = ?, homeroom = ?, img_url = ?, status = 'active' WHERE student_id = ?";
        $pdo->prepare($sql)->execute([$studentId, $name, $level, $room, $homeroom, $img ?: null, $existingId]);
    } else {
        // Insert
        $sql = "INSERT INTO beh_students (student_id, name, level, room, homeroom, img_url) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$studentId, $name, $level, $room, $homeroom, $img ?: null]);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลนักเรียนสำเร็จ']);

} catch (Exception $e) {
    error_log('[behavior] manage_students error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
