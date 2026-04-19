<?php
session_start();
$_SESSION['llw_role'] = 'super_admin';
$_SESSION['user_id'] = 1;
$_SESSION['firstname'] = 'Admin';
$_SESSION['lastname'] = 'System';

// Hack to mock php://input
function mockInput($data) {
    $file = 'php://temp';
    $handle = fopen($file, 'r+');
    fwrite($handle, json_encode($data));
    rewind($handle);
    return $file;
}

// Actually, let's just modify the API logic to be more flexible momentarily or just run the logic directly.
require_once 'config/database.php';
$pdo = getPdo();
$input = ['date' => '2026-04-18'];
include 'behavior/api/sync_attendance_to_behavior.php';
