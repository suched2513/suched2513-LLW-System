<?php
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    exit('Unauthorized');
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'wfh_staff', 'att_teacher'])) {
    http_response_code(403);
    exit('Forbidden');
}

$file = basename($_GET['f'] ?? '');
if (!$file) {
    http_response_code(400);
    exit('Bad Request');
}

$path = __DIR__ . '/../../uploads/sar_evidence/' . $file;
if (!file_exists($path) || !is_file($path)) {
    http_response_code(404);
    exit('Not Found');
}

$ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($file) . '"');
readfile($path);
