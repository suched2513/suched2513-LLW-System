<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'กรุณาเข้าสู่ระบบ']); exit; }
if ($_SESSION['llw_role'] !== 'super_admin') { http_response_code(403); echo json_encode(['status'=>'error','message'=>'ไม่มีสิทธิ์']); exit; }

$action     = $_GET['action'] ?? 'list';
$teacher_id = (int)($_SESSION['user_id'] ?? 0);

try {
    $pdo = getPdo();

    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("SELECT id, name FROM hw_subjects WHERE teacher_id = ? ORDER BY name ASC");
            $stmt->execute([$teacher_id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        case 'add':
            $input = json_decode(file_get_contents('php://input'), true);
            $name  = trim($input['name'] ?? '');
            if ($name === '') { echo json_encode(['status'=>'error','message'=>'กรุณาระบุชื่อวิชา']); exit; }

            $check = $pdo->prepare("SELECT id FROM hw_subjects WHERE name = ? AND teacher_id = ?");
            $check->execute([$name, $teacher_id]);
            if ($check->fetch()) { echo json_encode(['status'=>'error','message'=>'มีวิชานี้อยู่แล้ว']); exit; }

            $stmt = $pdo->prepare("INSERT INTO hw_subjects (name, teacher_id) VALUES (?, ?)");
            $stmt->execute([$name, $teacher_id]);
            echo json_encode(['status' => 'success', 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
            break;

        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int)($input['id'] ?? 0);
            if (!$id) { echo json_encode(['status'=>'error','message'=>'ข้อมูลไม่ถูกต้อง']); exit; }
            $pdo->prepare("DELETE FROM hw_subjects WHERE id = ? AND teacher_id = ?")->execute([$id, $teacher_id]);
            echo json_encode(['status' => 'success']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'invalid action']);
    }
} catch (Exception $e) {
    error_log('[homework] manage_subjects: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
