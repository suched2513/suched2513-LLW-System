<?php
// api/auth.php
session_start();
require_once '../config/db_central.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['username']) && isset($input['password'])) {
    $stmt = $pdo_central->prepare("SELECT * FROM teachers WHERE t_username = ?");
    $stmt->execute([$input['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($input['password'], $user['t_password'])) {
        $_SESSION['user_id'] = $user['t_id'];
        $_SESSION['user_name'] = $user['t_name'];
        $_SESSION['user_role'] = 'teacher';
        echo json_encode(['status' => 'success', 'message' => 'Login successful.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing username or password.']);
}
?>
