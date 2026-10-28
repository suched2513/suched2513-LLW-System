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
    $userId = (int)($_SESSION['user_id'] ?? 0);

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

        // ซิงค์เข้า llw_class_advisors ด้วย
        try {
            $prefix   = (strpos($level, 'ม.') === 0) ? $level : ('ม.' . $level);
            $fullRoom = preg_replace('/^ม\.+/', 'ม.', $prefix . '/' . $room);
            $caCols   = $pdo->query("SHOW COLUMNS FROM `llw_class_advisors`")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('role_type', $caCols, true)) {
                $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, 'secondary')")->execute([$fullRoom, $userId]);
            } else {
                $pdo->prepare("INSERT IGNORE INTO llw_class_advisors (classroom, user_id) VALUES (?, ?)")->execute([$fullRoom, $userId]);
            }
        } catch (Throwable $ignore) {}

        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มห้องที่ดูแลแล้ว']);
        exit;
    }

    if ($action === 'delete') {
        $mid = (int)($input['mappingId'] ?? 0);
        $rowSt = $pdo->prepare("SELECT level, room FROM beh_advisors WHERE id = ? AND user_id = ? LIMIT 1");
        $rowSt->execute([$mid, $userId]);
        $delRow = $rowSt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM beh_advisors WHERE id = ? AND user_id = ?");
        $stmt->execute([$mid, $userId]);

        if ($delRow) {
            try {
                $prefix   = (strpos($delRow['level'], 'ม.') === 0) ? $delRow['level'] : ('ม.' . $delRow['level']);
                $fullRoom = preg_replace('/^ม\.+/', 'ม.', $prefix . '/' . $delRow['room']);
                $pdo->prepare("DELETE FROM llw_class_advisors WHERE classroom = ? AND user_id = ?")->execute([$fullRoom, $userId]);
            } catch (Throwable $ignore) {}
        }

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
