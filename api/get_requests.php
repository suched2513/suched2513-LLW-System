<?php
// api/get_requests.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/db_central.php';
require_once '../config/db_project.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

try {
    $teacher_id = $_SESSION['user_id'];
    
    // Select requests with teacher name from central DB
    $sql = "SELECT r.*, t.t_name 
            FROM exit_permit_db.requests r 
            JOIN school_central_db.teachers t ON r.teacher_id = t.t_id 
            ORDER BY r.req_date DESC";
            
    $stmt = $pdo_project->query($sql);
    $requests = $stmt->fetchAll();
    
    echo json_encode(['status' => 'success', 'data' => $requests]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
