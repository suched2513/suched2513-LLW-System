<?php
/**
 * API: Get behavior templates
 * GET — returns { goods: [], bads: [] }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->query("SELECT id, type, name, score FROM beh_templates WHERE status = 'active' ORDER BY type, name");
    $all = $stmt->fetchAll();

    $goods = [];
    $bads  = [];
    foreach ($all as $t) {
        $item = [
            'id'    => $t['id'],
            'type'  => $t['type'],
            'name'  => $t['name'],
            'score' => (int)$t['score'],
        ];
        if ($t['type'] === 'ความดี') {
            $goods[] = $item;
        } else {
            $bads[] = $item;
        }
    }

    echo json_encode(['status' => 'success', 'goods' => $goods, 'bads' => $bads]);

} catch (Exception $e) {
    error_log('[behavior] get_templates error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
