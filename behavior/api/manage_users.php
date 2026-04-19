<?php
/**
 * API: Manage behavior system users (CRUD)
 * POST JSON: { action: 'save'|'delete'|'list', ... }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ (super_admin only)']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? 'list');

try {
    $pdo = getPdo();

    // LIST
    if ($action === 'list' || $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT id, username, name, role, active FROM beh_system_users ORDER BY role, name");
        echo json_encode($stmt->fetchAll());
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
        $pdo->prepare("DELETE FROM beh_system_users WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ลบผู้ใช้แล้ว']);
        exit;
    }

    // SAVE
    $id       = $input['id'] ?? null;
    $username = trim($input['username'] ?? '');
    $name     = trim($input['name'] ?? '');
    $role     = trim($input['role'] ?? 'teacher');
    $password = $input['password'] ?? null;

    if ($username === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอก Username และชื่อ']);
        exit;
    }

    if (!in_array($role, ['admin', 'teacher', 'homeroom'], true)) {
        $role = 'teacher';
    }

    if ($id) {
        // Update
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE beh_system_users SET username = ?, name = ?, role = ?, password_hash = ? WHERE id = ?")
                ->execute([$username, $name, $role, $hash, $id]);
        } else {
            $pdo->prepare("UPDATE beh_system_users SET username = ?, name = ?, role = ? WHERE id = ?")
                ->execute([$username, $name, $role, $id]);
        }
    } else {
        // Insert
        $pw = $password ?: $username; // default password = username
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO beh_system_users (username, password_hash, name, role) VALUES (?, ?, ?, ?)")
            ->execute([$username, $hash, $name, $role]);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกผู้ใช้สำเร็จ']);

} catch (Exception $e) {
    error_log('[behavior] manage_users error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
