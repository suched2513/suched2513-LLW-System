<?php
// api/get_teachers.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_central.php';

try {
    $stmt = $pdo_central->query("SELECT t_id, t_name FROM teachers ORDER BY t_name ASC");
    $teachers = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $teachers]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
