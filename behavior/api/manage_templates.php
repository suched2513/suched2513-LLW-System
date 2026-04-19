<?php
/**
 * API: Manage behavior templates (CRUD)
 * POST JSON: { action: 'save'|'delete'|'list', ... }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? 'list');

try {
    $pdo = getPdo();

    // LIST
    if ($action === 'list' || $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT id, type, name, score FROM beh_templates WHERE status = 'active' ORDER BY type, name");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // Need admin for save/delete
    if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID']);
            exit;
        }
        $pdo->prepare("UPDATE beh_templates SET status = 'inactive' WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ลบแม่แบบแล้ว']);
        exit;
    }

    // SAVE (insert or update)
    $id    = $input['id'] ?? null;
    $type  = trim($input['type'] ?? '');
    $name  = trim($input['name'] ?? '');
    $score = (int)($input['score'] ?? 0);

    if (!in_array($type, ['ความดี', 'ความผิด'], true) || $name === '' || $score <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
        exit;
    }

    if ($id) {
        $pdo->prepare("UPDATE beh_templates SET type = ?, name = ?, score = ? WHERE id = ?")->execute([$type, $name, $score, $id]);
    } else {
        $pdo->prepare("INSERT INTO beh_templates (type, name, score) VALUES (?, ?, ?)")->execute([$type, $name, $score]);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกแม่แบบสำเร็จ']);

} catch (Exception $e) {
    error_log('[behavior] manage_templates error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
