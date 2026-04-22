<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("SELECT id, activity_name, budget_allocated, budget_used FROM sbms_activities WHERE project_id = ? ORDER BY activity_name");
    $stmt->execute([$projectId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($activities);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
