<?php
/**
 * API: Manage Advisor Mappings
 * GET ?action=list
 * POST { action: 'save'|'delete', level, room, mappingId }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();
    $userId = $_SESSION['user_id'];

    // --- SELF-HEALING: Ensure Table Exists ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS beh_advisors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        level VARCHAR(10) NOT NULL,
        room VARCHAR(10) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_room (user_id, level, room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Handle both GET and POST input
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$action) $action = $input['action'] ?? '';

    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM beh_advisors WHERE user_id = ? ORDER BY CAST(`level` AS UNSIGNED), CAST(`room` AS UNSIGNED)");
        $stmt->execute([$userId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    if ($action === 'save') {
        $level = trim($input['level'] ?? '');
        $room  = trim($input['room'] ?? '');
        
        if ($level === '' || $room === '') {
            if (ob_get_length()) ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
            exit;
        }

        // Check if mapping already exists for this user
        $stmt = $pdo->prepare("SELECT id FROM beh_advisors WHERE user_id = ? AND level = ? AND room = ?");
        $stmt->execute([$userId, $level, $room]);
        if ($stmt->fetch()) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'ระบุห้องนี้ไปแล้ว']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO beh_advisors (user_id, level, room) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $level, $room]);
        
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มห้องที่ดูแลแล้ว']);
        exit;
    }

    if ($action === 'delete') {
        $mid = (int)($input['mappingId'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM beh_advisors WHERE id = ? AND user_id = ?");
        $stmt->execute([$mid, $userId]);
        
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลแล้ว']);
        exit;
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Action not found: ' . $action]);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    error_log('[LLW] manage_advisors error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
