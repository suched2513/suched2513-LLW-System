<?php
session_start();
require_once __DIR__ . '/../config.php';

// Allow both teachers and students
if (!isset($_SESSION['llw_role']) && !isset($_SESSION['student_uid'])) {
    http_response_code(403); exit;
}

$fname = basename(rawurldecode($_GET['f'] ?? ''));
if (!$fname || !preg_match('/^[\w\-\.]+$/', $fname)) {
    http_response_code(400); exit;
}

$path = __DIR__ . '/uploads/exercises/' . $fname;
if (!file_exists($path) || !is_file($path)) {
    http_response_code(404); exit;
}

$ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
$mime_map = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'm4v' => 'video/mp4',
    'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo',
    '3gp' => 'video/3gpp', 'webm' => 'video/webm',
    'pdf' => 'application/pdf',
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';
$size = filesize($path);

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    preg_match('/bytes=(\d*)-(\d*)/', $range, $m);
    $start  = $m[1] !== '' ? (int)$m[1] : 0;
    $end    = $m[2] !== '' ? (int)$m[2] : $size - 1;
    $length = $end - $start + 1;

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . $length);

    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    $remaining = $length;
    while (!feof($fp) && $remaining > 0) {
        echo fread($fp, min(65536, $remaining));
        $remaining -= 65536;
        flush();
    }
    fclose($fp);
} else {
    header('Content-Length: ' . $size);
    readfile($path);
}
exit;
